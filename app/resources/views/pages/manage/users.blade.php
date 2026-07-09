<?php

use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manage users')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = 'all';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function toggleBan(int $id): void
    {
        $user = User::query()->findOrFail($id);

        abort_if($user->id === auth()->id() || $user->id === 1, 403);

        $user->update(['banned_at' => $user->banned_at ? null : now()]);
        Flux::toast(variant: 'success', text: $user->banned_at ? __('User banned.') : __('User unbanned.'));
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->withCount('apiKeys')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->whereNull('banned_at'))
            ->when($this->status === 'banned', fn ($query) => $query->whereNotNull('banned_at'))
            ->latest()
            ->paginate(20);
    }

    public function roleLabel(UserRole $role): string
    {
        return match ($role) {
            UserRole::Admin => 'Admin',
            UserRole::Mod => 'Mod',
            UserRole::User => 'User',
        };
    }
}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Manage users') }}</flux:heading>
			<flux:text variant="subtle">{{ __('List, edit roles, and ban accounts.') }}</flux:text>
		</div>
		<flux:button :href="route('manage.index')" variant="filled" wire:navigate>{{ __('Manage') }}</flux:button>
	</div>

	<flux:card class="space-y-4">
		<div class="grid gap-3 sm:grid-cols-[1fr_14rem]">
			<flux:input wire:model.live.debounce.300ms="search" :label="__('Search users')" :placeholder="__('Name or email')" />
			<flux:select wire:model.live="status" :label="__('Status')">
				<flux:select.option value="all">{{ __('All') }}</flux:select.option>
				<flux:select.option value="active">{{ __('Active') }}</flux:select.option>
				<flux:select.option value="banned">{{ __('Banned') }}</flux:select.option>
			</flux:select>
		</div>

		<div class="overflow-x-auto">
			<table class="w-full min-w-4xl text-left text-sm">
				<thead class="text-zinc-400">
					<tr class="border-b border-white/10">
						<th class="px-3 py-2 font-medium">User</th>
						<th class="px-3 py-2 font-medium">Role</th>
						<th class="px-3 py-2 font-medium">API key</th>
						<th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Created date') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Actions') }}</th>
					</tr>
				</thead>
				<tbody>
					@forelse ($this->users as $user)
						<tr class="border-b border-white/10" wire:key="manage-user-{{ $user->id }}">
							<td class="px-3 py-3 align-top">
								<div class="font-medium">{{ $user->name }}</div>
								<div class="text-xs text-zinc-400">{{ $user->email }}</div>
							</td>
							<td class="px-3 py-3 align-top">{{ $this->roleLabel($user->role) }}</td>
							<td class="px-3 py-3 align-top tabular-nums">{{ number_format($user->api_keys_count) }}</td>
							<td class="px-3 py-3 align-top">
								@if ($user->banned_at)
									<flux:badge size="sm">{{ __('Banned') }}</flux:badge>
								@else
									<flux:badge size="sm">Active</flux:badge>
								@endif
							</td>
							<td class="px-3 py-3 align-top">{{ $user->created_at?->format('Y-m-d H:i') }}</td>
							<td class="space-x-2 px-3 py-3 align-top">
								<flux:button :href="route('manage.users.edit', $user)" size="sm" variant="filled" wire:navigate>Edit</flux:button>
								@if ($user->id !== auth()->id() && $user->id !== 1)
									<flux:button type="button" size="sm" variant="danger" wire:click="toggleBan({{ $user->id }})" wire:confirm="{{ __('Change this user ban status?') }}">{{ $user->banned_at ? __('Unban') : __('Ban') }}</flux:button>
								@endif
							</td>
						</tr>
					@empty
						<tr>
							<td class="px-3 py-6 text-center text-zinc-400" colspan="6">{{ __('No users.') }}</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>

		<div>{{ $this->users->links() }}</div>
	</flux:card>
</section>
