@props([
    'name' => 'aiDataConsent',
])

<div {{ $attributes->class('space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100') }}>
    <p class="leading-6">
        {{ __('To process this request, GenAnh sends your prompt and any images you upload or select to a third-party AI image service. That can include faces or likenesses visible in those photos. Data is used only to generate or edit the image you asked for.') }}
    </p>
    <flux:checkbox
        wire:model="{{ $name }}"
        :label="__('I allow GenAnh to send my prompt and uploaded images to the third-party AI image service for this request.')"
    />
    <flux:error name="{{ $name }}" />
    <p class="text-xs leading-5 opacity-90">
        {{ __('Details are in the') }}
        <a href="{{ route('legal.privacy') }}" wire:navigate class="font-medium underline underline-offset-2">{{ __('Privacy Policy') }}</a>
        {{ __('sections “Third-party AI image processing” and “Face data in uploaded photos”.') }}
    </p>
</div>
