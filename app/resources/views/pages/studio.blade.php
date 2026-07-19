<?php

use App\Models\GeneratedMedia;
use App\Models\StudioProject;
use App\Services\ImagePromptService;
use App\Services\StudioImageService;
use App\Support\AppSettings;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Studio')] class extends Component
{
    use WithFileUploads;

    #[Url]
    public string $view = 'plaza';

    #[Url]
    public ?int $project = null;

    public bool $showWizard = false;

    public string $tool = '';

    public int $step = 1;

    public mixed $newPhotos = [];

    public mixed $newProductPhoto = null;

    public mixed $newLogoPhoto = null;

    public mixed $newModelPhoto = null;

    public mixed $newAdditionalProductPhotos = [];

    public string $projectName = '';

    public string $productName = '';

    public string $aspectRatio = '4:5';

    public string $imageModel = '';

    public string $language = 'vi';

    public array $imageTypes = ['hero', 'close-up', 'lifestyle'];

    public string $customImageType = '';

    public string $notes = '';

    public string $posterTopic = '';

    public string $posterCopy = '';

    public bool $autoWrite = true;

    public string $posterStyle = '';

    public bool $autoStyle = true;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $this->imageModel = AppSettings::defaultImageModel();

        if (! in_array($this->view, ['plaza', 'projects'], true)) {
            $this->view = 'plaza';
        }

        if ($this->project && Auth::check()) {
            $project = StudioProject::query()->where('user_id', Auth::id())->find($this->project);

            if ($project && $project->submitted_at === null) {
                $this->resumeProject($project->id);
            }
        }

        if (request()->boolean('wizard') && ! $this->showWizard) {
            $requestedTool = request()->query('tool');
            $this->openTool(is_string($requestedTool) && in_array($requestedTool, StudioProject::TOOLS, true)
                ? $requestedTool
                : StudioProject::TOOLS[0]);
        }
    }

    public function openWizard(): void
    {
        $this->openTool(StudioProject::TOOLS[0]);
    }

    public function openTool(string $tool): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');
            $this->js("window.dispatchEvent(new CustomEvent('open-account-modal', { detail: { component: 'auth.login' } }))");

            return;
        }

        if (! in_array($tool, StudioProject::TOOLS, true)) {
            return;
        }

        $this->resetWizard();
        $this->tool = $tool;
        $this->projectName = '';
        $this->showWizard = true;
    }

    public function closeWizard(): void
    {
        $this->showWizard = false;
        $this->reset('newPhotos', 'newProductPhoto', 'newLogoPhoto', 'newModelPhoto', 'newAdditionalProductPhotos');
    }

    public function updatedNewPhotos(): void
    {
        if (! Auth::check() || $this->tool !== 'marketing-poster') {
            $this->newPhotos = [];

            return;
        }

        $photos = is_array($this->newPhotos) ? $this->newPhotos : [$this->newPhotos];
        $remaining = max(0, AppSettings::maxReferencePhotos() - count($this->inputPaths()));
        $photos = array_slice(array_values(array_filter($photos)), 0, $remaining);

        if ($photos === []) {
            $this->newPhotos = [];

            return;
        }

        $this->validate([
            'newPhotos' => ['array', 'max:'.$remaining],
            'newPhotos.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);

        $project = $this->draft();
        $paths = $this->inputPaths();

        foreach ($photos as $photo) {
            $path = $photo->store('studio-projects/'.$project->id, 'public');

            if (! is_string($path)) {
                $this->errorMessage = __('Could not save uploaded image.');
                break;
            }

            $paths[] = $path;
        }

        $project->update(['input_paths' => ['references' => array_slice($paths, 0, AppSettings::maxReferencePhotos())]]);
        $this->newPhotos = [];
        $this->errorMessage = null;
        $this->resetValidation('newPhotos');
        unset($this->draftProject);
    }

    public function updatedNewProductPhoto(): void
    {
        $this->storeProductUploads('newProductPhoto', 'product', 1);
    }

    public function updatedNewLogoPhoto(): void
    {
        $this->storeProductUploads('newLogoPhoto', 'logo', 1);
    }

    public function updatedNewModelPhoto(): void
    {
        $this->storeProductUploads('newModelPhoto', 'model', 1);
    }

    public function updatedNewAdditionalProductPhotos(): void
    {
        $this->storeProductUploads('newAdditionalProductPhotos', 'additional_products', 2);
    }

    public function removeInput(int $index): void
    {
        $project = $this->ownedProject();
        $paths = $this->inputPaths();
        $path = $paths[$index] ?? null;

        if (! $project || ! is_string($path)) {
            return;
        }

        Storage::disk('public')->delete($path);
        unset($paths[$index]);
        $project->update(['input_paths' => ['references' => array_values($paths)]]);
        unset($this->draftProject);
    }

    public function removeProductInput(string $role, int $index = 0): void
    {
        if (! in_array($role, ['product', 'logo', 'model', 'additional_products'], true)) {
            return;
        }

        $project = $this->ownedProject();

        if (! $project || $project->tool !== 'product-detail') {
            return;
        }

        $inputs = $this->productInputPaths($project);
        $path = $role === 'additional_products' ? ($inputs[$role][$index] ?? null) : $inputs[$role];

        if (! is_string($path)) {
            return;
        }

        Storage::disk('public')->delete($path);

        if ($role === 'additional_products') {
            unset($inputs[$role][$index]);
            $inputs[$role] = array_values($inputs[$role]);
        } else {
            $inputs[$role] = null;
        }

        $project->update(['input_paths' => $inputs]);
        unset($this->draftProject);
    }

    public function saveDraft(): void
    {
        if (! Auth::check() || ! in_array($this->tool, StudioProject::TOOLS, true)) {
            return;
        }

        $project = $this->draft();
        $name = trim($this->projectName);
        $project->update([
            'name' => $name !== ''
                ? Str::limit($name, 255, '')
                : (filled($project->name) ? $project->name : $this->defaultProjectName()),
            'form_data' => $this->formData(),
        ]);
        unset($this->draftProject);
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validateBasics();
        }

        if ($this->step === 2 && $this->tool === 'product-detail') {
            $this->validateImageTypes();
        }

        $this->saveDraft();
        $this->step = min($this->lastStep(), $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function addCustomImageType(): void
    {
        $value = trim($this->customImageType);

        if ($value === '') {
            return;
        }

        $this->validate(['customImageType' => ['string', 'max:80']]);
        $key = 'custom:'.$value;

        if (! in_array($key, $this->imageTypes, true) && count($this->imageTypes) < 6) {
            $this->imageTypes[] = $key;
        }

        $this->customImageType = '';
        $this->saveDraft();
    }

    public function submit(StudioImageService $generator, ImagePromptService $editor): void
    {
        $this->validateBasics();

        if ($this->tool === 'product-detail') {
            $this->validateImageTypes();
        }

        $this->saveDraft();
        $project = $this->ownedProject();

        if (! $project) {
            return;
        }

        try {
            if (! $this->isEditingProject() || trim($this->projectName) === '') {
                $this->assignGeneratedProjectName($editor, $project);
            }

            $images = $generator->createBatch(
                request(),
                $project,
                $this->generationOutputs(),
                $this->aspectRatio,
                '1k',
                model: $this->imageModel,
            );
            $this->showWizard = false;
            $this->view = 'projects';
            $this->project = $project->id;
            $this->dispatch('image-usage-updated');
            Flux::toast(variant: 'success', text: __('Project queued successfully.'));

            if ($images->isNotEmpty()) {
                $this->redirectRoute('studio.index', ['view' => 'projects', 'project' => $project->id], navigate: true);
            }
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = __('Could not create an image right now. Please try again later.');
        }
    }

    public function resumeProject(int $id): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $project = StudioProject::query()->where('user_id', Auth::id())->find($id);

        if (! $project) {
            return;
        }

        $this->project = $project->id;
        $this->view = 'projects';
        $this->loadProject($project);
        $this->showWizard = true;
    }

    public function deleteDraft(int $id): void
    {
        $project = StudioProject::query()
            ->where('user_id', Auth::id())
            ->whereNull('submitted_at')
            ->find($id);

        if (! $project) {
            return;
        }

        Storage::disk('public')->delete(collect($project->input_paths ?? [])->flatten()->filter()->all());
        $project->delete();

        if ($this->project === $id) {
            $this->project = null;
            $this->showWizard = false;
        }
    }

    public function refreshProject(): void
    {
        unset($this->projects, $this->recentProjects, $this->selectedProject);
    }

    #[Computed]
    public function projects()
    {
        if (! Auth::check()) {
            return collect();
        }

        return StudioProject::query()
            ->where('user_id', Auth::id())
            ->with(['media' => fn ($query) => $query->latest()])
            ->latest('updated_at')
            ->get();
    }

    #[Computed]
    public function recentProjects()
    {
        return $this->projects->take(6);
    }

    #[Computed]
    public function selectedProject(): ?StudioProject
    {
        if (! Auth::check() || ! $this->project) {
            return null;
        }

        return StudioProject::query()
            ->where('user_id', Auth::id())
            ->with(['media' => fn ($query) => $query->latest()])
            ->find($this->project);
    }

    #[Computed]
    public function draftProject(): ?StudioProject
    {
        return $this->ownedProject();
    }

    public function imageUrl(GeneratedMedia $image, string $size = 'sm'): ?string
    {
        return app(ImagePromptService::class)->imageUrl($image, $size);
    }

    public function toolLabel(string $tool): string
    {
        return match ($tool) {
            'product-detail' => __('Product detail images'),
            'marketing-poster' => __('Marketing poster'),
            default => $tool,
        };
    }

    public function projectProgress(StudioProject $project): string
    {
        if ($project->submitted_at === null) {
            return __('Draft');
        }

        $total = $project->media->count();
        $done = $project->media->whereIn('status', ['succeeded', 'failed'])->count();

        return $done === $total ? __('Completed') : __('Creating :done/:total', ['done' => $done, 'total' => $total]);
    }

    public function mediaVersion(GeneratedMedia $image): int
    {
        return max(1, (int) data_get($image->request_meta, 'version', 1));
    }

    public function latestVersion(StudioProject $project): int
    {
        return (int) $project->media
            ->map(fn (GeneratedMedia $image): int => $this->mediaVersion($image))
            ->max();
    }

    private function draft(): StudioProject
    {
        $project = $this->ownedProject();

        if ($project) {
            return $project;
        }

        $project = StudioProject::create([
            'user_id' => Auth::id(),
            'tool' => $this->tool,
            'name' => trim($this->projectName) !== '' ? Str::limit(trim($this->projectName), 255, '') : $this->defaultProjectName(),
            'form_data' => $this->formData(),
            'input_paths' => $this->tool === 'product-detail'
                ? ['product' => null, 'logo' => null, 'model' => null, 'additional_products' => []]
                : ['references' => []],
        ]);
        $this->project = $project->id;
        unset($this->draftProject, $this->projects, $this->recentProjects);

        return $project;
    }

    private function ownedProject(): ?StudioProject
    {
        if (! Auth::check() || ! $this->project) {
            return null;
        }

        return StudioProject::query()
            ->where('user_id', Auth::id())
            ->find($this->project);
    }

    private function loadProject(StudioProject $project): void
    {
        $data = is_array($project->form_data) ? $project->form_data : [];
        $this->tool = $project->tool;
        $this->projectName = $project->name;
        $this->productName = (string) ($data['product_name'] ?? '');
        $this->aspectRatio = (string) ($data['aspect_ratio'] ?? '4:5');
        $savedModel = is_string($data['image_model'] ?? null) ? $data['image_model'] : null;
        $this->imageModel = in_array($savedModel, AppSettings::enabledImageModels(), true) ? $savedModel : AppSettings::defaultImageModel();
        $this->language = (string) ($data['language'] ?? 'vi');
        $this->imageTypes = is_array($data['image_types'] ?? null) ? $data['image_types'] : ['hero', 'close-up', 'lifestyle'];
        $this->notes = (string) ($data['notes'] ?? '');
        $this->posterTopic = (string) ($data['poster_topic'] ?? '');
        $this->posterCopy = (string) ($data['poster_copy'] ?? '');
        $this->autoWrite = (bool) ($data['auto_write'] ?? true);
        $this->posterStyle = (string) ($data['poster_style'] ?? '');
        $this->autoStyle = (bool) ($data['auto_style'] ?? true);
        $this->step = 1;
        $this->errorMessage = null;
    }

    private function resetWizard(): void
    {
        $this->project = null;
        $this->step = 1;
        $this->reset('newPhotos', 'newProductPhoto', 'newLogoPhoto', 'newModelPhoto', 'newAdditionalProductPhotos');
        $this->projectName = '';
        $this->productName = '';
        $this->aspectRatio = '4:5';
        $this->imageModel = AppSettings::defaultImageModel();
        $this->language = 'vi';
        $this->imageTypes = ['hero', 'close-up', 'lifestyle'];
        $this->customImageType = '';
        $this->notes = '';
        $this->posterTopic = '';
        $this->posterCopy = '';
        $this->autoWrite = true;
        $this->posterStyle = '';
        $this->autoStyle = true;
        $this->errorMessage = null;
        $this->resetValidation();
        unset($this->draftProject);
    }

    public function isEditingProject(): bool
    {
        return $this->ownedProject()?->submitted_at !== null;
    }

    private function assignGeneratedProjectName(ImagePromptService $editor, StudioProject $project): void
    {
        $path = $this->primaryReferencePath($project);

        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            $name = $this->defaultProjectName();
            $this->projectName = $name;
            $project->update(['name' => $name]);

            return;
        }

        $hint = $this->tool === 'product-detail'
            ? trim($this->productName)
            : trim($this->posterTopic);

        try {
            $name = $editor->projectNameFromImage(
                new UploadedFile(
                    Storage::disk('public')->path($path),
                    basename($path),
                ),
                $this->language,
                $hint,
            );
        } catch (\Throwable $e) {
            report($e);
            $name = $this->defaultProjectName();
        }

        $this->projectName = $name;
        $project->update(['name' => $name]);
    }

    private function primaryReferencePath(StudioProject $project): ?string
    {
        if ($project->tool === 'product-detail') {
            $product = $this->productInputPaths($project)['product'];

            return is_string($product) ? $product : null;
        }

        $references = collect($project->input_paths['references'] ?? [])
            ->filter(fn (mixed $path): bool => is_string($path))
            ->values();

        return $references->first();
    }

    private function inputPaths(): array
    {
        return collect($this->draftProject?->input_paths ?? [])
            ->flatten()
            ->filter(fn (mixed $path): bool => is_string($path))
            ->values()
            ->all();
    }

    /**
     * @return array{product: string|null, logo: string|null, model: string|null, additional_products: array<int, string>}
     */
    public function productInputPaths(?StudioProject $project = null): array
    {
        $paths = $project?->input_paths ?? $this->draftProject?->input_paths ?? [];
        $paths = is_array($paths) ? $paths : [];
        $legacy = collect($paths['references'] ?? [])->filter(fn (mixed $path): bool => is_string($path))->values()->all();
        $product = is_string($paths['product'] ?? null) ? $paths['product'] : ($legacy[0] ?? null);
        $additional = collect($paths['additional_products'] ?? array_slice($legacy, 1))
            ->filter(fn (mixed $path): bool => is_string($path))
            ->take(2)
            ->values()
            ->all();

        return [
            'product' => $product,
            'logo' => is_string($paths['logo'] ?? null) ? $paths['logo'] : null,
            'model' => is_string($paths['model'] ?? null) ? $paths['model'] : null,
            'additional_products' => $additional,
        ];
    }

    private function storeProductUploads(string $property, string $role, int $limit): void
    {
        $uploads = $this->{$property};
        $uploads = is_array($uploads) ? $uploads : [$uploads];
        $uploads = array_values(array_filter($uploads));

        if (! Auth::check() || $this->tool !== 'product-detail' || $uploads === []) {
            $this->{$property} = $property === 'newAdditionalProductPhotos' ? [] : null;

            return;
        }

        $this->validate([
            $property => $property === 'newAdditionalProductPhotos' ? ['array', 'max:'.$limit] : ['required', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
            $property.'.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);

        $project = $this->draft();
        $inputs = $this->productInputPaths($project);
        $existing = $role === 'additional_products' ? $inputs[$role] : [];
        $remaining = max(0, $limit - count($existing));

        foreach (array_slice($uploads, 0, $remaining) as $upload) {
            $path = $upload->store('studio-projects/'.$project->id, 'public');

            if (! is_string($path)) {
                $this->errorMessage = __('Could not save uploaded image.');

                break;
            }

            if ($role === 'additional_products') {
                $existing[] = $path;
            } else {
                $oldPath = $inputs[$role];

                if (is_string($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $inputs[$role] = $path;
            }
        }

        if ($role === 'additional_products') {
            $inputs[$role] = array_slice($existing, 0, $limit);
        }

        $project->update(['input_paths' => $inputs]);
        $this->{$property} = $property === 'newAdditionalProductPhotos' ? [] : null;
        $this->errorMessage = null;
        $this->resetValidation($property);
        unset($this->draftProject);
    }

    private function formData(): array
    {
        return [
            'product_name' => trim($this->productName),
            'aspect_ratio' => $this->aspectRatio,
            'image_model' => $this->imageModel,
            'language' => $this->language,
            'image_types' => array_values(array_unique($this->imageTypes)),
            'notes' => trim($this->notes),
            'poster_topic' => trim($this->posterTopic),
            'poster_copy' => trim($this->posterCopy),
            'auto_write' => $this->autoWrite,
            'poster_style' => trim($this->posterStyle),
            'auto_style' => $this->autoStyle,
        ];
    }

    private function validateBasics(): void
    {
        $rules = [
            'projectName' => ['nullable', 'string', 'max:255'],
            'aspectRatio' => ['required', 'string', 'in:1:1,3:4,4:3,4:5,9:16,16:9'],
            'imageModel' => ['required', 'string', 'max:120', Rule::in(AppSettings::enabledImageModels())],
            'language' => ['required', 'string', 'in:vi,en'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($this->tool === 'product-detail') {
            $rules['productName'] = ['nullable', 'string', 'max:255'];

            $product = $this->productInputPaths()['product'];

            if (! is_string($product) || ! Storage::disk('public')->exists($product)) {
                $this->addError('newProductPhoto', __('Product image is required.'));
                throw ValidationException::withMessages(['newProductPhoto' => __('Product image is required.')]);
            }
        } else {
            $rules['posterTopic'] = ['required', 'string', 'max:500'];
            $rules['posterCopy'] = ['nullable', 'string', 'max:1200'];
            $rules['posterStyle'] = ['nullable', 'string', 'max:255'];
        }

        $this->validate($rules);
    }

    private function validateImageTypes(): void
    {
        $this->validate([
            'imageTypes' => ['required', 'array', 'min:1', 'max:6'],
            'imageTypes.*' => ['string', 'max:87'],
        ]);
    }

    public function lastStep(): int
    {
        return $this->tool === 'product-detail' ? 3 : 3;
    }

    private function defaultProjectName(): string
    {
        return $this->tool === 'product-detail' ? __('Product detail images') : __('Marketing poster');
    }

    private function generationOutputs(): array
    {
        return $this->tool === 'product-detail' ? $this->productOutputs() : [$this->posterOutput()];
    }

    private function productOutputs(): array
    {
        $labels = [
            'hero' => 'Hero Banner',
            'close-up' => 'Close-up Details',
            'lifestyle' => 'Lifestyle Scene',
            'material' => 'Material',
            'how-to' => 'How to Use',
            'brand' => 'Brand Closing',
        ];

        return collect($this->imageTypes)->map(function (string $type) use ($labels): array {
            $label = Str::startsWith($type, 'custom:') ? Str::after($type, 'custom:') : ($labels[$type] ?? $type);
            $language = $this->language === 'vi' ? 'Vietnamese' : 'English';
            $prompt = implode("\n", array_filter([
                'Create a professional ecommerce product detail image.',
                'Output type: '.$label.'.',
                trim($this->productName) !== '' ? 'Product name: '.trim($this->productName).'.' : null,
                'Keep the exact product identity, shape, colors, materials, logo, and proportions. Do not invent a different product.',
                'Write any necessary product copy in '.$language.'. Keep text short and legible.',
                'Use a '.$this->aspectRatio.' composition.',
                trim($this->notes) !== '' ? 'Additional notes: '.trim($this->notes) : null,
            ]));

            return ['prompt' => $prompt, 'title' => $this->defaultProjectName().' — '.$label, 'output_type' => $type];
        })->values()->all();
    }

    private function posterOutput(): array
    {
        $language = $this->language === 'vi' ? 'Vietnamese' : 'English';
        $copy = ! $this->autoWrite && trim($this->posterCopy) !== ''
            ? 'Use this exact poster copy: '.trim($this->posterCopy)
            : 'Write concise, compelling poster copy suitable for the topic.';
        $style = ! $this->autoStyle && trim($this->posterStyle) !== ''
            ? 'Visual style: '.trim($this->posterStyle).'.'
            : 'Choose a polished commercial style appropriate for the topic.';
        $prompt = implode("\n", array_filter([
            'Create a polished marketing poster.',
            'Topic: '.trim($this->posterTopic).'.',
            $copy,
            $style,
            'Use the uploaded images as brand or product references. Preserve recognizable products and logos as closely as possible.',
            'Poster text language: '.$language.'. Use a '.$this->aspectRatio.' composition.',
            trim($this->notes) !== '' ? 'Additional notes: '.trim($this->notes) : null,
        ]));

        return ['prompt' => $prompt, 'title' => $this->defaultProjectName().' — '.Str::limit($this->posterTopic, 80, ''), 'output_type' => 'poster'];
    }
};
?>

@php
    $inputPaths = collect($this->draftProject?->input_paths ?? [])->flatten()->filter()->values();
    $productInputs = $this->productInputPaths();
    $selectedProject = $this->selectedProject;
    $isPolling = $selectedProject?->media->contains(fn ($image) => $image->status === 'pending') ?? false;
@endphp

<section class="mx-auto w-full max-w-7xl space-y-8 p-2 sm:p-4" x-data x-on:open-studio-wizard.window="$wire.openWizard()" @if ($isPolling) wire:poll.2s="refreshProject" @endif>
    <x-studio.header :view="$view" />

    @if ($view === 'plaza')
        <x-studio.plaza :page="$this" />
    @else
        <x-studio.projects :page="$this" :selected-project="$selectedProject" />
    @endif

    <x-studio.wizard
        :page="$this"
        :tool="$tool"
        :step="$step"
        :error-message="$errorMessage"
        :input-paths="$inputPaths"
        :product-inputs="$productInputs"
        :auto-write="$autoWrite"
        :auto-style="$autoStyle"
        :image-types="$imageTypes"
    />
</section>
