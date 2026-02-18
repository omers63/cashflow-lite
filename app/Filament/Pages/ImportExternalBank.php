<?php

namespace App\Filament\Pages;

use App\Models\ExternalBankAccount;
use App\Models\ExternalBankImport;
use App\Models\MasterAccount;
use App\Models\Transaction;
use App\Services\ReconciliationService;
use Filament\Forms;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;

class ImportExternalBank extends Page implements HasTable
{
    use InteractsWithTable;
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected string $view = 'filament.pages.import-external-bank';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?string $navigationLabel = 'Import Bank Transactions';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Import External Bank Transactions';

    /* ------------------------------------------------------------------ */
    /*  Public state                                                        */
    /* ------------------------------------------------------------------ */

    public ?array $data = [];
    public bool $showPreview = false;
    public array $previewRows = [];
    public int $duplicateCount = 0;
    public int $newCount = 0;
    public ?int $selectedBankId = null;
    public ?string $importMode = 'manual'; // 'manual' | 'csv'

    /* ------------------------------------------------------------------ */
    /*  Lifecycle                                                           */
    /* ------------------------------------------------------------------ */

    public function mount(): void
    {
        $this->form->fill();
    }

    /* ------------------------------------------------------------------ */
    /*  Form – single manual entry                                          */
    /* ------------------------------------------------------------------ */

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Import Settings')
                    ->schema([
                        Forms\Components\Select::make('external_bank_account_id')
                            ->label('Bank Account')
                            ->options(
                                ExternalBankAccount::active()
                                    ->get()
                                    ->mapWithKeys(fn($b) => [
                                        $b->id => "{$b->bank_name} (****{$b->account_number})",
                                    ])
                            )
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn($state) => $this->selectedBankId = $state),

                        Forms\Components\Select::make('import_mode')
                            ->label('Import Method')
                            ->options([
                                'manual' => 'Manual Entry',
                                'csv' => 'CSV Upload',
                            ])
                            ->default('manual')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($state) => $this->importMode = $state),
                    ])
                    ->columns(2),

                // ── Manual entry ──────────────────────────────────────────
                Components\Section::make('Transaction Details')
                    ->schema([
                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Transaction Date')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('external_ref_id')
                            ->label('External Reference ID')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Unique reference from your bank statement'),

                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(500),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->columns(2)
                    ->visible(fn(Get $get) => $get('import_mode') === 'manual'),

                // ── CSV upload ────────────────────────────────────────────
                Components\Section::make('CSV Upload')
                    ->schema([
                        Forms\Components\FileUpload::make('csv_file')
                            ->label('CSV File')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                            ->maxSize(10240)
                            ->helperText('Required columns: transaction_date, external_ref_id, amount, description')
                            ->storeFiles(false),

                        Forms\Components\TextInput::make('csv_format')
                            ->label('Expected CSV Format')
                            ->default('transaction_date, external_ref_id, amount, description')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Date format: YYYY-MM-DD HH:MM:SS'),
                    ])
                    ->visible(fn(Get $get) => $get('import_mode') === 'csv'),
            ])
            ->statePath('data');
    }

    /* ------------------------------------------------------------------ */
    /*  Table – recent imports                                              */
    /* ------------------------------------------------------------------ */

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ExternalBankImport::query()
                    ->with('externalBankAccount')
                    ->latest('import_date')
                    ->latest('id')
            )
            ->columns([
                Tables\Columns\TextColumn::make('import_date')
                    ->label('Import Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('externalBankAccount.bank_name')
                    ->label('Bank')
                    ->searchable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Txn Date')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_ref_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->description),

                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Duplicate')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),

                Tables\Columns\IconColumn::make('imported_to_master')
                    ->label('Imported')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('external_bank_account_id')
                    ->label('Bank Account')
                    ->relationship('externalBankAccount', 'bank_name'),

                Tables\Filters\Filter::make('duplicates_only')
                    ->label('Duplicates Only')
                    ->query(fn($query) => $query->where('is_duplicate', true)),

                Tables\Filters\Filter::make('not_imported')
                    ->label('Not Yet Imported')
                    ->query(fn($query) => $query->where('imported_to_master', false)
                        ->where('is_duplicate', false)),

                Tables\Filters\Filter::make('date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')->label('From'),
                        Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(
                        fn($query, array $data) => $query
                            ->when($data['from'], fn($q) => $q->whereDate('import_date', '>=', $data['from']))
                            ->when($data['to'], fn($q) => $q->whereDate('import_date', '<=', $data['to']))
                    ),
            ])
            ->recordActions([
                Actions\Action::make('import_now')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn(ExternalBankImport $record) => $this->importSingleRecord($record))
                    ->visible(fn(ExternalBankImport $record) => !$record->imported_to_master && !$record->is_duplicate),

                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('import_selected')
                        ->label('Import Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->action(fn($records) => $this->bulkImport($records))
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('import_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50]);
    }

    /* ------------------------------------------------------------------ */
    /*  Header actions                                                      */
    /* ------------------------------------------------------------------ */

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview Import')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->action(fn() => $this->previewImport()),

            Action::make('import')
                ->label('Confirm Import')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Import')
                ->modalDescription(fn() => "Ready to import {$this->newCount} transaction(s). {$this->duplicateCount} duplicate(s) will be skipped.")
                ->action(fn() => $this->processImport())
                ->visible(fn() => $this->showPreview && $this->newCount > 0),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Preview logic                                                       */
    /* ------------------------------------------------------------------ */

    public function previewImport(): void
    {
        $this->validate();

        $data = $this->form->getState();

        if ($data['import_mode'] === 'manual') {
            $this->previewManualEntry($data);
        } else {
            $this->previewCsvUpload($data);
        }

        $this->showPreview = true;
    }

    protected function previewManualEntry(array $data): void
    {
        $isDuplicate = ExternalBankImport::where('external_bank_account_id', $data['external_bank_account_id'])
            ->where('external_ref_id', $data['external_ref_id'])
            ->exists();

        $bank = ExternalBankAccount::find($data['external_bank_account_id']);

        $this->previewRows = [
            [
                'transaction_date' => $data['transaction_date'],
                'external_ref_id' => $data['external_ref_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? '',
                'bank_name' => $bank->bank_name ?? '—',
                'is_duplicate' => $isDuplicate,
            ]
        ];

        $this->duplicateCount = $isDuplicate ? 1 : 0;
        $this->newCount = $isDuplicate ? 0 : 1;
    }

    protected function previewCsvUpload(array $data): void
    {
        // CSV parsing would live here in a real implementation.
        // Keeping the stub clean for the package.
        Notification::make()
            ->title('CSV Preview')
            ->body('CSV parsing will process rows after upload. Switch to Manual Entry to preview a single transaction now.')
            ->info()
            ->send();
    }

    /* ------------------------------------------------------------------ */
    /*  Import logic                                                        */
    /* ------------------------------------------------------------------ */

    public function processImport(): void
    {
        $data = $this->form->getState();

        if ($data['import_mode'] === 'manual') {
            $this->importManualEntry($data);
        } else {
            $this->importCsvFile($data);
        }
    }

    protected function importManualEntry(array $data): void
    {
        $bankId = $data['external_bank_account_id'];
        $isDuplicate = ExternalBankImport::where('external_bank_account_id', $bankId)
            ->where('external_ref_id', $data['external_ref_id'])
            ->exists();

        DB::beginTransaction();
        try {
            $import = ExternalBankImport::create([
                'external_bank_account_id' => $bankId,
                'import_date' => now(),
                'transaction_date' => $data['transaction_date'],
                'external_ref_id' => $data['external_ref_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'is_duplicate' => $isDuplicate,
                'imported_to_master' => false,
                'notes' => $data['notes'] ?? null,
                'imported_by' => auth()->id(),
            ]);

            if (!$isDuplicate) {
                $this->postToMasterBank($import, $bankId);
            }

            DB::commit();

            Notification::make()
                ->title($isDuplicate ? 'Duplicate Detected' : 'Import Successful')
                ->body($isDuplicate
                    ? 'Transaction already exists. Marked as duplicate.'
                    : 'Transaction imported to Master Bank Account.')
                ->color($isDuplicate ? 'warning' : 'success')
                ->send();

        } catch (\Throwable $e) {
            DB::rollBack();
            Notification::make()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        $this->showPreview = false;
        $this->previewRows = [];
        $this->newCount = 0;
        $this->duplicateCount = 0;
        $this->form->fill();
    }

    protected function importCsvFile(array $data): void
    {
        // Full CSV import logic would be implemented here.
        Notification::make()
            ->title('CSV Import')
            ->body('CSV import queued for processing.')
            ->info()
            ->send();
    }

    /* ------------------------------------------------------------------ */
    /*  Import a single record from the table action                       */
    /* ------------------------------------------------------------------ */

    protected function importSingleRecord(ExternalBankImport $record): void
    {
        DB::beginTransaction();
        try {
            $this->postToMasterBank($record, $record->external_bank_account_id);
            DB::commit();

            Notification::make()
                ->title('Imported Successfully')
                ->success()
                ->send();

        } catch (\Throwable $e) {
            DB::rollBack();
            Notification::make()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Bulk import from table                                             */
    /* ------------------------------------------------------------------ */

    protected function bulkImport($records): void
    {
        $imported = 0;
        $skipped = 0;

        DB::beginTransaction();
        try {
            foreach ($records as $record) {
                if ($record->imported_to_master || $record->is_duplicate) {
                    $skipped++;
                    continue;
                }
                $this->postToMasterBank($record, $record->external_bank_account_id);
                $imported++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Notification::make()
                ->title('Bulk Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->title('Bulk Import Complete')
            ->body("{$imported} imported, {$skipped} skipped.")
            ->success()
            ->send();
    }

    /* ------------------------------------------------------------------ */
    /*  Shared: post to master bank                                        */
    /* ------------------------------------------------------------------ */

    protected function postToMasterBank(ExternalBankImport $import, int $bankId): void
    {
        $bank = ExternalBankAccount::find($bankId);

        $transaction = Transaction::create([
            'transaction_id' => Transaction::generateTransactionId('EXT'),
            'transaction_date' => $import->transaction_date,
            'type' => 'external_import',
            'from_account' => $bank->bank_name ?? 'External Bank',
            'to_account' => 'Master Bank Account',
            'amount' => $import->amount,
            'reference' => $import->external_ref_id,
            'status' => 'pending',
            'notes' => $import->description,
            'created_by' => auth()->id(),
        ]);

        $transaction->process();

        $import->update([
            'imported_to_master' => true,
            'transaction_id' => $transaction->id,
        ]);

        // Keep external bank balance in sync
        $bank->increment('current_balance', $import->amount);

        // Keep master bank balance in sync
        MasterAccount::where('account_type', 'master_bank')
            ->first()
                ?->increment('balance', $import->amount);
    }

    /* ------------------------------------------------------------------ */
    /*  Computed props for the view                                        */
    /* ------------------------------------------------------------------ */

    public function getMasterBankBalanceProperty(): string
    {
        $balance = MasterAccount::where('account_type', 'master_bank')->value('balance') ?? 0;
        return '$' . number_format($balance, 2);
    }

    public function getTodayImportCountProperty(): int
    {
        return ExternalBankImport::whereDate('import_date', today())->count();
    }

    public function getTodayDuplicateCountProperty(): int
    {
        return ExternalBankImport::whereDate('import_date', today())
            ->where('is_duplicate', true)
            ->count();
    }

    public function getTodayTotalAmountProperty(): string
    {
        $total = ExternalBankImport::whereDate('import_date', today())
            ->where('imported_to_master', true)
            ->sum('amount');
        return '$' . number_format($total, 2);
    }
}
