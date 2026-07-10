<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manage categories')] class extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public string $slug = '';

    public string $status = 'active';

    public string $newName = '';

    public string $newSlug = '';

    public string $newStatus = 'active';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function edit(int $id): void
    {
        $category = Category::query()->findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->slug = $category->slug;
        $this->status = $category->status;
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'name', 'slug', 'status');
    }

    public function save(): void
    {
        if (! $this->editingId) {
            return;
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($this->editingId)],
            'status' => ['required', Rule::in(['active', 'hidden'])],
        ]);

        Category::query()->whereKey($this->editingId)->update($data);

        $this->cancelEdit();
        unset($this->categories);

        Flux::toast(variant: 'success', text: __('Category saved.'));
    }

    public function create(): void
    {
        $this->newSlug = $this->newSlug !== '' ? $this->newSlug : $this->slugify($this->newName);

        $data = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newSlug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')],
            'newStatus' => ['required', Rule::in(['active', 'hidden'])],
        ]);

        Category::create([
            'name' => $data['newName'],
            'slug' => $data['newSlug'],
            'status' => $data['newStatus'],
            'sort_order' => $this->nextSortOrder(),
        ]);

        $this->reset('newName', 'newSlug', 'newStatus');
        unset($this->categories);

        Flux::toast(variant: 'success', text: __('Category created.'));
    }

    public function updatedNewName(): void
    {
        if ($this->newSlug === '') {
            $this->newSlug = $this->slugify($this->newName);
        }
    }

    private function slugify(string $value): string
    {
        return Str::slug($value);
    }

    private function nextSortOrder(): int
    {
        return (int) Category::query()->max('sort_order') + 10;
    }

    public function updateStatus(int $id, string $status): void
    {
        if (! in_array($status, ['active', 'hidden'], true)) {
            return;
        }

        Category::query()->whereKey($id)->update(['status' => $status]);
        unset($this->categories);

        Flux::toast(variant: 'success', text: __('Category status updated.'));
    }

    public function sortCategory(int $id, int $position): void
    {
        $ordered = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('id')
            ->reject(fn (int $categoryId): bool => $categoryId === $id)
            ->values();

        $ordered->splice(max(0, min($position, $ordered->count())), 0, [$id]);

        foreach ($ordered as $index => $categoryId) {
            Category::query()->whereKey($categoryId)->update(['sort_order' => ($index + 1) * 10]);
        }

        unset($this->categories);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()
            ->withCount('images')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

}; ?>

<section class="mx-auto w-full max-w-6xl space-y-6 p-4 sm:p-6">
	<div class="flex flex-wrap items-start justify-between gap-3">
		<div class="space-y-1">
			<flux:heading size="xl">{{ __('Manage categories') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Sort, edit, and hide gallery categories.') }}</flux:text>
		</div>
		<flux:button :href="route('manage.index')" variant="filled" wire:navigate>{{ __('Manage') }}</flux:button>
	</div>

	<flux:card class="space-y-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<flux:heading size="lg">{{ __('New category') }}</flux:heading>
			<flux:text variant="subtle">{{ __('Add a new gallery category.') }}</flux:text>
		</div>

		<form wire:submit="create" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
			<flux:input wire:model="newName" :label="__('Name')" placeholder="{{ __('e.g. Posters & Visual') }}" />
			<flux:input wire:model="newSlug" :label="__('Slug')" class:input="font-mono" placeholder="{{ __('auto-generated') }}" />
			<flux:select wire:model="newStatus" :label="__('Status')">
				<flux:select.option value="active">{{ __('Active') }}</flux:select.option>
				<flux:select.option value="hidden">{{ __('Hidden') }}</flux:select.option>
			</flux:select>
			<flux:button type="submit" variant="primary" class="w-full lg:w-auto">{{ __('Create category') }}</flux:button>
		</form>
	</flux:card>

	<flux:card class="space-y-4">
		<div class="flex flex-wrap items-center justify-between gap-3">
			<flux:heading size="lg">{{ __('Categories') }}</flux:heading>
			<flux:text variant="subtle">{{ __(':count categories', ['count' => number_format($this->categories->count())]) }}</flux:text>
		</div>

		<div class="overflow-x-auto">
			<table class="w-full min-w-5xl text-left text-sm">
				<thead class="text-zinc-400">
					<tr class="border-b border-white/10">
						<th class="px-3 py-2 font-medium">{{ __('Order') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Name') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Slug') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Status') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Images') }}</th>
						<th class="px-3 py-2 font-medium">{{ __('Actions') }}</th>
					</tr>
				</thead>
				<tbody wire:sort="sortCategory">
					@forelse ($this->categories as $category)
						<tr class="border-b border-white/10" wire:key="manage-category-{{ $category->id }}" wire:sort:item="{{ $category->id }}">
							<td class="w-24 px-3 py-3 align-top">
								<button class="inline-flex cursor-grab items-center gap-2 rounded-lg px-2 py-1 text-zinc-400 hover:bg-white/10 hover:text-white" type="button" wire:sort:handle aria-label="{{ __('Drag to reorder') }}">
									<x-iconsax-two-menu class="size-4" />
									<span class="tabular-nums">{{ $category->sort_order }}</span>
								</button>
							</td>
							@if ($editingId === $category->id)
								<td class="px-3 py-3 align-top">
									<flux:input wire:model="name" size="sm" :label="__('Name')" />
								</td>
								<td class="px-3 py-3 align-top">
									<flux:input wire:model="slug" size="sm" :label="__('Slug')" />
								</td>
								<td class="w-44 px-3 py-3 align-top">
									<flux:select wire:model="status" size="sm" :label="__('Status')">
										<flux:select.option value="active">{{ __('Active') }}</flux:select.option>
										<flux:select.option value="hidden">{{ __('Hidden') }}</flux:select.option>
									</flux:select>
								</td>
								<td class="px-3 py-3 align-top tabular-nums">{{ number_format($category->images_count) }}</td>
								<td class="space-x-2 px-3 py-3 align-top">
									<flux:button type="button" size="sm" variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
									<flux:button type="button" size="sm" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
								</td>
							@else
								<td class="px-3 py-3 align-top">
									<div class="font-medium">{{ $category->name }}</div>
									@if ($category->status === 'hidden')
										<flux:text class="mt-1 text-xs" variant="subtle">{{ __('Hidden') }}</flux:text>
									@endif
								</td>
								<td class="px-3 py-3 align-top font-mono text-xs text-zinc-400">{{ $category->slug }}</td>
								<td class="w-44 px-3 py-3 align-top">
									<flux:select size="sm" :label="__('Status')" wire:change="updateStatus({{ $category->id }}, $event.target.value)">
										<flux:select.option value="active" :selected="$category->status === 'active'">{{ __('Active') }}</flux:select.option>
										<flux:select.option value="hidden" :selected="$category->status === 'hidden'">{{ __('Hidden') }}</flux:select.option>
									</flux:select>
								</td>
								<td class="px-3 py-3 align-top tabular-nums">{{ number_format($category->images_count) }}</td>
								<td class="px-3 py-3 align-top">
									<flux:button type="button" size="sm" variant="filled" wire:click="edit({{ $category->id }})">{{ __('Edit') }}</flux:button>
								</td>
							@endif
						</tr>
					@empty
						<tr>
							<td class="px-3 py-6 text-center text-zinc-400" colspan="6">{{ __('No categories.') }}</td>
						</tr>
					@endforelse
				</tbody>
			</table>
		</div>
	</flux:card>
</section>
