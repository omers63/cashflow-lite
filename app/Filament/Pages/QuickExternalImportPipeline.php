<?php

namespace App\Filament\Pages;

use App\Filament\Resources\MemberResource;
use App\Models\ExternalBankAccount;
use App\Models\Loan;
use App\Models\Member;
use App\Services\QuickExternalImportPipelineService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class QuickExternalImportPipeline extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|\UnitEnum|null $navigationGroup = 'Financial Operations';

    protected static ?string $navigationLabel = 'Quick import → member fund';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Quick import → member fund';

    protected string $view = 'filament.pages.quick-external-import-pipeline';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public int $step = 1;

    public bool $completed = false;

    public function mount(): void
    {
        $this->data = [
            'external_bank_account_id' => null,
            'entry_direction' => 'credit',
            'transaction_date' => now()->format('Y-m-d H:i:s'),
            'amount' => null,
            'description' => null,
            'external_ref_id' => 'MAN-QP-'.strtoupper(Str::random(10)),
            'import_notes' => null,
            'member_id' => null,
            'destination' => 'contribution',
            'contribution_amount' => null,
            'repayment_amount' => null,
            'fund_notes' => null,
            'collection_obligation_month' => null,
            'collection_period_due_date' => null,
            'collection_timing_override' => 'auto',
            'loan_id' => null,
            'repayment_date' => null,
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\Section::make('Step 1 — External bank line')
                    ->description('Creates one external bank import row (same as manual import), then posts it to master in one run.')
                    ->visible(fn (): bool => (int) $this->step === 1)
                    ->schema([
                        Forms\Components\Select::make('external_bank_account_id')
                            ->label('External bank account')
                            ->options(fn (): array => ExternalBankAccount::query()
                                ->orderBy('bank_name')
                                ->get()
                                ->mapWithKeys(fn (ExternalBankAccount $a) => [
                                    $a->id => "{$a->bank_name} — ****{$a->account_number}",
                                ])
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false),
                        Forms\Components\ToggleButtons::make('entry_direction')
                            ->label('Entry direction')
                            ->options([
                                'credit' => 'Credit (deposit)',
                                'debit' => 'Debit (withdrawal)',
                            ])
                            ->inline()
                            ->default('credit')
                            ->required()
                            ->live()
                            ->helperText('Credit: money in — typical deposit. Debit: money out — paired with a loan disbursement from the master fund (requires fund balance).'),
                        Forms\Components\DateTimePicker::make('transaction_date')
                            ->label('Transaction date')
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->helperText('Always enter a positive number; direction is set by Credit vs Debit above.'),
                        Forms\Components\TextInput::make('external_ref_id')
                            ->label('External reference')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Must be unique per external bank account.'),
                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('import_notes')
                            ->label('Internal notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Components\Section::make('Step 2 — Member')
                    ->description(fn (): string => ($this->data['entry_direction'] ?? 'credit') === 'debit'
                        ? 'Borrower member for this line (the disbursement is credited to their bank and tied to the master import).'
                        : 'Who receives the bank credit from master (same as “Post to member” on the master import).')
                    ->visible(fn (): bool => (int) $this->step === 2)
                    ->schema([
                        Forms\Components\Select::make('member_id')
                            ->label('Member')
                            ->options(fn (): array => Member::query()
                                ->with('user')
                                ->orderBy('id')
                                ->get()
                                ->mapWithKeys(fn (Member $m) => [
                                    $m->id => $m->user
                                        ? "{$m->user->name} ({$m->user->user_code})"
                                        : "Member #{$m->id}",
                                ])
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false),
                    ]),

                Components\Section::make('Step 3 — Complete the flow')
                    ->description(fn (): string => ($this->data['entry_direction'] ?? 'credit') === 'debit'
                        ? 'Debit import: withdrawal on the external/master line, then loan disbursement from the master fund to the member bank (requires sufficient master fund balance).'
                        : 'Credit import: after the member bank is credited, contribute to the fund or repay a loan. Repayment uses your amount first; any leftover import funds stay in the member bank.')
                    ->visible(fn (): bool => (int) $this->step === 3)
                    ->schema([
                        Forms\Components\ToggleButtons::make('destination')
                            ->label('Next posting')
                            ->options([
                                'contribution' => 'Contribution',
                                'loan_repayment' => 'Loan repayment',
                            ])
                            ->inline()
                            ->required(fn (): bool => ($this->data['entry_direction'] ?? 'credit') === 'credit')
                            ->visible(fn (): bool => ($this->data['entry_direction'] ?? 'credit') === 'credit')
                            ->live(),

                        Forms\Components\TextInput::make('contribution_amount')
                            ->label('Contribution amount')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0.01)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'contribution')
                            ->helperText('Defaults to the import amount when you continue from step 2. Cannot exceed member bank after the credit posts.'),

                        Forms\Components\Textarea::make('fund_notes')
                            ->label(fn (): string => ($this->data['entry_direction'] ?? 'credit') === 'debit'
                                ? 'Notes (disbursement)'
                                : 'Notes (contribution / repayment)')
                            ->rows(2)
                            ->columnSpanFull(),

                        Components\Section::make('Collection classification (optional)')
                            ->description('Only applies to contributions.')
                            ->columnSpanFull()
                            ->visible(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'contribution')
                            ->schema([
                                Forms\Components\DatePicker::make('collection_obligation_month')
                                    ->label('Collection period (obligation month)')
                                    ->native(false),
                                Forms\Components\DatePicker::make('collection_period_due_date')
                                    ->label('Period due date')
                                    ->native(false),
                                Forms\Components\Select::make('collection_timing_override')
                                    ->label('On time / late')
                                    ->options([
                                        'auto' => 'Automatic',
                                        'on_time' => 'On time',
                                        'late' => 'Late',
                                    ])
                                    ->default('auto'),
                            ])
                            ->columns(2)
                            ->collapsed(),

                        Forms\Components\Select::make('loan_id')
                            ->label('Loan')
                            ->options(function (Get $get): array {
                                $memberId = $get('member_id');
                                if (! $memberId) {
                                    return [];
                                }

                                return Loan::query()
                                    ->where('member_id', $memberId)
                                    ->where('status', 'active')
                                    ->orderBy('loan_id')
                                    ->get()
                                    ->mapWithKeys(fn (Loan $loan) => [
                                        $loan->id => "{$loan->loan_id} — balance \$".number_format((float) $loan->outstanding_balance, 2),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->visible(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'debit'
                                || (($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'loan_repayment'))
                            ->required(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'debit'
                                || (($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'loan_repayment'))
                            ->helperText(fn (Get $get): string => ($get('entry_direction') ?? 'credit') === 'debit'
                                ? 'Active loan receiving this disbursement; outstanding balance will increase.'
                                : 'Loan to repay.'),

                        Forms\Components\TextInput::make('repayment_amount')
                            ->label('Repayment amount')
                            ->numeric()
                            ->prefix('$')
                            ->minValue(0.01)
                            ->step(0.01)
                            ->visible(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'loan_repayment')
                            ->required(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'loan_repayment')
                            ->helperText('Processed after the import credits the member bank. Must not exceed bank balance after the credit. Any import amount left after repayment stays in the member bank.'),

                        Forms\Components\DatePicker::make('repayment_date')
                            ->label(fn (): string => ($this->data['entry_direction'] ?? 'credit') === 'debit'
                                ? 'Disbursement date'
                                : 'Repayment date')
                            ->native(false)
                            ->visible(fn (Get $get): bool => ($get('entry_direction') ?? 'credit') === 'debit'
                                || (($get('entry_direction') ?? 'credit') === 'credit' && $get('destination') === 'loan_repayment')),
                    ])
                    ->columns(2),

                // Must have at least one visible component on step 4: otherwise the root schema renders
                // nothing (Filament Schema::toEmbeddedHtml) and state handling can break; also keeps the form mounted.
                Components\Section::make('Step 4 — Review')
                    ->description('Confirm the summary below this form, then use Run full pipeline.')
                    ->visible(fn (): bool => (int) $this->step === 4)
                    ->schema([
                        Forms\Components\Placeholder::make('review_notice')
                            ->label('')
                            ->content('Click **Run full pipeline** at the bottom to create the import and follow-on transactions.'),
                    ]),
            ])
            ->statePath('data');
    }

    /** @return array<string, mixed> */
    protected function syncedFormData(): array
    {
        // Use raw Livewire state, not getState(): getState() runs validate()+dehydrate and returns
        // incomplete/empty data when all wizard sections are hidden (e.g. step 4 review), which
        // would otherwise wipe $this->data and run the pipeline with null amounts.
        $payload = $this->form->getRawState();
        if ($payload instanceof \Illuminate\Contracts\Support\Arrayable) {
            $payload = $payload->toArray();
        }
        if (! is_array($payload)) {
            $payload = [];
        }
        if (isset($payload['data']) && is_array($payload['data'])) {
            $payload = $payload['data'];
        }

        return $this->mergeFormPayloadIntoData($payload);
    }

    /**
     * Merge getRawState() into $this->data without losing required wizard values on step 4.
     *
     * - array_merge($this->data, $payload) let hidden fields dehydrate to null and wipe amount/member_id.
     * - array_merge($payload, $this->data) let wrong nulls in $this->data overwrite good payload values.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mergeFormPayloadIntoData(array $payload): array
    {
        $base = $this->data ?? [];
        $merged = $base;

        // After form->fill() these are intentionally null; do not revive stale values from getRawState().
        $doNotReviveWhenBaseNull = ['contribution_amount', 'repayment_amount'];

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (! array_key_exists($key, $base)) {
                $merged[$key] = $value;

                continue;
            }

            $b = $base[$key];
            if ($b === null) {
                if (in_array($key, $doNotReviveWhenBaseNull, true)) {
                    continue;
                }
                $merged[$key] = $value;

                continue;
            }

            if ($b === '') {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    public function reviewSummaryHtml(): string
    {
        $d = $this->data ?? [];
        $bank = isset($d['external_bank_account_id'])
            ? ExternalBankAccount::find($d['external_bank_account_id'])
            : null;
        $member = isset($d['member_id']) ? Member::find($d['member_id']) : null;
        $dir = $d['entry_direction'] ?? 'credit';
        $dest = $d['destination'] ?? 'contribution';
        $loan = ! empty($d['loan_id']) ? Loan::find($d['loan_id']) : null;

        $lines = [
            '<ul class="list-disc space-y-1 pl-5 text-sm">',
            '<li><strong>Bank:</strong> '.e($bank ? "{$bank->bank_name} ****{$bank->account_number}" : '—').'</li>',
            '<li><strong>Direction:</strong> '.e($dir === 'debit' ? 'Debit (withdrawal)' : 'Credit (deposit)').'</li>',
            '<li><strong>Amount:</strong> $'.e(number_format((float) ($d['amount'] ?? 0), 2)).'</li>',
            '<li><strong>Reference:</strong> '.e((string) ($d['external_ref_id'] ?? '')).'</li>',
            '<li><strong>Member:</strong> '.e($member && $member->user ? $member->user->name : '—').'</li>',
        ];

        if ($dir === 'debit') {
            $lines[] = '<li><strong>Then:</strong> Loan disbursement ('.e($loan?->loan_id ?? '?').')</li>';
        } else {
            $lines[] = '<li><strong>Then:</strong> '.e(
                $dest === 'contribution'
                    ? 'Contribution to fund'
                    : 'Loan repayment ('.($loan?->loan_id ?? '?').')'
            ).'</li>';
            if ($dest === 'contribution') {
                $lines[] = '<li><strong>Contribution amount:</strong> $'
                    .e(number_format((float) ($d['contribution_amount'] ?? $d['amount'] ?? 0), 2)).'</li>';
            } else {
                $lines[] = '<li><strong>Repayment amount:</strong> $'
                    .e(number_format((float) ($d['repayment_amount'] ?? 0), 2)).'</li>';
            }
        }

        $lines[] = '</ul>';

        return implode('', $lines);
    }

    public function goToNextStep(): void
    {
        $this->completed = false;
        $this->data = $this->syncedFormData();

        match ((int) $this->step) {
            1 => $this->validate([
                'data.external_bank_account_id' => ['required', 'exists:external_bank_accounts,id'],
                'data.entry_direction' => ['required', 'in:credit,debit'],
                'data.transaction_date' => ['required', 'date'],
                'data.amount' => ['required', 'numeric', 'min:0.01'],
                'data.external_ref_id' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('external_bank_imports', 'external_ref_id')->where(
                        fn ($q) => $q->where(
                            'external_bank_account_id',
                            (int) ($this->data['external_bank_account_id'] ?? 0)
                        )
                    ),
                ],
                'data.description' => ['nullable', 'string'],
                'data.import_notes' => ['nullable', 'string'],
            ]),
            2 => $this->validate([
                'data.member_id' => ['required', 'exists:members,id'],
            ]),
            3 => match ($this->data['entry_direction'] ?? 'credit') {
                'debit' => $this->validate([
                    'data.fund_notes' => ['nullable', 'string'],
                    'data.loan_id' => [
                        'required',
                        'exists:loans,id',
                        function (string $attribute, mixed $value, \Closure $fail): void {
                            $loan = Loan::query()->find($value);
                            if (! $loan || (int) $loan->member_id !== (int) ($this->data['member_id'] ?? 0)) {
                                $fail('Choose a loan that belongs to the selected member.');
                            }
                            if ($loan && $loan->status !== 'active') {
                                $fail('Only active loans can receive a disbursement.');
                            }
                        },
                    ],
                    'data.repayment_date' => ['nullable', 'date'],
                ]),
                default => $this->validate([
                    'data.destination' => ['required', 'in:contribution,loan_repayment'],
                    'data.contribution_amount' => [
                        Rule::requiredIf(fn () => ($this->data['destination'] ?? '') === 'contribution'),
                        'nullable',
                        'numeric',
                        'min:0.01',
                    ],
                    'data.repayment_amount' => [
                        Rule::requiredIf(fn () => ($this->data['destination'] ?? '') === 'loan_repayment'),
                        'nullable',
                        'numeric',
                        'min:0.01',
                    ],
                    'data.fund_notes' => ['nullable', 'string'],
                    'data.collection_obligation_month' => ['nullable', 'date'],
                    'data.collection_period_due_date' => ['nullable', 'date'],
                    'data.collection_timing_override' => ['nullable', 'string'],
                    'data.loan_id' => [
                        Rule::requiredIf(fn () => ($this->data['destination'] ?? '') === 'loan_repayment'),
                        'nullable',
                        'exists:loans,id',
                        function (string $attribute, mixed $value, \Closure $fail): void {
                            if (($this->data['destination'] ?? '') !== 'loan_repayment' || $value === null || $value === '') {
                                return;
                            }
                            $loan = Loan::query()->find($value);
                            if (! $loan || (int) $loan->member_id !== (int) ($this->data['member_id'] ?? 0)) {
                                $fail('Choose a loan that belongs to the selected member.');
                            }
                            if ($loan && $loan->status !== 'active') {
                                $fail('Only active loans can receive a repayment.');
                            }
                        },
                    ],
                    'data.repayment_date' => ['nullable', 'date'],
                ]),
            },
            default => null,
        };

        if ((int) $this->step === 1) {
            if (($this->data['entry_direction'] ?? 'credit') === 'credit') {
                // Avoid carrying contribution/repayment amounts from a previous wizard pass when
                // the import amount or flow was reset (e.g. after a completed run).
                $this->data['contribution_amount'] = null;
                $this->data['repayment_amount'] = null;
                $this->form->fill($this->data);
            }
            $this->step = 2;
        } elseif ((int) $this->step === 2) {
            if (($this->data['entry_direction'] ?? 'credit') === 'credit') {
                if (empty($this->data['contribution_amount']) && ($this->data['destination'] ?? '') === 'contribution') {
                    $this->data['contribution_amount'] = $this->data['amount'];
                }
                if (empty($this->data['repayment_amount']) && ($this->data['destination'] ?? '') === 'loan_repayment') {
                    $this->data['repayment_amount'] = $this->data['amount'];
                }
            }
            $this->form->fill($this->data);
            $this->step = 3;
        } elseif ((int) $this->step === 3) {
            $this->data = $this->syncedFormData();
            $this->step = 4;
        }
    }

    public function goToPreviousStep(): void
    {
        $this->completed = false;
        $this->resetValidation();
        if ((int) $this->step > 1) {
            $this->step = (int) $this->step - 1;
        }
    }

    public function runPipeline(): void
    {
        // Livewire may hydrate step as string "4"; strict !== 4 would silently skip the run.
        if ((int) $this->step !== 4) {
            return;
        }

        $this->data = $this->syncedFormData();
        $memberId = (int) ($this->data['member_id'] ?? 0);

        try {
            app(QuickExternalImportPipelineService::class)->run($this->data);
            Notification::make()
                ->title('Pipeline completed')
                ->body('The external import flow finished successfully.')
                ->success()
                ->send();

            if ($memberId > 0) {
                $this->redirect(MemberResource::getUrl('view', ['record' => $memberId]));

                return;
            }

            $this->completed = true;
            $this->step = 1;
            $this->data['external_ref_id'] = 'MAN-QP-'.strtoupper(Str::random(10));
            $this->data['transaction_date'] = now()->format('Y-m-d H:i:s');
            $this->data['amount'] = null;
            $this->data['description'] = null;
            $this->data['import_notes'] = null;
            $this->data['member_id'] = null;
            $this->data['entry_direction'] = 'credit';
            $this->data['destination'] = 'contribution';
            $this->data['contribution_amount'] = null;
            $this->data['repayment_amount'] = null;
            $this->data['fund_notes'] = null;
            $this->data['collection_obligation_month'] = null;
            $this->data['collection_period_due_date'] = null;
            $this->data['collection_timing_override'] = 'auto';
            $this->data['loan_id'] = null;
            $this->data['repayment_date'] = null;
            $this->form->fill($this->data);
            $this->resetValidation();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            Notification::make()
                ->title('Pipeline failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
