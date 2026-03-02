<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static string|\UnitEnum|null $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Members';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Member')
                    ->description('A member is a user. Creating a member will create a new user. A dependant can only have one parent; a dependant cannot be a parent.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (?Member $record) => $record === null),

                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(table: 'users', column: 'email')
                            ->maxLength(255)
                            ->visible(fn (?Member $record) => $record === null),

                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->required()
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255)
                            ->visible(fn (?Member $record) => $record === null),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(20)
                            ->visible(fn (?Member $record) => $record === null),

                        Forms\Components\Select::make('status')
                            ->label('User status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->default('active')
                            ->visible(fn (?Member $record) => $record === null),

                        Forms\Components\Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (?Member $record) => $record !== null)
                            ->helperText('User cannot be changed after creation.'),

                        Forms\Components\DatePicker::make('membership_date')
                            ->label('Membership Date')
                            ->default(now())
                            ->required()
                            ->helperText('The date this person became a member. Used for loan eligibility (min 1 year).'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent member (optional)')
                            ->options(function (?Member $record) {
                                $query = Member::eligibleParentsQuery()->with('user');
                                if ($record?->id) {
                                    $query->where('id', '!=', $record->id);
                                }
                                return $query->get()->mapWithKeys(fn (Member $m) => [
                                    $m->id => $m->user ? "{$m->user->name} ({$m->user->user_code})" : "User #{$m->user_id}",
                                ]);
                            })
                            ->searchable()
                            ->placeholder('None (this member is not a dependant)')
                            ->helperText('Only members who are not dependants can be parents. Leave empty if this member is not a dependant.'),
                    ])
                    ->columns(1),

                Components\Section::make('Account Balances')
                    ->description('Financial balances for this member. Updated by transactions.')
                    ->schema([
                        Forms\Components\TextInput::make('bank_account_balance')
                            ->label('Bank Account Balance')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->step(0.01)
                            ->disabled(fn (?Member $record) => $record !== null)
                            ->dehydrated(fn (?Member $record) => $record === null)
                            ->helperText(fn (?Member $record) => $record ? 'Read-only. Use Recalculate Balance or transactions to update.' : 'Initial balance when creating a member.'),

                        Forms\Components\TextInput::make('fund_account_balance')
                            ->label('Fund Account Balance')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->step(0.01)
                            ->disabled(fn (?Member $record) => $record !== null)
                            ->dehydrated(fn (?Member $record) => $record === null),

                        Forms\Components\TextInput::make('outstanding_loans')
                            ->label('Outstanding Loans')
                            ->numeric()
                            ->prefix('$')
                            ->default(0)
                            ->step(0.01)
                            ->disabled(),

                        Forms\Components\Select::make('allowed_allocation')
                            ->label('Bank Balance Cap')
                            ->options(array_combine(
                                Member::ALLOCATION_OPTIONS,
                                array_map(fn ($v) => '$' . number_format($v, 0), Member::ALLOCATION_OPTIONS)
                            ))
                            ->default(500)
                            ->required()
                            ->visible(fn (?Member $record) => $record !== null)
                            ->helperText('Maximum allowed bank account balance for this member (multiples of $500, up to $3,000).'),

                        Forms\Components\TextInput::make('available_to_borrow')
                            ->label('Available to Borrow')
                            ->prefix('$')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?Member $record) => $record ? number_format($record->available_to_borrow, 2) : '0.00')
                            ->visible(fn (?Member $record) => $record !== null),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.user_code')
                    ->label('User Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('parent.user.name')
                    ->label('Parent')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_parent')
                    ->label('Parent member')
                    ->boolean()
                    ->getStateUsing(fn (Member $record) => $record->isParentMember()),
                Tables\Columns\IconColumn::make('is_dependant')
                    ->label('Dependant')
                    ->boolean()
                    ->getStateUsing(fn (Member $record) => $record->isDependantMember()),

                Tables\Columns\TextColumn::make('bank_account_balance')
                    ->label('Bank Account')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('fund_account_balance')
                    ->label('Fund Account')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('outstanding_loans')
                    ->label('Loans')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('Total'),
                    ]),

                Tables\Columns\TextColumn::make('available_to_borrow')
                    ->label('Available')
                    ->money('USD')
                    ->getStateUsing(fn (Member $record) => $record->available_to_borrow)
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("(fund_account_balance - outstanding_loans) {$direction}")),
            ])
            ->defaultSort('id')
            ->filters([
                Tables\Filters\Filter::make('has_parent')
                    ->label('Has parent')
                    ->query(fn ($query) => $query->whereNotNull('parent_id')),
                Tables\Filters\Filter::make('is_parent_member')
                    ->label('Parent member (has dependants)')
                    ->query(fn ($query) => $query->whereIn('id', Member::whereNotNull('parent_id')->pluck('parent_id'))),
            ])
            ->recordActions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('Member')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.user_code')
                            ->label('User Code'),
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('parent.user.name')
                            ->label('Parent member')
                            ->placeholder('—'),
                        Infolists\Components\IconEntry::make('is_parent')
                            ->label('Is parent member')
                            ->getStateUsing(fn (Member $record) => $record->isParentMember()),
                        Infolists\Components\IconEntry::make('is_dependant')
                            ->label('Is dependant')
                            ->getStateUsing(fn (Member $record) => $record->isDependantMember()),
                        Infolists\Components\TextEntry::make('membership_date')
                            ->label('Member Since')
                            ->date()
                            ->helperText(fn (Member $record) => number_format($record->membershipYears(), 1) . ' year(s)'),
                    ])
                    ->columns(2),

                Components\Section::make('Account Balances')
                    ->schema([
                        Infolists\Components\TextEntry::make('bank_account_balance')
                            ->label('Bank Account')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('fund_account_balance')
                            ->label('Fund Account')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('outstanding_loans')
                            ->label('Outstanding Loans')
                            ->money('USD'),
                        Infolists\Components\TextEntry::make('available_to_borrow')
                            ->label('Available to Borrow')
                            ->getStateUsing(fn (Member $record) => $record->available_to_borrow)
                            ->money('USD')
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                        Infolists\Components\TextEntry::make('allowed_allocation')
                            ->label('Bank Balance Cap')
                            ->getStateUsing(fn (Member $record) => '$' . number_format((int) ($record->allowed_allocation ?? 500), 2))
                            ->helperText(fn (Member $record) => 'Room remaining: $' . number_format(
                                max(0, (int) ($record->allowed_allocation ?? 500) - (float) $record->bank_account_balance), 2
                            ))
                            ->color('info'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\MemberResource\RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'view' => Pages\ViewMember::route('/{record}'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return 'Member';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Members';
    }
}
