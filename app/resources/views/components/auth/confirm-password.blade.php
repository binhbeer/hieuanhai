<?php

use Livewire\Component;

new class extends Component {};
?>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Confirm password')" :description="__('This is a secure area of the application. Please confirm your password before continuing.')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify options-route="passkey.confirm-options" submit-route="passkey.confirm" :label="__('Confirm with passkey')" :loading-label="__('Confirming...')" :separator="__('Or confirm with password')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6" x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf

            <flux:input name="password" :label="__('Password')" type="password" required autocomplete="current-password" :placeholder="__('Password')" viewable />

            <flux:button variant="primary" type="submit" class="w-full" x-bind:disabled="submitting" data-test="confirm-password-button">
                {{ __('Confirm') }}
            </flux:button>
        </form>
    </div>