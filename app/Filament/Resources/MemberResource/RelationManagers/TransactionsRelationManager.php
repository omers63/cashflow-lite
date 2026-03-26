<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Filament\Resources\TransactionResource;
use App\Filament\Support\CollectionObligationColumns;
use App\Filament\Support\TransactionDeleteActionConfigurator;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    /**
     * Required so Filament authorizes without calling getTable() while the table property
     * is still uninitialized (InteractsWithRelationshipTable::getAuthorizationResponse).
     */
    protected static ?string $relatedResource = TransactionResource::class;

    protected static ?string $title = 'Transactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-path';

    #[On('refreshTransactions')]
    public function refresh(): void
    {
        // Filament caches table rows and per-record cell state (HasCellState) by primary key only.
        // After collection_is_late / obligation fields change, stale "On time / late" badges persist
        // until both caches are cleared.
        $this->flushCachedTableRecords();
        foreach ($this->getTable()->getColumns() as $column) {
            $column->clearCachedState();
        }
    }

    public function table(Table $table): Table
    {
        // Summarizer callbacks must not call $this->getTable() — Filament sets RelationManager::$table
        // after configureTable(), so getTable() during column setup triggers "accessed before initialization".
        $manager = $this;

        $transactionUrlWithMemberContext = function (string $page, Transaction $record): string {
            $url = TransactionResource::getUrl($page, ['record' => $record]);
            $query = http_build_query(['member_context' => (int) $this->getOwnerRecord()->getKey()]);

            return $url.(str_contains($url, '?') ? '&' : '?').$query;
        };

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->date('M d, Y')
                    ->sortable(),

                ...CollectionObligationColumns::forTransactionRecord(),

                Tables\Columns\TextColumn::make('target_account')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', ucfirst($state))),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'external_import',
                        'success' => 'contribution',
                        'warning' => 'loan_repayment',
                        'danger' => 'loan_disbursement',
                        'info' => 'master_to_user_bank',
                        'secondary' => 'adjustment',
                        'gray' => ['allocation_to_dependant', 'allocation_from_parent', 'debit', 'credit'],
                    ])
                    ->formatStateUsing(fn (?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : ''),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->getStateUsing(function (Transaction $record) {
                        $amount = (float) $record->amount;

                        // Debit = Negative, Credit = Positive
                        return $record->isCredit() ? $amount : -$amount;
                    })
                    ->formatStateUsing(fn ($state) => $state >= 0
                        ? '$'.number_format((float) $state, 2)
                        : '-$'.number_format(abs((float) $state), 2))
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('amount', $direction))
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Net')
                            // modifyQueryUsing must be set so hasQueryModification() returns true,
                            // otherwise Filament pre-computes SUM(amount) in SQL via selectedState
                            // and the using() callback is never reached.
                            ->query(fn ($q) => $q)
                            ->using(function (string $attribute, $query) use ($manager) {
                                $sumFilteredRows = function () use ($query) {
                                    $records = $query->get();

                                    return $records->sum(function ($row) {
                                        $amount = (float) (is_object($row) ? ($row->amount ?? 0) : ($row['amount'] ?? 0));
                                        $type = is_object($row) ? ($row->type ?? '') : ($row['type'] ?? '');
                                        $debitTypes = ['allocation_to_dependant'];

                                        return in_array($type, $debitTypes, true) ? -$amount : $amount;
                                    });
                                };

                                // "All" tab: net = member's bank account balance.
                                $activeTab = $manager->activeTab ?? 'all';
                                if ($activeTab === 'all') {
                                    $owner = $manager->getOwnerRecord();
                                    $owner->refresh();

                                    return (float) $owner->bank_account_balance;
                                }

                                // Any other tab: net = sum of transactions of that type (with sign logic).
                                return (float) $sumFilteredRows();
                            })
                            ->formatStateUsing(fn ($state) => '$'.number_format((float) $state, 2)),
                    ]),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'complete',
                        'danger' => 'failed',
                        'secondary' => 'reversed',
                    ]),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'external_import' => 'External Bank Import',
                        'master_to_user_bank' => 'Master to User Bank',
                        'contribution' => 'Contribution',
                        'loan_repayment' => 'Loan Repayment',
                        'loan_disbursement' => 'Loan Disbursement',
                        'allocation_to_dependant' => 'Allocation to Dependant',
                        'allocation_from_parent' => 'Allocation from Parent',
                        'adjustment' => 'Adjustment',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'complete' => 'Complete',
                        'failed' => 'Failed',
                        'reversed' => 'Reversed',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('target_account')
                    ->options([
                        'master_bank' => 'Master Bank',
                        'master_fund' => 'Master Fund',
                        'user_bank' => 'User Bank',
                        'user_fund' => 'User Fund',
                        'external_bank' => 'External Bank',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('transaction_date')
                    ->label('Transaction Date')
                    ->schema([
                        Forms\Components\DatePicker::make('from')
                            ->label('From'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['to'] ?? null, fn ($q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['from'])) {
                            $indicators[] = 'From: '.\Carbon\Carbon::parse($data['from'])->toFormattedDateString();
                        }
                        if (! empty($data['to'])) {
                            $indicators[] = 'To: '.\Carbon\Carbon::parse($data['to'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View')
                        ->url(fn (Transaction $record) => $transactionUrlWithMemberContext('view', $record)),
                    Actions\Action::make('edit')
                        ->label('Edit')
                        ->tooltip('Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Transaction $record) => $transactionUrlWithMemberContext('edit', $record)),
                    Actions\Action::make('unassign')
                        ->label('Unassign Member')
                        ->tooltip('Unassign Member')
                        ->icon('heroicon-o-user-minus')
                        ->requiresConfirmation()
                        ->modalHeading('Unassign transaction from member')
                        ->modalDescription('This transaction will be unassigned from this member. The record is not deleted. For external imports, the member\'s bank balance will be reduced by the transaction amount.')
                        ->action(function (Transaction $record): void {
                            $owner = $this->getOwnerRecord();
                            $ownerUserId = (int) $owner->user_id;
                            if ((int) $record->user_id !== $ownerUserId) {
                                return;
                            }
                            DB::transaction(function () use ($record): void {
                                if ($record->type === 'external_import' && $record->user) {
                                    $record->user->debitBankAccount((float) $record->amount);
                                }
                                $record->update(['user_id' => null]);
                            });
                            $owner->refresh();
                            $this->dispatch('refreshMemberRecord', memberId: $owner->getKey());
                            Notification::make()
                                ->title('Transaction unassigned')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record) => (int) $record->user_id === (int) $this->getOwnerRecord()?->user_id),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->selectable(true)
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('unassign_from_user')
                        ->label('Unassign Member')
                        ->icon('heroicon-o-user-minus')
                        ->color('gray')
                        ->requiresConfirmation()
                        ->modalHeading('Unassign transactions from member')
                        ->modalDescription('Selected transactions will be unassigned from this member. The transaction records are not deleted. For external imports, the member\'s bank balance will be reduced by the unassigned amounts.')
                        ->action(function (Collection $records): void {
                            $owner = $this->getOwnerRecord();
                            $ownerUserId = (int) $owner->user_id;
                            $count = 0;
                            DB::transaction(function () use ($records, $ownerUserId, &$count): void {
                                foreach ($records as $record) {
                                    if ((int) $record->user_id !== $ownerUserId) {
                                        continue;
                                    }
                                    if ($record->type === 'external_import' && $record->user) {
                                        $record->user->debitBankAccount((float) $record->amount);
                                    }
                                    $record->update(['user_id' => null]);
                                    $count++;
                                }
                            });
                            $owner->refresh();
                            $this->dispatch('refreshMemberRecord', memberId: $owner->getKey());
                            Notification::make()
                                ->title('Unassigned from member')
                                ->body($count.' transaction(s) unassigned.')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    TransactionDeleteActionConfigurator::configureBulkDelete(
                        Actions\DeleteBulkAction::make()->authorize(fn () => true),
                    ),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }

    /**
     * Split the member transactions table into tabs by transaction type.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('')
                ->icon('heroicon-o-bars-3')
                ->extraAttributes(['title' => 'All transactions']),

            'contribution' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'contribution'))
                ->icon('heroicon-o-arrow-up-circle')
                ->extraAttributes(['title' => 'Contributions']),

            'loan_repayment' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'loan_repayment'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->extraAttributes(['title' => 'Loan repayments']),

            'loan_disbursement' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'loan_disbursement'))
                ->icon('heroicon-o-banknotes')
                ->extraAttributes(['title' => 'Loan disbursements']),

            'allocation_to_dependant' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'allocation_to_dependant'))
                ->icon('heroicon-o-user-group')
                ->extraAttributes(['title' => 'Allocations to dependants']),

            'allocation_from_parent' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'allocation_from_parent'))
                ->icon('heroicon-o-user-group')
                ->extraAttributes(['title' => 'Allocations from parent']),

            'external_import' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'external_import'))
                ->icon('heroicon-o-arrow-down-tray')
                ->extraAttributes(['title' => 'External imports']),

            'master_to_user_bank' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'master_to_user_bank'))
                ->icon('heroicon-o-building-library')
                ->extraAttributes(['title' => 'Master to member bank']),

            'adjustment' => Tab::make('')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'adjustment'))
                ->icon('heroicon-o-adjustments-horizontal')
                ->extraAttributes(['title' => 'Adjustments']),
        ];
    }
}
