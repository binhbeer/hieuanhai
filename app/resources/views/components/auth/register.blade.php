<?php

use Livewire\Component;

new class extends Component {};
?>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6" x-data="accountForm" x-on:submit.prevent="submit">
            @csrf
            <flux:text x-cloak x-show="errors.form" x-text="errors.form?.[0]" color="red" />

            <!-- Name -->
            <flux:input
                name="name"
                :label="__('Name')"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />
            <flux:text x-cloak x-show="errors.name" x-text="errors.name?.[0]" color="red" />

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
            />
            <flux:text x-cloak x-show="errors.email" x-text="errors.email?.[0]" color="red" />

            <!-- Password -->
            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
            <flux:text x-cloak x-show="errors.password" x-text="errors.password?.[0]" color="red" />

            <!-- Confirm Password -->
            <flux:input
                name="password_confirmation"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />

            <flux:text class="text-center text-sm" variant="subtle">
                {{ __('By creating an account, you agree to the') }}
                <flux:link :href="route('legal.terms')" wire:navigate>{{ __('Terms of Service') }}</flux:link>
                {{ __('and acknowledge the') }}
                <flux:link :href="route('legal.privacy')" wire:navigate>{{ __('Privacy Policy') }}</flux:link>.
            </flux:text>

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary" class="w-full" x-bind:disabled="submitting" data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Already have an account?') }}</span>
            <flux:link class="cursor-pointer" x-data x-on:click="$dispatch('open-account-modal', { component: 'auth.login' })">{{ __('Log in') }}</flux:link>
        </div>
    </div>
