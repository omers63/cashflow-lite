<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\MemberResource;
use App\Filament\Resources\TransactionResource;
use App\Filament\Support\TransactionDeleteActionConfigurator;
use App\Models\Member;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            TransactionDeleteActionConfigurator::configureRecordDelete(Actions\DeleteAction::make()),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $target = $data['target_account'] ?? '';
        $userId = $data['user_id'] ?? null;

        if (str_starts_with($target, 'external_bank:')) {
            $data['external_bank_account_id'] = explode(':', $target)[1] ?? null;
        }
        if (($target === 'user_bank' || $target === 'user_fund') && $userId) {
            $data['target_account'] = "{$target}:{$userId}";
        }

        return TransactionResource::hydrateContributionClassificationTiming($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $type = $data['type'] ?? null;

        $data['debit_or_credit'] = $data['debit_or_credit'] ?? 'credit';

        if ($type === 'external_import' && ! empty($data['external_bank_account_id'])) {
            $data['target_account'] = 'external_bank:'.$data['external_bank_account_id'];
        } elseif (in_array($type, ['master_to_user_bank', 'contribution', 'loan_repayment'], true)) {
            $data['target_account'] = 'user_bank';
        } elseif ($type === 'loan_disbursement') {
            $data['target_account'] = null;
        } elseif ($type === 'adjustment' && ! empty($data['target_account'])) {
            $target = $data['target_account'];
            if (preg_match('/^user_bank:(\d+)$/', $target, $m)) {
                $data['target_account'] = 'user_bank';
                $data['user_id'] = (int) $m[1];
            } elseif (preg_match('/^user_fund:(\d+)$/', $target, $m)) {
                $data['target_account'] = 'user_fund';
                $data['user_id'] = (int) $m[1];
            }
        }

        unset($data['external_bank_account_id']);

        return TransactionResource::applyContributionClassificationToSave($data);
    }

    protected function getRedirectUrl(): string
    {
        $memberContext = request()->query('member_context');
        if ($memberContext !== null && $memberContext !== '') {
            $member = Member::query()->find((int) $memberContext);
            $record = $this->getRecord();
            if (
                $member
                && $record->user_id
                && (int) $member->user_id === (int) $record->user_id
            ) {
                return MemberResource::getUrl('view', ['record' => $member]);
            }
        }

        return $this->getResource()::getUrl('index');
    }
}
