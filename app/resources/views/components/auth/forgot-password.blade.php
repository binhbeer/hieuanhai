<?php

use Livewire\Component;

new class extends Component {};
?>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6" x-data="accountForm" x-on:submit.prevent="submit">
            @csrf

            <flux:text x-cloak x-show="status" x-text="status" color="green" />
            <flux:text x-cloak x-show="errors.form" x-text="errors.form?.[0]" color="red" />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
            />
            <flux:text x-cloak x-show="errors.email" x-text="errors.email?.[0]" color="red" />

            <flux:button variant="primary" type="submit" class="w-full" x-bind:disabled="submitting" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-400">
            <span>{{ __('Or, return to') }}</span>
            <flux:link class="cursor-pointer" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('log in') }}</flux:link>
        </div>
    </div>
