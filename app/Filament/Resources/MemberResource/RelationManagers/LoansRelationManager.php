<?php

namespace App\Filament\Resources\MemberResource\RelationManagers;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use App\Models\Member;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoansRelationManager extends RelationManager
{
    /**
     * Eager-load this manager so ownerRecord is a full Member (lazy embeds can omit attributes like user_id,
     * which made queryForMember match only member_id and miss loans with null member_id).
     */
    protected static bool $isLazy = false;

    protected static string $relationship = 'loans';

    protected static ?string $title = 'Loans';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-banknotes';

    /**
     * Apply the member-scoped query here (not only in table()) so it wins over the
     * relationship query from makeBaseRelationshipTable — otherwise Filament can
     * resolve rows via member.loans() (user_id-only) and miss loans.
     */
    protected function makeTable(): Table
    {
        return parent::makeTable()
            ->query(function (): Builder {
                $owner = $this->getOwnerRecord();

                return $owner instanceof Member
                    ? Loan::queryForMember($owner->fresh())
                    : Loan::query()->whereKey([-1]);
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->label('Loan ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('is_emergency')
                    ->label('Emergency')
                    ->boolean()
                    ->trueColor('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('origination_date')
                    ->label('Origination Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('installment_amount')
                    ->label('Installment')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'primary' => 'paid_off',
                        'danger' => 'defaulted',
                        'secondary' => 'cancelled',
                    ]),

                Tables\Columns\TextColumn::make('next_payment_date')
                    ->label('Next Payment')
                    ->date()
                    ->sortable()
                    ->color(fn (Loan $record) => $record->isDelinquent() ? 'danger' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->selectable(true)
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View')
                        ->url(fn (Loan $record) => LoanResource::getUrl('view', ['record' => $record])),
                    Actions\EditAction::make()
                        ->label('Edit')
                        ->tooltip('Edit')
                        ->url(fn (Loan $record) => LoanResource::getUrl('edit', ['record' => $record]))
                        ->authorize(fn () => true),
                    Actions\Action::make('view_schedule')
                        ->label('View Schedule')
                        ->tooltip('View Amortization Schedule')
                        ->icon('heroicon-o-calendar')
                        ->modalHeading('Amortization Schedule')
                        ->modalContent(fn (Loan $record) => view('filament.modals.amortization-schedule', [
                            'schedule' => $record->generateAmortizationSchedule(),
                        ]))
                        ->modalSubmitAction(false),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No loans yet')
            ->emptyStateDescription('This member has not taken any loans.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}

