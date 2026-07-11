<?php

use Livewire\Component;

new class extends Component {};
?>
    <div class="mt-4 flex flex-col gap-6">
        <flux:text class="text-center">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </flux:text>

        @if (session('status') == 'verification-link-sent')
            <flux:text class="text-center font-medium dark:text-green-400! text-green-600!">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </flux:text>
        @endif

        @if (session('status') == 'image-creation-requires-verification')
            <flux:text class="text-center font-medium text-amber-600! dark:text-amber-400!">
                {{ __('Please verify your email to continue receiving daily image generations after your registration day.') }}
            </flux:text>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full" x-bind:disabled="submitting">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}" x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf
                <flux:button variant="ghost" type="submit" class="text-sm cursor-pointer" x-bind:disabled="submitting" data-test="logout-button">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
