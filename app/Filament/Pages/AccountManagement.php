<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExternalBankAccountResource;
use App\Filament\Resources\MasterAccountResource;
use App\Filament\Resources\MemberResource;
use App\Filament\Resources\UserResource;
use App\Models\MasterAccount;
use App\Models\Member;
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
    protected static ?string $navigationLabel = 'Accounts';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Account Lifecycle Management';
    protected string $view = 'filament.pages.account-management';

    public ?array $masterBankSummary = null;
    public ?array $masterFundSummary = null;

    public function mount(): void
    {
        $this->loadMasterAccounts();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Account Lifecycle Management';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Account Lifecycle Management';
    }

    protected function loadMasterAccounts(): void
    {
        $masterBank = MasterAccount::getMasterBank();
        $masterFund = MasterAccount::getMasterFund();

        $this->masterBankSummary = $masterBank ? [
            'id' => $masterBank->id,
            'balance' => (float) $masterBank->balance,
            'balance_date' => $masterBank->balance_date?->format('M d, Y'),
        ] : null;

        $this->masterFundSummary = $masterFund ? [
            'id' => $masterFund->id,
            'balance' => (float) $masterFund->balance,
            'balance_date' => $masterFund->balance_date?->format('M d, Y'),
        ] : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('master_accounts')
                ->label('')
                ->tooltip('Manage Master Accounts')
                ->icon('heroicon-o-banknotes')
                ->link()
                ->url(MasterAccountResource::getUrl('index')),

            Actions\Action::make('external_banks')
                ->label('')
                ->tooltip('External Bank Accounts')
                ->icon('heroicon-o-building-library')
                ->link()
                ->url(ExternalBankAccountResource::getUrl('index')),

            Actions\Action::make('members')
                ->label('')
                ->tooltip('Members')
                ->icon('heroicon-o-user-group')
                ->link()
                ->url(MemberResource::getUrl('index')),

            Actions\Action::make('users')
                ->label('')
                ->tooltip('Users')
                ->icon('heroicon-o-users')
                ->link()
                ->url(UserResource::getUrl('index')),

            Actions\Action::make('balance_snapshots')
                ->label('')
                ->tooltip('Balance Snapshots')
                ->icon('heroicon-o-archive-box')
                ->link()
                ->url(\App\Filament\Resources\BalanceSnapshotResource::getUrl('index')),
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
            ->query(fn () => Member::query()->with('user')->orderBy('id'))
            ->columns([
                Tables\Columns\TextColumn::make('user.user_code')
                    ->label('User Code')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
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
                    ->getStateUsing(fn (Member $record) => $record->available_to_borrow),
            ])
            ->recordActions([
                Actions\Action::make('edit')
                    ->label('Manage')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Member $record) => MemberResource::getUrl('edit', ['record' => $record])),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
