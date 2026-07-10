<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tìm kiếm')] class extends Component { }; ?>

@php($search = is_string(request('q')) ? trim(request('q')) : '')

<section class="mx-auto w-full max-w-7xl space-y-8 p-4 sm:p-6">
	<div class="mx-auto max-w-3xl pt-6 text-center sm:pt-12">
		<div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-white/10">
			<x-iconsax-two-search-normal class="size-7 text-zinc-500" />
		</div>
		<flux:heading level="1" size="xl">{{ __('Search images') }}</flux:heading>
		<flux:text class="mt-2" variant="subtle">{{ __('Search published images by title, prompt, category, or tag.') }}</flux:text>

		<form class="mt-6" action="{{ route('search.index') }}" method="GET">
			<flux:input.group>
				<flux:input name="q" :value="$search" placeholder="{{ __('Search images...') }}" aria-label="{{ __('Search images') }}" autofocus required>
                    <x-slot name="icon"><x-iconsax-two-search-normal class="size-5" /></x-slot>
                </flux:input>
				<flux:button type="submit" variant="primary">{{ __('Search') }}</flux:button>
			</flux:input.group>
		</form>
	</div>

	@if ($search !== '')
		<livewire:pages::gallery />
	@endif
</section>