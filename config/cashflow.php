<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Reconciliation Settings
    |--------------------------------------------------------------------------
    */
    'reconciliation' => [
        'tolerance' => env('RECONCILIATION_TOLERANCE', 0.01),
        'auto_create_exceptions' => env('AUTO_CREATE_EXCEPTIONS', true),
        'daily_schedule_time' => '23:30',
    ],

    /*
    |--------------------------------------------------------------------------
    | Loan Settings
    |--------------------------------------------------------------------------
    */
    'loans' => [
        'max_term_months' => env('MAX_LOAN_TERM_MONTHS', 60),
        'min_interest_rate' => 0,
        'max_interest_rate' => env('MAX_INTEREST_RATE', 25),
        'payment_reminder_days' => [5, 1], // Days before due date to send reminders
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception SLA Hours
    |--------------------------------------------------------------------------
    */
    'exceptions' => [
        'sla_hours' => [
            'critical' => 1,
            'high' => 2,
            'medium' => 4,
            'low' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Types
    |--------------------------------------------------------------------------
    */
    'transaction_types' => [
        'external_import' => 'External Bank Import',
        'master_to_user_bank' => 'Master to User Bank Distribution',
        'contribution' => 'User Contribution',
        'loan_repayment' => 'Loan Repayment',
        'loan_disbursement' => 'Loan Disbursement',
        'adjustment' => 'Manual Adjustment',
    ],
];
