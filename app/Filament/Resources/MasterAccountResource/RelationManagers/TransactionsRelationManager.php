<?php

namespace App\Filament\Resources\MasterAccountResource\RelationManagers;

use App\Models\Transaction;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-arrow-path';

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

                Tables\Columns\TextColumn::make('from_account')
                    ->limit(20)
                    ->tooltip(fn($record) => $record->from_account),

                Tables\Columns\TextColumn::make('to_account')
                    ->limit(20)
                    ->tooltip(fn($record) => $record->to_account),

                Tables\Columns\TextColumn::make('amount')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
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
                Actions\Action::make('assign_user')
                    ->label('Assign to User Bank')
                    ->icon('heroicon-o-user-plus')
                    ->color('gray')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User Bank Account')
                            ->options(fn () => User::active()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $user = User::find($data['user_id']);
                            if ($user) {
                                $user->creditBankAccount((float) $record->amount);
                            }
                            $record->update(['user_id' => $data['user_id']]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Assigned to user bank')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Transaction $record): bool => $this->getOwnerRecord()->account_type === 'master_bank'
                        && $record->type === 'external_import'
                        && ! $record->user_id),

                Actions\Action::make('reassign_user')
                    ->label('Reassign to User Bank')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('User Bank Account')
                            ->options(fn () => User::active()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required()
                            ->default(fn (Transaction $record) => $record->user_id),
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $oldUser = $record->user;
                            if ($oldUser) {
                                $oldUser->debitBankAccount((float) $record->amount);
                            }
                            $newUser = User::find($data['user_id']);
                            if ($newUser) {
                                $newUser->creditBankAccount((float) $record->amount);
                            }
                            $record->update(['user_id' => $data['user_id']]);
                        });

                        \Filament\Notifications\Notification::make()
                            ->title('Reassigned to user bank')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (Transaction $record): bool => $this->getOwnerRecord()->account_type === 'master_bank'
                        && $record->type === 'external_import'
                        && (bool) $record->user_id),
            ])
            ->selectable(true)
            ->toolbarActions([
                Actions\BulkActionGroup::make([
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
            'master_bank' => $query->whereIn('type', ['external_import', 'master_to_user_bank']),
            'master_fund' => $query->whereIn('type', ['contribution', 'loan_repayment', 'loan_disbursement']),
            default => $query->whereRaw('0 = 1'),
        };
    }
}

