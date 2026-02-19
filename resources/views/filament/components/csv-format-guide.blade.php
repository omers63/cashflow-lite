<div class="space-y-4">
    <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4">
        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-2">
            Required Columns
        </h4>
        <div class="font-mono text-xs text-gray-600 dark:text-gray-400 bg-white dark:bg-gray-900 rounded p-3 border border-gray-200 dark:border-gray-700">
            transaction_date, external_ref_id, amount, description
        </div>
    </div>

    <div class="space-y-2">
        <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
            Column Specifications
        </h4>
        <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>transaction_date:</strong> YYYY-MM-DD HH:MM:SS format (e.g., 2024-01-15 14:30:00)</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>external_ref_id:</strong> Unique reference number from bank statement</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>amount:</strong> Numeric value (e.g., 1234.56)</span>
            </li>
            <li class="flex items-start">
                <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span><strong>description:</strong> Transaction description (optional)</span>
            </li>
        </ul>
    </div>

    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 p-4 border border-blue-200 dark:border-blue-800">
        <h4 class="text-sm font-semibold text-blue-900 dark:text-blue-100 mb-2">
            Example CSV
        </h4>
        <pre class="font-mono text-xs text-blue-800 dark:text-blue-200 overflow-x-auto">2024-01-15 14:30:00,TXN-123456,1500.00,Customer Payment
2024-01-16 09:15:00,TXN-123457,750.50,Bank Transfer</pre>
    </div>
</div>
