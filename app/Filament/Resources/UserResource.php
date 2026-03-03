<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Schemas\Schema;
use Filament\Schemas\Components;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'User Management';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('User Information')
                    ->schema([
                        Forms\Components\TextInput::make('user_code')
                            ->label('User Code')
                            ->default(fn() => User::generateUserCode())
                            ->disabled()
                            ->dehydrated()
                            ->required(),

                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('active'),

                        Forms\Components\Select::make('role')
                            ->options([
                                'user' => 'User',
                                'admin' => 'Admin',
                                'super_admin' => 'Super Admin',
                            ])
                            ->required()
                            ->default('user'),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required(fn(string $context) => $context === 'create')
                            ->dehydrated(fn($state) => filled($state))
                            ->maxLength(255),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user_code')
                    ->label('User ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        default => 'User',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'secondary',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->recordActions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make()
                        ->label('View')
                        ->tooltip('View'),
                    Actions\EditAction::make()
                        ->label('Edit')
                        ->tooltip('Edit'),

                    Actions\Action::make('suspend')
                        ->label('Suspend')
                        ->tooltip('Suspend')
                        ->icon('heroicon-o-no-symbol')
                        ->requiresConfirmation()
                        ->schema([
                            Forms\Components\Textarea::make('reason')
                                ->required()
                                ->label('Suspension Reason'),
                        ])
                        ->action(function (User $record, array $data) {
                            $record->suspend($data['reason']);
                        })
                        ->visible(fn(User $record) => $record->status === 'active'),

                    Actions\Action::make('activate')
                        ->label('Activate')
                        ->tooltip('Activate')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn(User $record) => $record->activate())
                        ->visible(fn(User $record) => $record->status !== 'active'),
                ])
                    ->label('')
                    ->icon('heroicon-o-ellipsis-horizontal'),
            ])
            ->toolbarActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->emptyStateHeading('No users yet')
            ->emptyStateDescription('Create the first user to get started.')
            ->emptyStateIcon('heroicon-o-users');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Components\Section::make('User Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user_code')
                            ->label('User ID'),
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('phone'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->colors([
                                'success' => 'active',
                                'warning' => 'inactive',
                                'danger' => 'suspended',
                            ]),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ])
                    ->columns(2),

            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }
}
