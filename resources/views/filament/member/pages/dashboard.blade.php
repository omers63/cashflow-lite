<x-filament-panels::page>
    @php
        $member = $this->getMember();
        $summary = $this->getSummary();
        $recent = $this->getRecentTransactions();
    @endphp

    @if (! $member)
        <div class="flex flex-col items-center justify-center py-16 text-center">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5 mb-4">
                <x-filament::icon icon="heroicon-o-user-circle" class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">No member profile</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 max-w-sm">This account is not linked to a member. Please contact an administrator.</p>
        </div>
    @else
        {{-- ─── WELCOME HERO ──────────────────────────────────────────────────── --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-teal-500 via-teal-600 to-emerald-700 dark:from-teal-600 dark:via-teal-700 dark:to-emerald-800 p-6 sm:p-8 mb-8 shadow-lg">
            {{-- Animated background blobs --}}
            <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute bottom-0 left-0 h-32 w-32 rounded-full bg-white/5 blur-xl"></div>

            <div class="relative z-10">
                <p class="text-teal-100 text-sm font-medium mb-1">Welcome back</p>
                <h2 class="text-2xl sm:text-3xl font-bold text-white mb-1">{{ $member->user?->name ?? 'Member' }}</h2>
                <p class="text-teal-200/80 text-sm">
                    Member since {{ $member->membership_date?->format('F Y') ?? '—' }}
                    · {{ $member->user?->user_code }}
                </p>
            </div>
        </div>

        {{-- ─── SUMMARY STAT CARDS ────────────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('summary_cards'))
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
            {{-- Bank balance --}}
            <div class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-sm transition hover:shadow-md hover:border-teal-300 dark:hover:border-teal-600">
                <div class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-teal-500/5 dark:bg-teal-400/5 transition group-hover:scale-125"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-50 dark:bg-teal-500/10">
                            <x-filament::icon icon="heroicon-o-building-library" class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Balance</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                        ${{ number_format($summary['bank_balance'], 2) }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Member Bank Account</p>
                </div>
            </div>

            {{-- Fund balance --}}
            <div class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-sm transition hover:shadow-md hover:border-indigo-300 dark:hover:border-indigo-600">
                <div class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-indigo-500/5 dark:bg-indigo-400/5 transition group-hover:scale-125"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10">
                            <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Fund Balance</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                        ${{ number_format($summary['fund_balance'], 2) }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Member Fund Account</p>
                </div>
            </div>

            {{-- Outstanding loans --}}
            <div class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-sm transition hover:shadow-md hover:border-amber-300 dark:hover:border-amber-600">
                <div class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-amber-500/5 dark:bg-amber-400/5 transition group-hover:scale-125"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 dark:bg-amber-500/10">
                            <x-filament::icon icon="heroicon-o-currency-dollar" class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Outstanding Loans</p>
                    </div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                        ${{ number_format($summary['outstanding_loans'], 2) }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                        {{ $member->loans()->where('status', 'active')->count() }} active loan(s)
                    </p>
                </div>
            </div>

            {{-- Next payment --}}
            <div class="group relative overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5 shadow-sm transition hover:shadow-md hover:border-rose-300 dark:hover:border-rose-600">
                <div class="absolute top-0 right-0 h-24 w-24 translate-x-6 -translate-y-6 rounded-full bg-rose-500/5 dark:bg-rose-400/5 transition group-hover:scale-125"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-50 dark:bg-rose-500/10">
                            <x-filament::icon icon="heroicon-o-calendar-days" class="h-5 w-5 text-rose-600 dark:text-rose-400" />
                        </div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Next Payment</p>
                    </div>
                    @if($summary['next_payment_date'])
                        <p class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">
                            ${{ number_format($summary['next_payment_amount'] ?? 0, 2) }}
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            Due {{ $summary['next_payment_date']->format('M j, Y') }}
                        </p>
                    @else
                        <p class="text-lg font-semibold text-gray-400 dark:text-gray-500">—</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">No upcoming payments</p>
                    @endif
                </div>
            </div>
        </div>
        @endif

        {{-- ─── UPCOMING OBLIGATIONS ──────────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('upcoming_obligations'))
        @php $obligations = $this->getUpcomingObligations(); @endphp
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-500/10">
                        <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Upcoming Obligations</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">What you owe this month</p>
                    </div>
                </div>
            </div>
            <div class="p-5">
                @if (empty($obligations['items']))
                    <p class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">No upcoming obligations — you're all clear! ✨</p>
                @else
                    <div class="space-y-3">
                        @foreach ($obligations['items'] as $item)
                            <div class="flex items-center justify-between p-3.5 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 transition hover:bg-gray-100/70 dark:hover:bg-white/[.07]">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-{{ $item['color'] }}-50 dark:bg-{{ $item['color'] }}-500/10">
                                        <x-filament::icon :icon="$item['icon']" class="h-5 w-5 text-{{ $item['color'] }}-600 dark:text-{{ $item['color'] }}-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $item['type'] }}</p>
                                        @if (isset($item['due_date']))
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Due {{ $item['due_date']->format('M j, Y') }}</p>
                                        @endif
                                    </div>
                                </div>
                                <span class="text-base font-bold text-gray-900 dark:text-white">${{ number_format($item['amount'], 2) }}</span>
                            </div>
                        @endforeach

                        <div class="flex items-center justify-between p-4 rounded-xl mt-1 {{ $obligations['can_afford']
                            ? 'bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/10 dark:to-teal-500/10 border border-emerald-200 dark:border-emerald-700/40'
                            : 'bg-gradient-to-r from-rose-50 to-red-50 dark:from-rose-500/10 dark:to-red-500/10 border border-rose-200 dark:border-rose-700/40' }}">
                            <div>
                                <p class="text-sm font-semibold {{ $obligations['can_afford'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                    Total due this month
                                </p>
                                <p class="text-xs {{ $obligations['can_afford'] ? 'text-emerald-600/70 dark:text-emerald-400/60' : 'text-rose-600/70 dark:text-rose-400/60' }}">
                                    Balance: ${{ number_format($obligations['bank_balance'], 2) }}
                                    — {{ $obligations['can_afford'] ? '✓ Sufficient' : '✗ Insufficient' }}
                                </p>
                            </div>
                            <span class="text-xl font-bold {{ $obligations['can_afford'] ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                ${{ number_format($obligations['total_due'], 2) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ─── LOAN REPAYMENT PROGRESS ───────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('loan_progress'))
        @php $loanProgress = $this->getLoanProgress(); @endphp
        @if (count($loanProgress) > 0)
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-500/10">
                        <x-filament::icon icon="heroicon-o-chart-bar" class="h-4 w-4 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Loan Repayment Progress</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Visual progress for each active loan</p>
                    </div>
                </div>
            </div>
            <div class="p-5 space-y-4">
                @foreach ($loanProgress as $loan)
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-lg bg-violet-100 dark:bg-violet-500/20 px-2.5 py-1 text-xs font-semibold text-violet-700 dark:text-violet-300">
                                    {{ $loan['loan_id'] }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ $loan['remaining_term'] }} mo remaining</span>
                            </div>
                            <p class="text-sm font-bold text-gray-900 dark:text-white">
                                ${{ number_format($loan['total_paid'], 2) }}
                                <span class="text-gray-400 dark:text-gray-500 font-normal">/ ${{ number_format($loan['original_amount'], 2) }}</span>
                            </p>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5 overflow-hidden">
                            <div
                                class="h-2.5 rounded-full transition-all duration-700 ease-out {{ $loan['progress_percent'] >= 75 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : ($loan['progress_percent'] >= 40 ? 'bg-gradient-to-r from-teal-400 to-teal-500' : 'bg-gradient-to-r from-amber-400 to-amber-500') }}"
                                style="width: {{ min(100, $loan['progress_percent']) }}%"
                            ></div>
                        </div>
                        <div class="flex justify-between text-xs mt-2">
                            <span class="font-medium {{ $loan['progress_percent'] >= 75 ? 'text-emerald-600 dark:text-emerald-400' : ($loan['progress_percent'] >= 40 ? 'text-teal-600 dark:text-teal-400' : 'text-amber-600 dark:text-amber-400') }}">
                                {{ number_format($loan['progress_percent'], 1) }}% paid
                            </span>
                            <span class="text-gray-500 dark:text-gray-400">Remaining: ${{ number_format($loan['outstanding_balance'], 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
        @endif

        {{-- ─── TWO-COLUMN: ELIGIBILITY + CONTRIBUTIONS ──────────────────────── --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- LOAN ELIGIBILITY --}}
            @if($this->isWidgetEnabled('loan_eligibility'))
            @php $eligibility = $this->getLoanEligibility(); @endphp
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $eligibility['eligible'] ? 'bg-emerald-50 dark:bg-emerald-500/10' : 'bg-gray-100 dark:bg-white/5' }}">
                            <x-filament::icon icon="{{ $eligibility['eligible'] ? 'heroicon-o-check-badge' : 'heroicon-o-lock-closed' }}"
                                class="h-4 w-4 {{ $eligibility['eligible'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500' }}" />
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Loan Eligibility</h3>
                    </div>
                </div>
                <div class="p-5">
                    @if ($eligibility['eligible'])
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/10 dark:to-teal-500/10 border border-emerald-200/60 dark:border-emerald-600/20 mb-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/20">
                                <x-filament::icon icon="heroicon-o-check-circle" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div>
                                <p class="font-semibold text-emerald-700 dark:text-emerald-400">You are eligible!</p>
                                <p class="text-sm text-emerald-600/70 dark:text-emerald-400/60">Max borrowable: ${{ number_format($eligibility['max_amount'], 2) }}</p>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center gap-3 p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 mb-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-white/10">
                                <x-filament::icon icon="heroicon-o-lock-closed" class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                            </div>
                            <div>
                                <p class="font-semibold text-gray-600 dark:text-gray-400">Not yet eligible</p>
                                @if ($eligibility['has_active_loan'])
                                    <p class="text-xs text-amber-600 dark:text-amber-400 mt-0.5">Active loan must be repaid first</p>
                                @endif
                            </div>
                        </div>
                        @foreach ($eligibility['errors'] as $error)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1 flex items-start gap-1.5">
                                <span class="text-gray-300 dark:text-gray-600 mt-0.5">•</span> {{ $error }}
                            </p>
                        @endforeach
                    @endif

                    <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/5">
                        <div class="flex justify-between text-xs mb-2">
                            <span class="text-gray-500 dark:text-gray-400">Fund progress toward minimum</span>
                            <span class="font-medium text-gray-700 dark:text-gray-300">
                                ${{ number_format($eligibility['fund_balance'], 2) }} / ${{ number_format($eligibility['min_fund_required'], 2) }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2 overflow-hidden">
                            <div
                                class="h-2 rounded-full transition-all duration-700 {{ $eligibility['fund_progress'] >= 100 ? 'bg-gradient-to-r from-emerald-400 to-emerald-500' : 'bg-gradient-to-r from-teal-400 to-teal-500' }}"
                                style="width: {{ min(100, $eligibility['fund_progress']) }}%"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                            Membership: {{ $eligibility['membership_years'] }} yr(s) (min {{ $eligibility['min_membership_years'] }})
                        </p>
                    </div>
                </div>
            </div>
            @endif

            {{-- CONTRIBUTION HISTORY --}}
            @if($this->isWidgetEnabled('contribution_history'))
            @php $contribHistory = $this->getContributionHistory(); @endphp
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-500/10">
                            <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-4 w-4 text-sky-600 dark:text-sky-400" />
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Contribution History</h3>
                    </div>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 text-center">
                            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">This Year</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                                ${{ number_format($contribHistory['total_this_year'], 2) }}
                            </p>
                        </div>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 text-center">
                            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Avg Monthly</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                                ${{ number_format($contribHistory['avg_monthly'], 2) }}
                            </p>
                        </div>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 text-center">
                            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Streak</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                                {{ $contribHistory['streak'] }}
                                <span class="text-sm font-normal text-gray-400">mo</span>
                            </p>
                            @if ($contribHistory['streak'] >= 6)
                                <p class="text-xs text-emerald-600 dark:text-emerald-400 mt-0.5 font-medium">🔥 Excellent!</p>
                            @elseif ($contribHistory['streak'] >= 3)
                                <p class="text-xs text-sky-600 dark:text-sky-400 mt-0.5 font-medium">👍 Good going!</p>
                            @endif
                        </div>
                        <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 text-center">
                            <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Total Count</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">
                                {{ $contribHistory['total_contributions'] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- ─── FUND GROWTH CHART ─────────────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('fund_growth'))
        @php $fundGrowth = $this->getFundGrowthData(); @endphp
        @if (count($fundGrowth['labels']) > 1)
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10">
                        <x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Fund Growth</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Your fund account balance over time</p>
                    </div>
                </div>
            </div>
            <div class="p-5">
                <div style="height: 220px;">
                    <canvas id="fundGrowthChart"></canvas>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const isDark = document.documentElement.classList.contains('dark');
                const ctx = document.getElementById('fundGrowthChart').getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, 220);
                gradient.addColorStop(0, isDark ? 'rgba(99,102,241,0.25)' : 'rgba(99,102,241,0.12)');
                gradient.addColorStop(1, isDark ? 'rgba(99,102,241,0)' : 'rgba(99,102,241,0)');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: @json($fundGrowth['labels']),
                        datasets: [{
                            label: 'Fund Balance',
                            data: @json($fundGrowth['data']),
                            borderColor: '#6366f1',
                            backgroundColor: gradient,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            pointBackgroundColor: '#6366f1',
                            pointBorderColor: isDark ? '#1f2937' : '#fff',
                            pointBorderWidth: 2,
                            borderWidth: 2.5,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: isDark ? '#374151' : '#1f2937',
                                titleColor: '#f9fafb',
                                bodyColor: '#d1d5db',
                                cornerRadius: 8,
                                padding: 10,
                                callbacks: {
                                    label: function(ctx) {
                                        return '$' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits: 2});
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                ticks: {
                                    callback: function(val) { return '$' + val.toLocaleString(); },
                                    color: isDark ? '#6b7280' : '#9ca3af',
                                    font: { size: 11 }
                                },
                                grid: { color: isDark ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.04)' },
                                border: { display: false }
                            },
                            x: {
                                ticks: { color: isDark ? '#6b7280' : '#9ca3af', font: { size: 11 } },
                                grid: { display: false },
                                border: { display: false }
                            }
                        }
                    }
                });
            });
        </script>
        @endif
        @endif

        {{-- ─── COLLECTIONS STATUS ────────────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('collections_status'))
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-50 dark:bg-cyan-500/10">
                        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="h-4 w-4 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Collections Status</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $summary['period_label'] }}</p>
                    </div>
                </div>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 text-center">
                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Expected</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($summary['expected_total'], 2) }}</p>
                    </div>
                    <div class="p-4 rounded-xl bg-gray-50 dark:bg-white/5 border border-gray-100 dark:border-white/5 text-center">
                        <p class="text-xs font-medium text-gray-400 dark:text-gray-500 uppercase tracking-wider">Realized</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white mt-1">${{ number_format($summary['realized_total'], 2) }}</p>
                    </div>
                    <div class="p-4 rounded-xl border text-center {{ $summary['shortfall'] > 0
                        ? 'bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-700/30'
                        : 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-700/30' }}">
                        <p class="text-xs font-medium uppercase tracking-wider {{ $summary['shortfall'] > 0 ? 'text-rose-400 dark:text-rose-500' : 'text-emerald-400 dark:text-emerald-500' }}">Shortfall</p>
                        <p class="text-xl font-bold mt-1 {{ $summary['shortfall'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            ${{ number_format($summary['shortfall'], 2) }}
                        </p>
                        <p class="text-xs mt-0.5 {{ $summary['shortfall'] > 0 ? 'text-rose-500/70 dark:text-rose-400/50' : 'text-emerald-500/70 dark:text-emerald-400/50' }}">
                            {{ $summary['shortfall'] > 0 ? 'Funds needed' : 'All clear ✓' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ─── DEPENDANT SUMMARY ─────────────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('dependants'))
        @php $depSummary = $this->getDependantSummary(); @endphp
        @if ($depSummary['is_parent'])
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm mb-8 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-fuchsia-50 dark:bg-fuchsia-500/10">
                        <x-filament::icon icon="heroicon-o-user-group" class="h-4 w-4 text-fuchsia-600 dark:text-fuchsia-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Dependants</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Sub-accounts linked to you</p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Bank Balance</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Fund Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                        @foreach ($depSummary['dependants'] as $dep)
                            <tr class="hover:bg-gray-50 dark:hover:bg-white/[.02] transition">
                                <td class="px-6 py-3.5 font-medium text-gray-900 dark:text-white">
                                    <div class="flex items-center gap-2.5">
                                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-fuchsia-50 dark:bg-fuchsia-500/10">
                                            <x-filament::icon icon="heroicon-o-user" class="w-3.5 h-3.5 text-fuchsia-500 dark:text-fuchsia-400" />
                                        </div>
                                        {{ $dep['name'] }}
                                    </div>
                                </td>
                                <td class="px-6 py-3.5 text-right text-gray-700 dark:text-gray-300 font-medium">${{ number_format($dep['bank_balance'], 2) }}</td>
                                <td class="px-6 py-3.5 text-right text-gray-700 dark:text-gray-300 font-medium">${{ number_format($dep['fund_balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 dark:border-white/10 bg-gray-50/50 dark:bg-white/[.02]">
                            <td class="px-6 py-3 font-semibold text-gray-700 dark:text-gray-300">Total</td>
                            <td class="px-6 py-3 text-right font-bold text-gray-900 dark:text-white">${{ number_format($depSummary['total_allocated'], 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        @endif
        @endif

        {{-- ─── RECENT ACTIVITY ───────────────────────────────────────────────── --}}
        @if($this->isWidgetEnabled('recent_activity'))
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-white/5">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-white/5">
                        <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Recent Activity</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Latest movements on your accounts</p>
                    </div>
                </div>
            </div>

            @if($recent->isEmpty())
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-white/5 mb-3">
                        <x-filament::icon icon="heroicon-o-inbox" class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                    </div>
                    <p class="text-sm text-gray-400 dark:text-gray-500">No recent transactions</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">From</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">To</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-white/5">
                            @foreach($recent as $tx)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/[.02] transition">
                                    <td class="px-6 py-3.5 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                        {{ $tx->transaction_date?->format('M j, Y') ?? $tx->created_at?->format('M j, Y') }}
                                    </td>
                                    <td class="px-6 py-3.5">
                                        @php
                                            $typeColors = match(true) {
                                                in_array($tx->type, ['contribution', 'loan_repayment', 'allocation_from_parent']) => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
                                                in_array($tx->type, ['allocation_to_dependant']) => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400',
                                                default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $typeColors }}">
                                            {{ str_replace('_', ' ', ucfirst($tx->type)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3.5 text-gray-500 dark:text-gray-400 max-w-[150px] truncate">{{ $tx->from_account ?? '—' }}</td>
                                    <td class="px-6 py-3.5 text-gray-500 dark:text-gray-400 max-w-[150px] truncate">{{ $tx->to_account ?? '—' }}</td>
                                    <td class="px-6 py-3.5 text-right font-semibold whitespace-nowrap {{ $tx->amount < 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                                        ${{ number_format((float) $tx->amount, 2) }}
                                    </td>
                                    <td class="px-6 py-3.5 text-gray-400 dark:text-gray-500 max-w-[180px] truncate">{{ $tx->notes ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @endif
    @endif
</x-filament-panels::page>
