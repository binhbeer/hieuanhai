<?php

use App\Concerns\ProfileValidationRules;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use ProfileValidationRules, WithFileUploads;

    public string $name = '';

    public string $email = '';

    public $avatar;

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function updateAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = Auth::user();
        $media = $user->addMedia($this->avatar->getRealPath())
            ->usingFileName($this->avatar->hashName())
            ->toMediaCollection('avatar');
        $user->avatar_path = $media->getPathRelativeToRoot();
        $user->save();

        $this->reset('avatar');
        Flux::toast(variant: 'success', text: __('Avatar updated.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('home', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

}; ?>

<section class="w-full">
    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout active="profile" :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateAvatar" class="my-6 flex items-center gap-4">
            <flux:file-upload wire:model="avatar" accept="image/jpeg,image/png,image/webp" aria-label="{{ __('Avatar') }}">
                <div class="relative flex size-20 cursor-pointer items-center justify-center overflow-hidden rounded-full border border-zinc-200 bg-zinc-100 transition-colors hover:border-zinc-300 hover:bg-zinc-200 in-data-dragging:bg-zinc-200 in-data-loading:opacity-60 dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15 dark:in-data-dragging:bg-white/15">
                    @if ($avatar)
                        <img src="{{ $avatar->temporaryUrl() }}" class="size-full object-cover" alt="{{ __('Avatar preview') }}" />
                    @elseif (auth()->user()->avatar_path)
                        <img src="{{ Storage::url(auth()->user()->avatar_path) }}" class="size-full object-cover" alt="{{ auth()->user()->name }}" />
                    @else
                        <flux:icon name="user" variant="solid" class="size-8 text-zinc-500 dark:text-zinc-400" />
                    @endif

                    <div class="absolute bottom-0 right-0 rounded-full bg-white dark:bg-zinc-800">
                        <flux:icon name="arrow-up-circle" variant="solid" class="size-6 text-zinc-500 dark:text-zinc-400" />
                    </div>
                </div>
            </flux:file-upload>

            <div class="flex-1 space-y-2">
                <flux:heading size="sm">{{ __('Avatar') }}</flux:heading>
                <flux:text size="sm">{{ __('Choose a JPG, PNG, or WebP image up to 2 MB.') }}</flux:text>
                <flux:error name="avatar" />
                <flux:button type="submit" size="sm" variant="primary" wire:loading.attr="disabled" wire:target="avatar,updateAvatar" :disabled="! $avatar">
                    {{ __('Save') }}
                </flux:button>
            </div>
        </form>

        <flux:separator />

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                @if ($this->hasUnverifiedEmail)
                    <div>
                        <flux:text class="mt-4">
                            {{ __('Your email address is unverified.') }}

                            <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </flux:link>
                        </flux:text>

                        @if (session('status') === 'verification-link-sent')
                            <flux:text class="mt-2 font-medium dark:text-green-400! text-green-600!">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <flux:button variant="primary" type="submit" class="w-full" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>

            </div>
        </form>

        <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
