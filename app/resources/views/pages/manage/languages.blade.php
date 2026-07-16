<?php

use App\Models\Category;
use App\Models\GeneratedMedia;
use App\Models\Setting;
use App\Models\Tag;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Quản lý ngôn ngữ')] class extends Component
{
    use WithPagination;

    public string $tab = 'site';
    public string $search = '';
    public bool $missingOnly = true;
    public bool $englishEnabled = false;
    public string $homeTitleEn = '';
    public string $siteDescriptionEn = '';
    public string $siteKeywordsEn = '';
    public ?string $editingType = null;
    public ?int $editingId = null;
    public string $sourceLabel = '';
    public string $nameEn = '';
    public string $descriptionEn = '';
    public string $slugEn = '';
    public bool $showEditor = false;

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->englishEnabled = AppSettings::bool('locales.en.enabled');
        $this->homeTitleEn = AppSettings::string('site.home_title.en');
        $this->siteDescriptionEn = AppSettings::string('site.description.en');
        $this->siteKeywordsEn = AppSettings::string('site.keywords.en');
    }

    public function updatedSearch(): void
    {
        $this->resetPage(pageName: $this->pageName());
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['site', 'categories', 'tags', 'images'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->search = '';
        $this->resetPage(pageName: $this->pageName());
    }

    public function saveSite(): void
    {
        $data = $this->validate([
            'homeTitleEn' => ['required', 'string', 'max:70'],
            'siteDescriptionEn' => ['required', 'string', 'max:160'],
            'siteKeywordsEn' => ['nullable', 'string', 'max:500'],
        ]);

        Setting::putValue('site.home_title.en', trim($data['homeTitleEn']));
        Setting::putValue('site.description.en', trim($data['siteDescriptionEn']));
        Setting::putValue('site.keywords.en', trim($data['siteKeywordsEn'] ?? ''));

        Flux::toast(variant: 'success', text: 'Đã lưu SEO tiếng Anh.');
    }

    public function toggleEnglish(bool $enabled): void
    {
        if ($enabled && ! $this->siteReady()) {
            $this->englishEnabled = false;
            Flux::toast(text: 'Cần nhập English home title và site description trước khi bật.');

            return;
        }

        $this->englishEnabled = $enabled;
        Setting::putValue('locales.en.enabled', $enabled);
        Flux::toast(variant: 'success', text: $enabled ? 'Đã bật tiếng Anh.' : 'Đã tắt tiếng Anh.');
    }

    public function edit(string $type, int $id): void
    {
        $model = $this->model($type, $id);
        $this->editingType = $type;
        $this->editingId = $id;
        $this->sourceLabel = (string) $model->getTranslationWithoutFallback($type === 'image' ? 'title' : 'name', 'vi');
        $this->nameEn = (string) $model->getTranslationWithoutFallback($type === 'image' ? 'title' : 'name', 'en');
        $this->descriptionEn = (string) $model->getTranslationWithoutFallback('description', 'en');
        $this->slugEn = $type === 'image' ? '' : (string) $model->slug_en;
        $this->showEditor = true;
    }

    public function saveTranslation(): void
    {
        if ($this->editingType === null || $this->editingId === null) {
            return;
        }

        $rules = [
            'nameEn' => ['required', 'string', 'max:80'],
            'descriptionEn' => ['required', 'string', 'max:160'],
        ];

        if ($this->editingType !== 'image') {
            $table = $this->editingType === 'category' ? 'categories' : 'tags';
            $rules['slugEn'] = ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/', Rule::unique($table, 'slug_en')->ignore($this->editingId)];
        }

        $data = $this->validate($rules);
        $model = $this->model($this->editingType, $this->editingId);
        $model
            ->setTranslation($this->editingType === 'image' ? 'title' : 'name', 'en', trim($data['nameEn']))
            ->setTranslation('description', 'en', trim($data['descriptionEn']));

        if ($this->editingType !== 'image') {
            $model->slug_en = trim($data['slugEn']);
        }

        $model->save();
        $this->cancelEdit();
        unset($this->records, $this->stats);
        Flux::toast(variant: 'success', text: 'Đã lưu bản dịch tiếng Anh.');
    }

    public function cancelEdit(): void
    {
        $this->reset('editingType', 'editingId', 'sourceLabel', 'nameEn', 'descriptionEn', 'slugEn', 'showEditor');
    }

    #[Computed]
    public function records(): LengthAwarePaginator
    {
        return match ($this->tab) {
            'categories' => Category::query()
                ->when($this->search !== '', fn ($query) => $query->where('name->vi', 'like', '%'.trim($this->search).'%'))
                ->when($this->missingOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('name->en')->orWhere('name->en', '')->orWhereNull('description->en')->orWhere('description->en', '')->orWhereNull('slug_en')->orWhere('slug_en', '')))
                ->orderBy('sort_order')->paginate(15, pageName: 'categoriesPage'),
            'tags' => Tag::query()
                ->when($this->search !== '', fn ($query) => $query->where('name->vi', 'like', '%'.trim($this->search).'%'))
                ->when($this->missingOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('name->en')->orWhere('name->en', '')->orWhereNull('description->en')->orWhere('description->en', '')->orWhereNull('slug_en')->orWhere('slug_en', '')))
                ->orderByDesc('id')->paginate(15, pageName: 'tagsPage'),
            'images' => GeneratedMedia::query()
                ->publiclyVisible()
                ->when($this->search !== '', fn ($query) => $query->where('title->vi', 'like', '%'.trim($this->search).'%'))
                ->when($this->missingOnly, fn ($query) => $query->where(fn ($query) => $query->whereNull('title->en')->orWhere('title->en', '')->orWhereNull('description->en')->orWhere('description->en', '')))
                ->latest('published_at')->paginate(15, pageName: 'imagesPage'),
            default => Category::query()->whereRaw('1 = 0')->paginate(15),
        };
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'categories' => [Category::query()->englishReady()->count(), Category::query()->count()],
            'tags' => [Tag::query()->englishReady()->count(), Tag::query()->count()],
            'images' => [GeneratedMedia::query()->publiclyVisible()->englishReady()->count(), GeneratedMedia::query()->publiclyVisible()->count()],
        ];
    }

    private function siteReady(): bool
    {
        return filled(AppSettings::string('site.home_title.en'))
            && filled(AppSettings::string('site.description.en'));
    }

    private function model(string $type, int $id): Category|Tag|GeneratedMedia
    {
        return match ($type) {
            'category' => Category::query()->findOrFail($id),
            'tag' => Tag::query()->findOrFail($id),
            'image' => GeneratedMedia::query()->findOrFail($id),
            default => abort(404),
        };
    }

    private function pageName(): string
    {
        return match ($this->tab) {
            'categories' => 'categoriesPage',
            'tags' => 'tagsPage',
            'images' => 'imagesPage',
            default => 'page',
        };
    }
}; ?>

