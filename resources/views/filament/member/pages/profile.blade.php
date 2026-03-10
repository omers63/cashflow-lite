<x-filament-panels::page>
    @php
        $user = auth()->user();
        $member = $this->getMember();
    @endphp

    @if (! $member)
        <x-filament::section>
            <x-slot name="heading">No member profile</x-slot>
            <x-slot name="description">This account is not linked to a member. Please contact an administrator.</x-slot>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Profile</x-slot>
            <x-slot name="description">Your basic profile and membership information.</x-slot>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $user->name }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Member code</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $user->user_code }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $user->email }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Phone</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $user->phone ?? '—' }}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Membership since</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ $member->membership_date?->format('M j, Y') ?? '—' }}
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Role</p>
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ $member->isParentMember() ? 'Parent member' : ($member->isDependantMember() ? 'Dependant' : 'Member') }}
                    </p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Security</x-slot>
            <x-slot name="description">For any changes to your password or contact details, please contact an administrator (self-service security settings can be added later).</x-slot>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                In a future iteration, this page can include password change, notification preferences,
                and two-factor authentication settings.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>

