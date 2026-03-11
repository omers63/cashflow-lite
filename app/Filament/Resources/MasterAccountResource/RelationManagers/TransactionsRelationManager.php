<?php

namespace App\Filament\Resources\MasterAccountResource\RelationManagers;

use App\Models\Member;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-path';

    #[On('refreshMasterAccountRecord')]
    public function refreshTable(): void
    {
        $this->dispatch('$refresh');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('transaction_date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary' => 'external_import',
                        'info' => 'master_to_user_bank',
                        'success' => 'contribution',
                        'warning' => 'loan_repayment',
                        'danger' => 'loan_disbursement',
                        'secondary' => 'adjustment',
                    ])
                    ->formatStateUsing(fn(?string $state) => $state ? str_replace('_', ' ', ucfirst($state)) : ''),

                Tables\Columns\TextColumn::make('amount')
                    ->getStateUsing(function (Transaction $record): float {
                        // For adjustments, the amount is already signed (negative = debit)
                        if ($record->type === 'adjustment') {
                            return (float) $record->amount;
                        }
                        if ($this->getOwnerRecord()->account_type !== 'master_bank') {
                            return (float) $record->amount;
                        }
                        // Debits from master bank show as negative
                        $debitTypes = ['master_to_user_bank', 'loan_disbursement'];
                        return in_array($record->type, $debitTypes, true)
                            ? -(float) $record->amount
                            : (float) $record->amount;
                    })
                    ->formatStateUsing(function ($state): string {
                        return $state >= 0
                            ? '$' . number_format($state, 2)
                            : '-$' . number_format(abs($state), 2);
                    })
                    ->color(fn ($state): ?string => $state < 0 ? 'danger' : null)
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('amount', $direction))
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('Net')
                            ->query(fn ($q) => $q)
                            ->using(function (string $attribute, $query) {
                                $records = $query->get();
                                $debitTypes = ['master_to_user_bank', 'loan_disbursement'];
                                return $records->sum(function ($row) use ($debitTypes) {
                                    $amount = (float) $row->amount;
                                    return in_array($row->type, $debitTypes, true) ? -$amount : $amount;
                                });
                            })
                            ->formatStateUsing(fn ($state): string => ($state >= 0 ? '' : '-') . '$' . number_format(abs((float) $state), 2)),
                    ]),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Assigned to')
                    ->placeholder('—')
                    ->visible(fn (): bool => $this->getOwnerRecord()->account_type === 'master_bank'),

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
            ->recordClasses(function ($record) {
                // Adjustments use signed amount: negative = debit (red), positive = credit (green)
                if ($record->type === 'adjustment') {
                    return (float) $record->amount < 0
                        ? 'bg-danger-50 dark:bg-danger-950'
                        : 'bg-success-50 dark:bg-success-950';
                }
                $debitTypes = ['master_to_user_bank', 'loan_disbursement'];
                return in_array($record->type, $debitTypes, true)
                    ? 'bg-danger-50 dark:bg-danger-950'
                    : 'bg-success-50 dark:bg-success-950';
            })
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Transaction Type')
                    ->options([
                        'external_import' => 'External Bank Import',
                        'master_to_user_bank' => 'Master to User Bank',
                        'contribution' => 'Contribution',
                        'loan_repayment' => 'Loan Repayment',
                        'loan_disbursement' => 'Loan Disbursement',
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

                Tables\Filters\SelectFilter::make('from_account')
                    ->label('From account')
                    ->options(fn () => Transaction::query()->distinct()->orderBy('from_account')->pluck('from_account', 'from_account')->toArray())
                    ->searchable(),

                Tables\Filters\SelectFilter::make('to_account')
                    ->label('To account')
                    ->options(fn () => Transaction::query()->distinct()->orderBy('to_account')->pluck('to_account', 'to_account')->toArray())
                    ->searchable(),

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
                            ->when($data['from'], fn($q, $date) => $q->whereDate('transaction_date', '>=', $date))
                            ->when($data['to'], fn($q, $date) => $q->whereDate('transaction_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\Action::make('assign_user')
                        ->label('Post To Member')
                        ->tooltip('Post To Member')
                        ->icon('heroicon-o-user-plus')
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options(
                                fn () => Member::with('user')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [
                                        $m->id => $m->user
                                            ? "{$m->user->name} ({$m->user->user_code})"
                                            : "Member #{$m->id}",
                                    ])
                            )
                            ->searchable()
                            ->required(),
                    ])
                        ->action(function (Transaction $record, array $data): void {
                            DB::transaction(function () use ($record, $data): void {
                                $member = Member::find($data['member_id']);
                                if ($member) {
                                    $member->creditBankAccount((float) $record->amount);
                                    $record->update(['user_id' => $member->user_id]);
                                }
                            });
    
                            \Filament\Notifications\Notification::make()
                                ->title('Assigned to member')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record): bool => $this->getOwnerRecord()->account_type === 'master_bank'
                            && $record->type === 'external_import'
                            && ! $record->user_id),
    
                    Actions\Action::make('reassign_user')
                        ->label('Reassign to Member')
                        ->tooltip('Reassign to Member')
                        ->icon('heroicon-o-arrow-path')
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options(
                                fn () => Member::with('user')
                                    ->get()
                                    ->mapWithKeys(fn (Member $m) => [
                                        $m->id => $m->user
                                            ? "{$m->user->name} ({$m->user->user_code})"
                                            : "Member #{$m->id}",
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->default(fn (Transaction $record) => $record->user?->member?->id),
                    ])
                        ->action(function (Transaction $record, array $data): void {
                            DB::transaction(function () use ($record, $data): void {
                                $oldMember = $record->user?->member;
                                if ($oldMember) {
                                    $oldMember->debitBankAccount((float) $record->amount);
                                }
                                $newMember = Member::find($data['member_id']);
                                if ($newMember) {
                                    $newMember->creditBankAccount((float) $record->amount);
                                    $record->update(['user_id' => $newMember->user_id]);
                                }
                            });
    
                            \Filament\Notifications\Notification::make()
                                ->title('Reassigned to member')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record): bool => $this->getOwnerRecord()->account_type === 'master_bank'
                            && $record->type === 'external_import'
                            && (bool) $record->user_id),

                    Actions\Action::make('post_to_member')
                        ->label('Post To Member')
                        ->tooltip('Post To Member')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Forms\Components\Select::make('member_id')
                                ->label('Member')
                                ->options(
                                    fn () => Member::with('user')
                                        ->get()
                                        ->mapWithKeys(fn (Member $m) => [
                                            $m->id => $m->user
                                                ? "{$m->user->name} ({$m->user->user_code})"
                                                : "Member #{$m->id}",
                                        ])
                                )
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Transaction $record, array $data): void {
                            DB::transaction(function () use ($record, $data): void {
                                $member = Member::find($data['member_id']);
                                if ($member) {
                                    $member->creditFundAccount(abs((float) $record->amount));
                                    $record->update(['user_id' => $member->user_id]);
                                }
                            });

                            \Filament\Notifications\Notification::make()
                                ->title('Posted to member')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (Transaction $record): bool => ! $record->user_id
                            && ! in_array($record->type, ['external_import', 'master_to_user_bank', 'loan_disbursement', 'contribution', 'loan_repayment'], true)),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->selectable(true)
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('assign_member')
                        ->label('Post To Member')
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Forms\Components\Select::make('member_id')
                                ->label('Member')
                                ->options(
                                    fn () => Member::with('user')
                                        ->get()
                                        ->mapWithKeys(fn (Member $m) => [
                                            $m->id => $m->user
                                                ? "{$m->user->name} ({$m->user->user_code})"
                                                : "Member #{$m->id}",
                                        ])
                                )
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (array $data, $records): void {
                            $member = Member::find($data['member_id']);
                            if (!$member) {
                                return;
                            }
                            $assigned = 0;
                            DB::transaction(function () use ($records, $member, &$assigned): void {
                                foreach ($records as $record) {
                                    if ($record->type !== 'external_import' || $record->user_id) {
                                        continue;
                                    }
                                    $member->creditBankAccount((float) $record->amount);
                                    $record->update(['user_id' => $member->user_id]);
                                    $assigned++;
                                }
                            });
                            \Filament\Notifications\Notification::make()
                                ->title('Assign Member')
                                ->body($assigned . ' transaction(s) assigned to member.')
                                ->success()
                                ->send();
                            $this->getOwnerRecord()->refresh();
                            $this->dispatch('refreshMasterAccountRecord');
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => $this->getOwnerRecord()->account_type === 'master_bank'),

                    Actions\DeleteBulkAction::make()
                        ->authorize(fn() => true)
                        ->after(function (): void {
                            $this->getOwnerRecord()->refresh();
                            $this->dispatch('refreshMasterAccountRecord');
                        }),
                ]),
            ])
            ->paginated([10, 25, 50]);
    }

    public function getTableQuery(): Builder|Relation|null
    {
        $owner = $this->getOwnerRecord();

        if (!$owner) {
            return null;
        }

        /** @var Builder $query */
        $query = Transaction::query();

        return match ($owner->account_type) {
            'master_bank' => $query->where(function ($q) {
                $q->whereIn('type', ['external_import', 'master_to_user_bank', 'loan_disbursement'])
                  ->orWhere(fn ($q2) => $q2->where('type', 'adjustment')->where('from_account', 'master_bank'));
            }),
            'master_fund' => $query->where(function ($q) {
                $q->whereIn('type', ['contribution', 'loan_repayment'])
                  ->orWhere(fn ($q2) => $q2->where('type', 'adjustment')->where('from_account', 'master_fund'));
            }),
            default => $query->whereRaw('0 = 1'),
        };
    }
}

