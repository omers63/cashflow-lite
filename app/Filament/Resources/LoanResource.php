<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Models\Loan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financial Operations';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Loan Information')
                    ->schema([
                        Forms\Components\TextInput::make('loan_id')
                            ->default(fn() => Loan::generateLoanId())
                            ->disabled()
                            ->dehydrated()
                            ->required(),
                        
                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $user = User::find($state);
                                    if ($user) {
                                        $set('available_to_borrow', $user->available_to_borrow);
                                    }
                                }
                            }),
                        
                        Forms\Components\Placeholder::make('available_to_borrow')
                            ->label('User Available to Borrow')
                            ->content(fn($get) => $get('available_to_borrow') 
                                ? '$' . number_format($get('available_to_borrow'), 2)
                                : '$0.00'),
                        
                        Forms\Components\DatePicker::make('origination_date')
                            ->default(now())
                            ->required(),
                        
                        Forms\Components\TextInput::make('original_amount')
                            ->label('Loan Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                                $set('monthly_payment', Loan::calculateMonthlyPayment(
                                    $state ?? 0,
                                    $get('interest_rate') ?? 0,
                                    $get('term_months') ?? 12
                                ))
                            ),
                        
                        Forms\Components\TextInput::make('interest_rate')
                            ->label('Annual Interest Rate (%)')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->reactive()
                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                                $set('monthly_payment', Loan::calculateMonthlyPayment(
                                    $get('original_amount') ?? 0,
                                    $state ?? 0,
                                    $get('term_months') ?? 12
                                ))
                            ),
                        
                        Forms\Components\TextInput::make('term_months')
                            ->label('Term (Months)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(360)
                            ->default(12)
                            ->reactive()
                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => 
                                $set('monthly_payment', Loan::calculateMonthlyPayment(
                                    $get('original_amount') ?? 0,
                                    $get('interest_rate') ?? 0,
                                    $state ?? 12
                                ))
                            ),
                        
                        Forms\Components\TextInput::make('monthly_payment')
                            ->label('Monthly Payment')
                            ->numeric()
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(),
                        
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending Approval',
                                'active' => 'Active',
                                'paid_off' => 'Paid Off',
                                'defaulted' => 'Defaulted',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('pending')
                            ->required(),
                        
                        Forms\Components\Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan_id')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('origination_date')
                    ->date()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('original_amount')
                    ->label('Amount')
                    ->money('USD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('interest_rate')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('term_months')
                    ->label('Term')
                    ->suffix(' mo')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('monthly_payment')
                    ->label('Payment')
                    ->money('USD')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('outstanding_balance')
                    ->label('Balance')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),
                
                Tables\Columns\BadgeColumn::make('status')
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
                    ->color(fn($record) => $record->isDelinquent() ? 'danger' : null),
            ])
            ->defaultSort('origination_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Approval',
                        'active' => 'Active',
                        'paid_off' => 'Paid Off',
                        'defaulted' => 'Defaulted',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple(),
                
                Tables\Filters\SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\Filter::make('delinquent')
                    ->label('Delinquent')
                    ->query(fn($query) => $query->where('status', 'active')
                        ->where('next_payment_date', '<', now())),
                
                Tables\Filters\Filter::make('due_soon')
                    ->label('Due in 7 Days')
                    ->query(fn($query) => $query->where('status', 'active')
                        ->whereBetween('next_payment_date', [now(), now()->addDays(7)])),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->status === 'pending'),
                
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Loan $record) {
                        $record->approve(auth()->id());
                        
                        // Create disbursement transaction
                        // This should be done in your service layer
                    })
                    ->visible(fn($record) => $record->status === 'pending'),
                
                Tables\Actions\Action::make('view_schedule')
                    ->icon('heroicon-o-calendar')
                    ->color('info')
                    ->modalHeading('Amortization Schedule')
                    ->modalContent(fn($record) => view('filament.modals.amortization-schedule', [
                        'schedule' => $record->generateAmortizationSchedule()
                    ]))
                    ->modalSubmitAction(false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Loan Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('loan_id')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Borrower'),
                        Infolists\Components\TextEntry::make('origination_date')
                            ->date(),
                        Infolists\Components\BadgeEntry::make('status'),
                        Infolists\Components\TextEntry::make('original_amount')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('outstanding_balance')
                            ->money('USD')
                            ->color(fn($record) => $record->outstanding_balance > 0 ? 'warning' : 'success'),
                        Infolists\Components\TextEntry::make('interest_rate')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('term_months')
                            ->suffix(' months'),
                        Infolists\Components\TextEntry::make('monthly_payment')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('next_payment_date')
                            ->date()
                            ->color(fn($record) => $record->isDelinquent() ? 'danger' : null),
                    ])
                    ->columns(2),
                
                Infolists\Components\Section::make('Payment Progress')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_paid')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('total_principal_paid')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('total_interest_paid')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('progress_percentage')
                            ->suffix('%')
                            ->color('success'),
                        Infolists\Components\TextEntry::make('payments')
                            ->label('Payments Made')
                            ->state(fn($record) => $record->payments()->count()),
                        Infolists\Components\TextEntry::make('remaining_term')
                            ->suffix(' months'),
                    ])
                    ->columns(3),
                
                Infolists\Components\Section::make('Approval Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('approver.name')
                            ->label('Approved By'),
                        Infolists\Components\TextEntry::make('approved_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(fn($record) => $record->approved_by),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view' => Pages\ViewLoan::route('/{record}'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $delinquent = static::getModel()::where('status', 'active')
            ->where('next_payment_date', '<', now())
            ->count();
        
        return $delinquent > 0 ? $delinquent : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
