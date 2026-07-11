<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component {
    public bool $show = false;

    public ?string $active = null;

    public string $securityPassword = '';

    public function mount(?string $initial = null): void
    {
        if ($initial) {
            $this->open($initial);
        }
    }

    private const COMPONENTS = [
        'auth.login',
        'auth.register',
        'auth.forgot-password',
        'auth.reset-password',
        'auth.verify-email',
        'auth.confirm-password',
        'auth.two-factor-challenge',
        'settings.profile',
        'settings.security',
        'settings.api-key',
        'settings.appearance',
    ];

    #[On('open-account-modal')]
    public function open(string $component): void
    {
        abort_unless(in_array($component, self::COMPONENTS, true), 404);

        if (str_starts_with($component, 'settings.')) {
            abort_unless(auth()->check(), 403);
        }

        $this->active = $component;
        $this->show = true;
    }

    public function confirmSecurity(): void
    {
        $this->validate(['securityPassword' => ['required', 'string']]);

        if (! Hash::check($this->securityPassword, auth()->user()->password)) {
            $this->addError('securityPassword', __('The provided password is incorrect.'));

            return;
        }

        session(['auth.password_confirmed_at' => time()]);
        $this->reset('securityPassword');
    }

    public function close(): void
    {
        $this->show = false;
        $this->active = null;
        $this->resetErrorBag();
    }
}; ?>

<div>
<flux:modal name="account" wire:model.self="show" class="w-full max-w-3xl" @close="close">
    @if ($active)
        @if ($active === 'settings.security' && (time() - (int) session('auth.password_confirmed_at', 0)) > config('auth.password_timeout'))
            <form wire:submit="confirmSecurity" class="mx-auto max-w-md space-y-6">
                <x-auth-header :title="__('Confirm password')" :description="__('This is a secure area of the application. Please confirm your password before continuing.')" />
                <flux:input wire:model="securityPassword" :label="__('Password')" type="password" required autocomplete="current-password" viewable />
                <flux:button type="submit" variant="primary" class="w-full">{{ __('Confirm') }}</flux:button>
            </form>
        @else
            @livewire($active, [], $active)
        @endif
    @endif
</flux:modal>
</div>
