<div class="overflow-x-auto max-h-96">
    @if (empty($schedule))
        <p class="text-sm text-gray-500 dark:text-gray-400 py-4">No schedule available.</p>
    @else
        <table class="w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                <tr>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">#</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Date</th>
                    <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Post Date</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Payment</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Principal</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Interest</th>
                    <th class="px-3 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                @foreach ($schedule as $row)
                    <tr>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['payment_number'] }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['payment_date'] }}</td>
                        <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $row['post_date'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">${{ number_format($row['payment_amount'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">${{ number_format($row['principal'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">${{ number_format($row['interest'], 2) }}</td>
                        <td class="px-3 py-2 text-right font-medium {{ $row['balance'] <= 0 ? 'text-success-600 dark:text-success-400' : 'text-gray-900 dark:text-gray-100' }}">
                            ${{ number_format($row['balance'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
