<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExternalBankAccountResource;
use App\Filament\Resources\MasterAccountResource;
use App\Filament\Resources\UserResource;
use App\Models\MasterAccount;
use App\Models\User;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class AccountManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wallet';
    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';
    protected static ?string $navigationLabel = 'Account Management';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Account Lifecycle Management';
    protected string $view = 'filament.pages.account-management';

    public function getTitle(): string|Htmlable
    {
        return 'Account Lifecycle Management';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Account Lifecycle Management';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('master_accounts')
                ->label('Manage Master Accounts')
                ->icon('heroicon-o-banknotes')
                ->url(MasterAccountResource::getUrl('index')),

            Actions\Action::make('external_banks')
                ->label('External Bank Accounts')
                ->icon('heroicon-o-building-library')
                ->url(ExternalBankAccountResource::getUrl('index')),

            Actions\Action::make('users')
                ->label('User Management')
                ->icon('heroicon-o-users')
                ->url(UserResource::getUrl('index')),
        ];
    }

    public function getMasterBankProperty(): ?MasterAccount
    {
        return MasterAccount::getMasterBank();
    }

    public function getMasterFundProperty(): ?MasterAccount
    {
        return MasterAccount::getMasterFund();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => User::query()->orderBy('name'))
            ->columns([
                Tables\Columns\TextColumn::make('user_code')
                    ->label('User ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

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
                    ->label('Outstanding Loans')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('available_to_borrow')
                    ->label('Available to Borrow')
                    ->money('USD')
                    ->state(fn(User $record) => $record->available_to_borrow),
            ])
            ->recordActions([
                Actions\Action::make('edit')
                    ->label('Manage')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn(User $record) => UserResource::getUrl('edit', ['record' => $record])),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
