<?php

namespace App\Filament\Pages;

use App\Models\ExternalBankAccount;
use App\Models\ExternalBankImport;
use App\Models\MasterAccount;
use App\Models\Setting;
use App\Services\ExternalBankExcelParser;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms;
use Filament\Infolists\Components\TextEntry as InfolistTextEntry;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Components;
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
    protected static ?string $navigationLabel = 'Import Bank Transactions';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $title = 'Import External Bank Transactions';

    public ?array $data = [];
    public bool $showPreview = false;
    public array $previewRows = [];
    public int $duplicateCount = 0;
    public int $newCount = 0;
    public ?int $selectedBankId = null;

    /** When true, the Manual Entry bank select is hidden because the bank was defaulted from the URL. */
    public bool $bankDefaultedFromUrl = false;

    public function mount(): void
    {
        $bankId = request()->query('bank');
        $bankId = is_numeric($bankId) ? (int) $bankId : null;
        if ($bankId && ExternalBankAccount::where('id', $bankId)->exists()) {
            $this->selectedBankId = $bankId;
            $this->bankDefaultedFromUrl = true;
        }

        $this->form->fill(array_filter([
            'manual_entries' => [
                ['transaction_date' => now()->format('Y-m-d H:i:s'), 'amount' => null, 'external_ref_id' => null],
            ],
            'manual_bank_id' => $this->selectedBankId,
            'external_bank_account_id_csv' => $this->selectedBankId,
        ]));
    }

    protected function getSelectedManualBankId(): ?int
    {
        $id = $this->data['manual_bank_id'] ?? $this->selectedBankId;

        return $id ? (int) $id : null;
    }

    protected function getSelectedManualBankLabel(): ?string
    {
        $id = $this->getSelectedManualBankId();
        if (! $id) {
            return null;
        }
        $bank = ExternalBankAccount::find($id);

        return $bank ? "{$bank->bank_name} – ****{$bank->account_number}" : null;
    }

    protected function hasDefaultedBankFromUrl(): bool
    {
        $bankId = request()->query('bank');

        return is_numeric($bankId) && ExternalBankAccount::where('id', (int) $bankId)->exists();
    }

    protected function generateManualRefId(): string
    {
        return 'MAN-' . now()->format('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Summary')
                    ->description('Current import statistics and master bank balance')
                    ->schema([
                        Components\Grid::make(4)
                            ->schema([
                                InfolistTextEntry::make('master_bank_balance')
                                    ->label('Master Bank Balance')
                                    ->state(fn() => $this->masterBankBalance)
                                    ->icon('heroicon-o-banknotes')
                                    ->helperText(fn() => 'External banks total: ' . $this->externalBanksTotal . '. Per reconciliation, Master Bank should equal this sum.')
                                    ->columnSpan(1),

                                InfolistTextEntry::make('today_import_count')
                                    ->label('Imported Today')
                                    ->state(fn() => (string) $this->todayImportCount)
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->helperText('transactions processed')
                                    ->columnSpan(1),

                                InfolistTextEntry::make('today_duplicate_count')
                                    ->label('Duplicates Today')
                                    ->state(fn() => (string) $this->todayDuplicateCount)
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->helperText('already in system')
                                    ->columnSpan(1),

                                InfolistTextEntry::make('today_total_amount')
                                    ->label('Total Amount Today')
                                    ->state(fn() => $this->todayTotalAmount)
                                    ->icon('heroicon-o-currency-dollar')
                                    ->helperText('successfully imported')
                                    ->columnSpan(1),
                            ]),
                    ])
                    ->collapsible(false)
                    ->columnSpanFull(),

                Components\Tabs::make('Import Method')
                    ->tabs([
                        Components\Tabs\Tab::make('Manual Entry')
                            ->icon('heroicon-o-pencil-square')
                            ->schema([
                                Components\Section::make('Manual entry – add one or more transactions')
                                    ->description('Select the bank account once; then add as many transaction rows as you need.')
                                    ->schema([
                                        InfolistTextEntry::make('manual_bank_display')
                                            ->label('Importing to')
                                            ->state(fn () => $this->getSelectedManualBankLabel())
                                            ->icon('heroicon-o-building-library')
                                            ->visible(fn () => (bool) $this->getSelectedManualBankId()),

                                        Forms\Components\Select::make('manual_bank_id')
                                            ->label('Bank Account')
                                            ->options(
                                                ExternalBankAccount::active()
                                                    ->get()
                                                    ->mapWithKeys(fn($b) => [
                                                        $b->id => "{$b->bank_name} - ****{$b->account_number}",
                                                    ])
                                            )
                                            ->searchable()
                                            ->reactive()
                                            ->afterStateUpdated(fn($state) => $this->selectedBankId = $state)
                                            ->helperText('External bank account for all transactions below')
                                            ->prefixIcon('heroicon-o-building-library')
                                            ->columnSpanFull()
                                            ->visible(fn () => ! $this->bankDefaultedFromUrl),

                                        Forms\Components\Repeater::make('manual_entries')
                                            ->label('Transactions')
                                            ->schema([
                                                Forms\Components\Hidden::make('external_ref_id')
                                                    ->default(fn () => $this->generateManualRefId())
                                                    ->dehydrated(),

                                                Components\Grid::make(4)
                                                    ->schema([
                                                        Forms\Components\DateTimePicker::make('transaction_date')
                                                            ->label('Date')
                                                            ->default(now())
                                                            ->native(false)
                                                            ->prefixIcon('heroicon-o-calendar'),

                                                        Forms\Components\TextInput::make('amount')
                                                            ->label('Amount')
                                                            ->numeric()
                                                            ->prefix('$')
                                                            ->step(0.01)
                                                            ->minValue(0.01)
                                                            ->prefixIcon('heroicon-o-currency-dollar'),

                                                        Forms\Components\TextInput::make('description')
                                                            ->label('Description')
                                                            ->maxLength(500)
                                                            ->placeholder('Transaction description'),

                                                        Forms\Components\TextInput::make('notes')
                                                            ->label('Notes')
                                                            ->maxLength(500)
                                                            ->placeholder('Internal notes (optional)'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->defaultItems(1)
                                            ->addActionLabel('Add another transaction')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1),
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
                                            ->searchable()
                                            ->helperText('Select the external bank account for these transactions')
                                            ->prefixIcon('heroicon-o-building-library'),

                                        Forms\Components\FileUpload::make('csv_file')
                                            ->label('Upload file (.xls or .xlsx)')
                                            ->acceptedFileTypes([
                                                'application/vnd.ms-excel',
                                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                                'text/csv',
                                                'text/plain',
                                            ])
                                            ->maxSize(10240)
                                            ->storeFiles(false)
                                            ->columnSpanFull()
                                            ->helperText('Expected columns: Date (B), Transaction Type (C), Description (D&E), Amount (F), Balance (H). Data from row 6. Max 10MB.'),
                                    ])
                                    ->columns(1),
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
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date')
                            ->native(false),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date')
                            ->native(false),
                    ])
                    ->query(
                        fn($query, array $data) => $query
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
                Actions\ActionGroup::make([
                    Actions\Action::make('import_now')
                        ->label('Import to Master')
                        ->tooltip('Import to Master')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->modalHeading('Import Transaction')
                        ->modalDescription(fn($record) => "Import transaction of \${$record->amount} to Master Bank Account?")
                        ->action(fn(ExternalBankImport $record) => $this->importSingleRecord($record))
                        ->visible(fn(ExternalBankImport $record) => !$record->imported_to_master && !$record->is_duplicate),

                    Actions\DeleteAction::make()
                        ->label('Delete')
                        ->tooltip('Delete'),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('import_selected')
                        ->label('Import Selected')
                        ->tooltip('Import Selected')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Import')
                        ->modalDescription('Import all selected transactions to Master Bank Account?')
                        ->action(fn($records) => $this->bulkImport($records))
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make()
                        ->label('Delete Selected')
                        ->tooltip('Delete Selected'),
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
                ->action(fn() => $this->previewImport())
                ->keyBindings(['ctrl+p', 'cmd+p']),

            Action::make('import')
                ->label('Import Transaction')
                ->icon('heroicon-o-check-circle')
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
        $data = $this->form->getState();

        $file = $data['csv_file'] ?? null;
        $bankIdCsv = $data['external_bank_account_id_csv'] ?? null;
        $manualBankId = $data['manual_bank_id'] ?? $this->selectedBankId;
        $entries = $data['manual_entries'] ?? [];

        // Ensure manual entries have external_ref_id for validation
        foreach ($entries as $i => $row) {
            if (empty($row['external_ref_id'] ?? '')) {
                $entries[$i]['external_ref_id'] = $this->generateManualRefId();
            }
        }
        $validEntries = array_values(array_filter($entries, function ($row) {
            return !empty($row['external_ref_id'] ?? '') && isset($row['amount']) && (float) ($row['amount'] ?? 0) > 0 && !empty($row['transaction_date'] ?? '');
        }));

        // Manual entry flow first: if we have a bank and valid manual rows, preview those
        if ($manualBankId && count($validEntries) > 0) {
            $this->previewManualEntry((int) $manualBankId, $validEntries);
            $this->showPreview = true;
            return;
        }

        // CSV flow: require file and CSV bank selected
        if ($file && $bankIdCsv) {
            $path = $file instanceof TemporaryUploadedFile ? $file->getRealPath() : (is_string($file) ? $file : null);
            if (!$path || !is_readable($path)) {
                Notification::make()
                    ->title('Invalid file')
                    ->body('Please upload a valid .xls or .xlsx file.')
                    ->warning()
                    ->send();
                return;
            }
            $this->previewExcelEntry($path, (int) $bankIdCsv);
            $this->showPreview = true;
            return;
        }

        if ($bankIdCsv && !$file) {
            Notification::make()
                ->title('File required')
                ->body('Please upload an .xls or .xlsx file to preview.')
                ->warning()
                ->send();
            return;
        }

        Notification::make()
            ->title('Manual entry required')
            ->body('Select a bank account and add at least one transaction with Amount and Date.')
            ->warning()
            ->send();
    }

    protected function previewManualEntry(int $bankId, array $entries): void
    {
        $bank = ExternalBankAccount::find($bankId);
        $bankName = $bank->bank_name ?? '—';
        $previewRows = [];
        $duplicateCount = 0;
        $newCount = 0;

        foreach ($entries as $row) {
            $isDuplicate = ExternalBankImport::where('external_bank_account_id', $bankId)
                ->where('external_ref_id', $row['external_ref_id'])
                ->exists();

            $previewRows[] = [
                'transaction_date' => $row['transaction_date'],
                'external_ref_id' => $row['external_ref_id'],
                'amount' => $row['amount'],
                'description' => $row['description'] ?? '',
                'bank_name' => $bankName,
                'is_duplicate' => $isDuplicate,
            ];

            if ($isDuplicate) {
                $duplicateCount++;
            } else {
                $newCount++;
            }
        }

        $this->previewRows = $previewRows;
        $this->duplicateCount = $duplicateCount;
        $this->newCount = $newCount;

        Notification::make()
            ->title('Preview ready')
            ->body(count($previewRows) . ' transaction(s). ' . $newCount . ' new, ' . $duplicateCount . ' duplicate(s).')
            ->success()
            ->send();
    }

    protected function previewExcelEntry(string $path, int $bankId): void
    {
        $parser = new ExternalBankExcelParser();
        $bank = ExternalBankAccount::find($bankId);
        $bankName = $bank->bank_name ?? '—';

        try {
            $parsed = $parser->parse($path);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not read file')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        $previewRows = [];
        $duplicateCount = 0;
        $newCount = 0;

        foreach ($parsed as $row) {
            $amount = (float) $row['amount'];
            if ($amount === 0.0) {
                continue;
            }
            $transactionDate = $row['transaction_date'];
            $description = $row['description'] ?? '';
            $externalRefId = $this->makeExcelExternalRefId($bankId, $transactionDate, $description, $amount, $row['row_index']);

            $isDuplicate = ExternalBankImport::where('external_bank_account_id', $bankId)
                ->where('external_ref_id', $externalRefId)
                ->exists();

            $previewRows[] = [
                'transaction_date' => $transactionDate,
                'external_ref_id' => $externalRefId,
                'amount' => $amount,
                'description' => $description,
                'bank_name' => $bankName,
                'is_duplicate' => $isDuplicate,
            ];

            if ($isDuplicate) {
                $duplicateCount++;
            } else {
                $newCount++;
            }
        }

        $this->previewRows = $previewRows;
        $this->duplicateCount = $duplicateCount;
        $this->newCount = $newCount;

        Notification::make()
            ->title('Preview ready')
            ->body(count($previewRows) . ' transaction(s) found. ' . $newCount . ' new, ' . $duplicateCount . ' duplicate(s).')
            ->success()
            ->send();
    }

    private function makeExcelExternalRefId(int $bankId, $transactionDate, string $description, float $amount, int $rowIndex): string
    {
        $dateStr = $transactionDate instanceof \Carbon\Carbon
            ? $transactionDate->format('Y-m-d')
            : \Carbon\Carbon::parse($transactionDate)->format('Y-m-d');
        $key = $dateStr . '|' . $description . '|' . number_format($amount, 2) . '|' . $rowIndex;
        return 'XLS-' . $bankId . '-R' . $rowIndex . '-' . substr(md5($key), 0, 12);
    }

    public function processImport(): void
    {
        $data = $this->form->getState();
        $file = $data['csv_file'] ?? null;
        $bankIdCsv = $data['external_bank_account_id_csv'] ?? null;

        // CSV flow
        if ($file && $bankIdCsv) {
            $path = $file instanceof TemporaryUploadedFile ? $file->getRealPath() : (is_string($file) ? $file : null);
            if ($path && is_readable($path)) {
                $this->importExcelEntry($path, (int) $bankIdCsv);
                return;
            }
        }

        // Manual entry flow
        $manualBankId = $data['manual_bank_id'] ?? $this->selectedBankId;
        $entries = $data['manual_entries'] ?? [];
        foreach ($entries as $i => $row) {
            if (empty($row['external_ref_id'] ?? '')) {
                $entries[$i]['external_ref_id'] = $this->generateManualRefId();
            }
        }
        $validEntries = array_values(array_filter($entries, function ($row) {
            return !empty($row['external_ref_id'] ?? '') && isset($row['amount']) && (float) ($row['amount'] ?? 0) > 0 && !empty($row['transaction_date'] ?? '');
        }));

        if ($manualBankId && count($validEntries) > 0) {
            $this->importManualEntry((int) $manualBankId, $validEntries);
        }
    }

    protected function importManualEntry(int $bankId, array $entries): void
    {
        DB::beginTransaction();
        try {
            $imported = 0;
            $duplicates = 0;

            foreach ($entries as $row) {
                $isDuplicate = ExternalBankImport::where('external_bank_account_id', $bankId)
                    ->where('external_ref_id', $row['external_ref_id'])
                    ->exists();

                $import = ExternalBankImport::create([
                    'external_bank_account_id' => $bankId,
                    'import_date' => now(),
                    'transaction_date' => $row['transaction_date'],
                    'external_ref_id' => $row['external_ref_id'],
                    'amount' => $row['amount'],
                    'description' => $row['description'] ?? null,
                    'is_duplicate' => $isDuplicate,
                    'imported_to_master' => false,
                    'notes' => $row['notes'] ?? null,
                    'imported_by' => auth()->id(),
                ]);

                if (! $isDuplicate) {
                    $imported++;
                }
            }

            DB::commit();

            $this->showPreview = false;
            $this->previewRows = [];
            $this->newCount = 0;
            $this->duplicateCount = 0;
            $this->form->fill([
                'manual_bank_id' => $bankId,
                'manual_entries' => [
                    [
                        'transaction_date' => now()->format('Y-m-d H:i:s'),
                        'amount' => null,
                        'external_ref_id' => $this->generateManualRefId(),
                        'description' => null,
                        'notes' => null,
                    ],
                ],
            ]);

            Notification::make()
                ->title('Import complete')
                ->body($this->getImportSuccessBody($imported, $duplicates))
                ->success()
                ->duration(5000)
                ->send();
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

    protected function getImportSuccessBody(int $imported, int $duplicates): string
    {
        $total = $imported + $duplicates;
        $custom = Setting::renderTemplate('import_success_message', [
            'imported_count' => $imported,
            'duplicate_count' => $duplicates,
            'total_count' => $total,
        ]);
        if ($custom !== '') {
            return $custom;
        }

        return $imported . ' transaction(s) added.'
            . ($duplicates > 0 ? ' ' . $duplicates . ' duplicate(s) skipped.' : '')
            . ' Use "Import to Master" on the bank account\'s Transactions tab to post to Master Bank.';
    }

    protected function importExcelEntry(string $path, int $bankId): void
    {
        $parser = new ExternalBankExcelParser();
        try {
            $parsed = $parser->parse($path);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not read file')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }

        DB::beginTransaction();
        try {
            $imported = 0;
            $duplicates = 0;

            foreach ($parsed as $row) {
                $amount = (float) $row['amount'];
                if ($amount === 0.0) {
                    continue;
                }
                $transactionDate = $row['transaction_date'];
                $description = $row['description'] ?? '';
                $externalRefId = $this->makeExcelExternalRefId($bankId, $transactionDate, $description, $amount, $row['row_index']);

                $isDuplicate = ExternalBankImport::where('external_bank_account_id', $bankId)
                    ->where('external_ref_id', $externalRefId)
                    ->exists();

                if ($isDuplicate) {
                    $duplicates++;
                    continue;
                }

                ExternalBankImport::create([
                    'external_bank_account_id' => $bankId,
                    'import_date' => now(),
                    'transaction_date' => $transactionDate,
                    'external_ref_id' => $externalRefId,
                    'amount' => $amount,
                    'description' => $description ?: null,
                    'is_duplicate' => false,
                    'imported_to_master' => false,
                    'notes' => null,
                    'imported_by' => auth()->id(),
                ]);
                $imported++;
            }

            DB::commit();

            $this->showPreview = false;
            $this->previewRows = [];
            $this->newCount = 0;
            $this->duplicateCount = 0;
            $this->form->fill(['external_bank_account_id_csv' => $bankId]);

            Notification::make()
                ->title('Import complete')
                ->body($this->getImportSuccessBody($imported, $duplicates))
                ->success()
                ->duration(5000)
                ->send();
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
            $record->postToMasterBank();
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
                $record->postToMasterBank();
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

    public function getMasterBankBalanceProperty(): string
    {
        $balance = MasterAccount::where('account_type', 'master_bank')->value('balance') ?? 0;
        return '$' . number_format($balance, 2);
    }

    public function getExternalBanksTotalProperty(): string
    {
        $total = ExternalBankAccount::active()->sum('current_balance');
        return '$' . number_format($total, 2);
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
