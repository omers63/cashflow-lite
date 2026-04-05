<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Quick Actions
        </x-slot>
        <x-slot name="description">
            Jump to the most common financial operations.
        </x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <a href="{{ $this->getMemberCreateUrl() }}"
               class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                <div>
                    <div class="font-medium">Create Member</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Add a new member and user account.</div>
                </div>
                <x-filament::icon icon="heroicon-o-user-plus" class="w-4 h-4 text-primary-500" />
            </a>

            <a href="{{ $this->getAccountManagementUrl() }}"
               class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                <div>
                    <div class="font-medium">Account Management</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Review master, external, and member accounts.</div>
                </div>
                <x-filament::icon icon="heroicon-o-wallet" class="w-4 h-4 text-primary-500" />
            </a>

            <a href="{{ $this->getImportBankUrl() }}"
               class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                <div>
                    <div class="font-medium">Import Bank Transactions</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Bring in external bank data.</div>
                </div>
                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-4 h-4 text-primary-500" />
            </a>

            <a href="{{ $this->getQuickImportPipelineUrl() }}"
               class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                <div>
                    <div class="font-medium">Quick import → member fund</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">One flow: external line, master, member bank, then contribution or loan repayment.</div>
                </div>
                <x-filament::icon icon="heroicon-o-bolt" class="w-4 h-4 text-primary-500" />
            </a>

            <a href="{{ $this->getLoanIndexUrl() }}"
               class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                <div>
                    <div class="font-medium">Review Loans</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Approve or monitor member loans.</div>
                </div>
                <x-filament::icon icon="heroicon-o-banknotes" class="w-4 h-4 text-primary-500" />
            </a>

            <a href="{{ $this->getDailyReconciliationUrl() }}"
               class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2 text-sm hover:border-primary-500 dark:hover:border-primary-500 transition-colors">
                <div>
                    <div class="font-medium">Run Daily Reconciliation</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">Verify that all balances match.</div>
                </div>
                <x-filament::icon icon="heroicon-o-calculator" class="w-4 h-4 text-primary-500" />
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

