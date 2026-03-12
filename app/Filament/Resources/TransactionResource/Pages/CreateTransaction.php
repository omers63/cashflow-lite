<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['type'] ?? null;

        $data['debit_or_credit'] = $data['debit_or_credit'] ?? 'credit';

        if ($type === 'external_import' && !empty($data['external_bank_account_id'])) {
            $data['target_account'] = 'external_bank:' . $data['external_bank_account_id'];
        } elseif (in_array($type, ['master_to_user_bank', 'contribution', 'loan_repayment'], true)) {
            $data['target_account'] = 'user_bank';
        } elseif ($type === 'loan_disbursement') {
            $data['target_account'] = null;
        } elseif ($type === 'adjustment' && !empty($data['target_account'])) {
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
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
