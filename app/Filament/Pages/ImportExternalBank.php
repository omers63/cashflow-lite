<?php

namespace App\Filament\Pages;

use App\Models\ExternalBankAccount;
use App\Models\ExternalBankImport;
use App\Models\MasterAccount;
use App\Models\Transaction;
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

    public ?array $data = [];
    public bool $showPreview = false;
    public array $previewRows = [];
    public int $duplicateCount = 0;
    public int $newCount = 0;
    public ?int $selectedBankId = null;

    public function mount(): void
    {
        $this->form->fill([
            'transaction_date' => now(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Tabs::make('Import Method')
                    ->tabs([
                        Components\Tabs\Tab::make('Manual Entry')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Components\Section::make()
                                    ->schema([
                                        Forms\Components\Select::make('external_bank_account_id')
                                            ->label('Bank Account')
                                            ->options(
                                                ExternalBankAccount::active()
                                                    ->get()
                                                    ->mapWithKeys(fn($b) => [
                                                        $b->id => "{$b->bank_name} - ****{$b->account_number}",
                                                    ])
                                            )
                                            ->required()
                                            ->searchable()
                                            ->reactive()
                                            ->afterStateUpdated(fn($state) => $this->selectedBankId = $state)
                                            ->helperText('Select the external bank account for this transaction')
                                            ->prefixIcon('heroicon-o-building-library'),

                                        Forms\Components\DateTimePicker::make('transaction_date')
                                            ->label('Transaction Date')
                                            ->required()
                                            ->default(now())
                                            ->native(false)
                                            ->prefixIcon('heroicon-o-calendar'),

                                        Forms\Components\TextInput::make('external_ref_id')
                                            ->label('Reference ID')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ExternalBankImport::class, 'external_ref_id', ignoreRecord: true)
                                            ->helperText('Bank transaction reference number')
                                            ->prefixIcon('heroicon-o-hashtag'),

                                        Forms\Components\TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->prefix('$')
                                            ->required()
                                            ->step(0.01)
                                            ->minValue(0.01)
                                            ->prefixIcon('heroicon-o-currency-dollar'),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(2)
                                            ->maxLength(500)
                                            ->columnSpanFull()
                                            ->placeholder('Transaction description from bank statement'),

                                        Forms\Components\Textarea::make('notes')
                                            ->label('Internal Notes')
                                            ->rows(2)
                                            ->maxLength(500)
                                            ->columnSpanFull()
                                            ->placeholder('Add any internal notes or comments'),
                                    ])
                                    ->columns(2),
                            ]),

                        Components\Tabs\Tab::make('CSV Upload')
                            ->icon('heroicon-o-document-arrow-up')
                            ->schema([
                                Components\Section::make()
                                    ->schema([
                                        Forms\Components\Select::make('external_bank_account_id_csv')
                                            ->label('Bank Account')
                                            ->options(
                                                ExternalBankAccount::active()
                                                    ->get()
                                                    ->mapWithKeys(fn($b) => [
                                                        $b->id => "{$b->bank_name} - ****{$b->account_number}",
                                                    ])
                                            )
                                            ->required()
                                            ->searchable()
                                            ->helperText('Select the external bank account for these transactions')
                                            ->prefixIcon('heroicon-o-building-library'),

                                        Forms\Components\FileUpload::make('csv_file')
                                            ->label('Upload CSV File')
                                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                                            ->maxSize(10240)
                                            ->storeFiles(false)
                                            ->columnSpanFull()
                                            ->helperText('Maximum file size: 10MB'),
                                    ])
                                    ->columns(1),

                                Components\Section::make('CSV Format Guide')
                                    ->schema([
                                        Forms\Components\Placeholder::make('format_info')
                                            ->content(fn() => view('filament.components.csv-format-guide')),
                                    ])
                                    ->collapsed()
                                    ->compact(),
                            ]),
                    ])
                    ->activeTab(1)
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

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
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->description(fn($record) => $record->import_date->diffForHumans()),

                Tables\Columns\TextColumn::make('externalBankAccount.bank_name')
                    ->label('Bank')
                    ->searchable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Transaction Date')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_ref_id')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Reference copied!')
                    ->copyMessageDuration(1500)
                    ->icon('heroicon-o-hashtag'),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->tooltip(fn($record) => $record->description)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_duplicate')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->size('sm')
                    ->tooltip(fn($record) => $record->is_duplicate ? 'Duplicate transaction' : 'Unique transaction'),

                Tables\Columns\IconColumn::make('imported_to_master')
                    ->label('Imported')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->size('sm')
                    ->tooltip(fn($record) => $record->imported_to_master ? 'Imported to Master' : 'Pending import'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('external_bank_account_id')
                    ->label('Bank Account')
                    ->relationship('externalBankAccount', 'bank_name')
                    ->multiple()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_duplicate')
                    ->label('Duplicate Status')
                    ->placeholder('All transactions')
                    ->trueLabel('Duplicates only')
                    ->falseLabel('Unique only'),

                Tables\Filters\TernaryFilter::make('imported_to_master')
                    ->label('Import Status')
                    ->placeholder('All transactions')
                    ->trueLabel('Imported')
                    ->falseLabel('Not imported'),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date')
                            ->native(false),
                    ])
                    ->query(fn($query, array $data) => $query
                        ->when($data['from'], fn($q) => $q->whereDate('import_date', '>=', $data['from']))
                        ->when($data['to'], fn($q) => $q->whereDate('import_date', '<=', $data['to']))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'From: ' . \Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = 'To: ' . \Carbon\Carbon::parse($data['to'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->recordActions([
                Actions\Action::make('import_now')
                    ->label('Import')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Import Transaction')
                    ->modalDescription(fn($record) => "Import transaction of \${$record->amount} to Master Bank Account?")
                    ->action(fn(ExternalBankImport $record) => $this->importSingleRecord($record))
                    ->visible(fn(ExternalBankImport $record) => !$record->imported_to_master && !$record->is_duplicate),

                Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('import_selected')
                        ->label('Import Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Import')
                        ->modalDescription('Import all selected transactions to Master Bank Account?')
                        ->action(fn($records) => $this->bulkImport($records))
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('import_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s')
            ->emptyStateHeading('No transactions imported yet')
            ->emptyStateDescription('Start by importing your first transaction using the form above.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->action(fn() => $this->previewImport())
                ->keyBindings(['ctrl+p', 'cmd+p']),

            Action::make('import')
                ->label('Import Transaction')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Import')
                ->modalDescription(fn() => $this->duplicateCount > 0
                    ? "Found {$this->duplicateCount} duplicate(s). Only {$this->newCount} new transaction(s) will be imported."
                    : "Ready to import {$this->newCount} transaction(s).")
                ->modalIcon('heroicon-o-arrow-down-tray')
                ->action(fn() => $this->processImport())
                ->visible(fn() => $this->showPreview && $this->newCount > 0)
                ->keyBindings(['ctrl+enter', 'cmd+enter']),
        ];
    }

    public function previewImport(): void
    {
        $this->validate();
        $data = $this->form->getState();

        if (!isset($data['external_bank_account_id'])) {
            Notification::make()
                ->title('Bank Account Required')
                ->body('Please select a bank account first.')
                ->warning()
                ->send();
            return;
        }

        $this->previewManualEntry($data);
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

        if ($isDuplicate) {
            Notification::make()
                ->title('Duplicate Detected')
                ->body('This transaction already exists in the system.')
                ->warning()
                ->send();
        } else {
            Notification::make()
                ->title('Preview Ready')
                ->body('Transaction validated and ready to import.')
                ->success()
                ->send();
        }
    }

    public function processImport(): void
    {
        $data = $this->form->getState();
        $this->importManualEntry($data);
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
                ->duration(5000)
                ->send();

            $this->showPreview = false;
            $this->previewRows = [];
            $this->newCount = 0;
            $this->duplicateCount = 0;
            $this->form->fill([
                'transaction_date' => now(),
                'external_bank_account_id' => $bankId,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Notification::make()
                ->title('Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->duration(10000)
                ->send();
        }
    }

    protected function importSingleRecord(ExternalBankImport $record): void
    {
        DB::beginTransaction();
        try {
            $this->postToMasterBank($record, $record->external_bank_account_id);
            DB::commit();

            Notification::make()
                ->title('Imported Successfully')
                ->body("Transaction of \${$record->amount} imported to Master Bank.")
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

            Notification::make()
                ->title('Bulk Import Complete')
                ->body("{$imported} transaction(s) imported" . ($skipped > 0 ? ", {$skipped} skipped" : "") . ".")
                ->success()
                ->send();

        } catch (\Throwable $e) {
            DB::rollBack();
            Notification::make()
                ->title('Bulk Import Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

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

        $bank->increment('current_balance', $import->amount);
        MasterAccount::where('account_type', 'master_bank')
            ->first()
            ?->increment('balance', $import->amount);
    }

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
