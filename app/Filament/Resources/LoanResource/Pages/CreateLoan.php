<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $amount = (float) $data['original_amount'];
        $tier = \App\Models\Member::loanTierFor($amount);

        $data['loan_id'] = \App\Models\Loan::generateLoanId();
        
        $term = (int) ($data['term_months'] ?? 12);
        
        if ($tier) {
            $data['installment_amount'] = $tier['installment_amount'];
            $data['maturity_fund_balance'] = $tier['maturity_balance'];
        } else {
            $data['installment_amount'] = null;
            $data['maturity_fund_balance'] = null;
        }
        
        $rate = (float) ($data['interest_rate'] ?? 0);
        $data['monthly_payment'] = \App\Models\Loan::calculateMonthlyPayment($amount, $rate, $term);
        
        $data['total_paid'] = 0;
        $data['outstanding_balance'] = $amount;
        
        unset($data['_fund_balance'], $data['_max_loan'], $data['_eligibility_errors']);

        return $data;
    }
}