<section class="mx-auto w-full max-w-7xl space-y-6 p-4 sm:p-6 lg:p-8">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1">
            <flux:heading size="xl">Quản lý ngôn ngữ</flux:heading>
            <flux:text variant="subtle">Tiếng Việt mặc định. Chỉ bật English khi nội dung SEO đã sẵn sàng.</flux:text>
        </div>
        <flux:switch wire:model.live="englishEnabled" wire:change="toggleEnglish($event.target.checked)" label="Bật English" />
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        @foreach ($this->stats as $label => [$ready, $total])
            <flux:card class="space-y-1">
                <flux:text variant="subtle">{{ ucfirst($label) }}</flux:text>
                <div class="text-2xl font-semibold tabular-nums">{{ $ready }}/{{ $total }}</div>
            </flux:card>
        @endforeach
    </div>

    <flux:tabs variant="segmented" scrollable>
        @foreach (['site' => 'Cấu hình', 'categories' => 'Danh mục', 'tags' => 'Tags', 'images' => 'Ảnh'] as $key => $label)
            <flux:tab wire:click="setTab('{{ $key }}')" :selected="$tab === $key">{{ $label }}</flux:tab>
        @endforeach
    </flux:tabs>

    @if ($tab === 'site')
        <flux:card>
            <form wire:submit="saveSite" class="space-y-5">
                <flux:input wire:model="homeTitleEn" label="English home title" maxlength="70" required />
                <flux:textarea wire:model="siteDescriptionEn" label="English site description" maxlength="160" rows="4" required />
                <flux:textarea wire:model="siteKeywordsEn" label="English keywords" maxlength="500" rows="3" />
                <div class="flex justify-end"><flux:button type="submit" variant="primary">Lưu SEO English</flux:button></div>
            </form>
        </flux:card>
    @else
        <div class="flex flex-wrap items-end gap-3">
            <flux:input wire:model.live.debounce.300ms="search" label="Tìm nội dung tiếng Việt" class="max-w-md" />
            <flux:switch wire:model.live="missingOnly" label="Chỉ mục thiếu English" />
        </div>

        <flux:table :paginate="$this->records">
            <flux:table.columns>
                <flux:table.column>ID</flux:table.column>
                <flux:table.column>Nội dung Việt</flux:table.column>
                <flux:table.column>Trạng thái</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->records as $record)
                    @php($type = $tab === 'categories' ? 'category' : ($tab === 'tags' ? 'tag' : 'image'))
                    @php($source = $record->getTranslationWithoutFallback($type === 'image' ? 'title' : 'name', 'vi'))
                    <flux:table.row :key="$type.'-'.$record->id">
                        <flux:table.cell>{{ $record->id }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $source ?: 'Chưa có tiêu đề' }}</flux:table.cell>
                        <flux:table.cell>
                            @if (($type === 'image' && $record->englishReady()) || ($type !== 'image' && filled($record->slug_en) && filled($record->getTranslationWithoutFallback($type === 'image' ? 'title' : 'name', 'en')) && filled($record->getTranslationWithoutFallback('description', 'en'))))
                                <flux:badge color="green">Đã dịch</flux:badge>
                            @else
                                <flux:badge color="amber">Thiếu English</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell><flux:button size="sm" wire:click="edit('{{ $type }}', {{ $record->id }})">Dịch</flux:button></flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="4">Không còn nội dung thiếu bản dịch.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="translation-editor" flyout variant="floating" class="md:w-lg" wire:model.self="showEditor" @close="cancelEdit">
        <form wire:submit="saveTranslation" class="space-y-5">
            <div>
                <flux:heading size="xl">Biên tập English</flux:heading>
                <flux:text class="mt-2" variant="subtle">Bản Việt: {{ $sourceLabel }}</flux:text>
            </div>
            <flux:input wire:model="nameEn" :label="$editingType === 'image' ? 'English title' : 'English name'" required />
            @if ($editingType !== 'image')
                <flux:input wire:model="slugEn" label="English slug" class:input="font-mono" required />
            @endif
            <flux:textarea wire:model="descriptionEn" label="English SEO description" maxlength="160" rows="5" required />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="cancelEdit">Hủy</flux:button>
                <flux:button type="submit" variant="primary">Lưu bản dịch</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
