<?php

use App\Jobs\CreateAiImage;
use App\Services\QuickImageService;
use App\Support\AppSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $showComposer = false;

    public ?string $tool = null;

    public ?string $resolvedTool = null;

    public array $photos = [];

    public mixed $newPhotos = [];

    public array $roles = [];

    public string $request = '';

    public string $intentSummary = '';

    public float $confidence = 1.0;

    public bool $needsClarification = false;

    public array $questions = [];

    /** @var list<array{tool: string, request: string, reason: string}> */
    public array $suggestions = [];

    public ?int $selectedSuggestion = null;

    public bool $analyzed = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        if (request()->boolean('composer')) {
            $this->openComposer();
        }
    }

    public function openComposer(?string $tool = null): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->resetComposer();
        $this->tool = \App\Support\QuickEditTools::get($tool) ? $tool : null;
        $this->showComposer = true;
    }

    public function closeComposer(): void
    {
        $this->showComposer = false;
    }

    public function updatedNewPhotos(): void
    {
        $newPhotos = is_array($this->newPhotos) ? $this->newPhotos : [$this->newPhotos];
        $remaining = max(0, AppSettings::maxReferencePhotos() - count($this->photos));
        $this->photos = array_slice([...$this->photos, ...array_values(array_filter($newPhotos))], 0, count($this->photos) + $remaining);
        $this->newPhotos = [];
        $this->resetAnalysis();
        $this->applyDefaultRoles($this->tool);
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index], $this->roles[$index]);
        $this->photos = array_values($this->photos);
        $this->roles = array_values($this->roles);
        $this->resetAnalysis();
    }

    public function analyzeImages(QuickImageService $editor): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        $this->validatePhotos();
        $this->resetAnalysis();

        try {
            $this->suggestions = $editor->suggestQuickOptions($this->photos, $this->tool);

            if ($this->suggestions === []) {
                $this->errorMessage = __('No reliable suggestions were found. Please try clearer images.');

                return;
            }

            $this->selectSuggestion(0);
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = __('Could not analyze this image right now. Please try again later.');
        }
    }

    public function chooseSuggestion(int $index): void
    {
        $this->selectSuggestion($index);
    }

    public function createImage(QuickImageService $editor): void
    {
        if (! Auth::check()) {
            $this->dispatch('open-account-modal', component: 'auth.login');

            return;
        }

        if (! $this->analyzed || \App\Support\QuickEditTools::get($this->resolvedTool) === null) {
            $this->errorMessage = __('Analyze the images before creating.');

            return;
        }

        if ($editor->requiresEmailVerificationForImageCreation()) {
            session()->flash('status', 'image-creation-requires-verification');
            $this->redirectRoute('verification.notice', navigate: true);

            return;
        }

        $this->validateBaseInputs();

        try {
            $tool = $this->resolvedTool;
            $this->applyDefaultRoles($tool);
            $this->validateRoles();

            if (count($this->photos) > 1) {
                $analysis = $editor->preflight($this->photos, $this->request, $tool);
                $this->intentSummary = (string) ($analysis['intent_summary'] ?? '');
                $this->roles = (array) ($analysis['roles'] ?? []);
                $this->confidence = (float) ($analysis['confidence'] ?? 0);
                $this->needsClarification = (bool) ($analysis['needs_clarification'] ?? false);
                $this->questions = (array) ($analysis['questions'] ?? []);
            }

            if ($this->needsClarification || $this->confidence < 0.65) {
                $this->errorMessage = __('Please clarify the edit before creating the image.');

                return;
            }

            $image = $editor->createQuickPending(request(), $this->photos, $this->request, $tool, [
                'reference_roles' => $this->roles,
                'intent_summary' => $this->intentSummary !== '' ? $this->intentSummary : $this->request,
                'preflight_confidence' => $this->confidence,
            ]);
            CreateAiImage::dispatch($image->id, $image->user_id)->afterCommit();
        } catch (\InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->errorMessage = __('Could not create an image right now. Please try again later.');

            return;
        }

        $this->dispatch('image-usage-updated');
        $this->redirectRoute('history.index', ['image' => $image->id], navigate: true);
    }

    private function selectSuggestion(int $index): void
    {
        $suggestion = $this->suggestions[$index] ?? null;

        if (! is_array($suggestion) || \App\Support\QuickEditTools::get($suggestion['tool'] ?? null) === null) {
            return;
        }

        $this->selectedSuggestion = $index;
        $this->resolvedTool = $suggestion['tool'];
        $this->request = trim($suggestion['request']);
        $this->analyzed = true;
        $this->errorMessage = null;
        $this->applyDefaultRoles($this->resolvedTool, true);
    }

    private function validatePhotos(): void
    {
        $this->validate([
            'photos' => ['required', 'array', 'min:1', 'max:'.AppSettings::maxReferencePhotos()],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);
    }

    private function validateBaseInputs(): void
    {
        $this->validate([
            'request' => ['required', 'string', 'max:500'],
            'photos' => ['required', 'array', 'min:1', 'max:'.AppSettings::maxReferencePhotos()],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp,avif', 'max:'.AppSettings::imageUploadMaxKb()],
        ]);
    }

    private function validateRoles(): void
    {
        $this->validate([
            'roles' => ['required', 'array', 'size:'.count($this->photos), 'max:'.AppSettings::maxReferencePhotos()],
            'roles.*' => ['required', 'string', Rule::in(\App\Support\QuickEditTools::roles())],
        ]);
    }

    private function applyDefaultRoles(?string $tool, bool $forcePrimary = false): void
    {
        $config = \App\Support\QuickEditTools::get($tool);
        $sourceRole = $config['source_role'] ?? 'source';

        foreach (array_keys($this->photos) as $index) {
            if (($forcePrimary && $index === 0) || ! in_array($this->roles[$index] ?? null, \App\Support\QuickEditTools::roles(), true)) {
                $this->roles[$index] = $index === 0 ? $sourceRole : 'supplemental';
            }
        }
    }

    private function resetAnalysis(): void
    {
        $this->reset('resolvedTool', 'request', 'intentSummary', 'questions', 'suggestions', 'selectedSuggestion', 'errorMessage');
        $this->confidence = 1.0;
        $this->needsClarification = false;
        $this->analyzed = false;
    }

    private function resetComposer(): void
    {
        $this->reset('tool', 'resolvedTool', 'photos', 'newPhotos', 'roles', 'request', 'intentSummary', 'questions', 'suggestions', 'selectedSuggestion', 'errorMessage');
        $this->confidence = 1.0;
        $this->needsClarification = false;
        $this->analyzed = false;
        $this->resetValidation();
    }
};
?>

@php
    $tools = \App\Support\QuickEditTools::all();
    $maxPhotos = AppSettings::maxReferencePhotos();
    $landingToolConfig = \App\Support\QuickEditTools::get($tool);
    $resolvedToolConfig = \App\Support\QuickEditTools::get($resolvedTool);
@endphp

<div class="contents" x-data x-on:open-quick-composer.window="$wire.openComposer($event.detail?.tool ?? null)">
    <flux:modal name="quick-composer" flyout class="flex w-full max-w-none flex-col overflow-hidden! p-0! md:w-[420px]" wire:model.self="showComposer" @close="closeComposer">
        <form class="flex min-h-0 flex-1 flex-col" wire:submit="createImage">
            <div class="shrink-0 border-b border-zinc-200 p-6 pe-14 dark:border-white/10">
                <flux:heading size="xl">{{ __('Quick Edit') }}</flux:heading>
                <flux:text class="mt-1" variant="subtle">{{ __('Upload references, let GenAnh suggest the best edit, then review the prompt.') }}</flux:text>
                @if ($landingToolConfig && ! $analyzed)
                    <div class="mt-3"><flux:badge color="amber">{{ __('Starting point: :tool', ['tool' => __($landingToolConfig['title'])]) }}</flux:badge></div>
                @endif
            </div>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4">
                @if ($errorMessage)
                    <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-100">{{ $errorMessage }}</div>
                @endif

                <section class="space-y-3" aria-labelledby="quick-references-heading">
                    <x-image-upload-grid model="newPhotos" :count="count($photos)" :limit="$maxPhotos" :heading="__('Reference images')" :add-label="__('Add image')">
                        @foreach ($photos as $index => $photo)
                            <article class="min-w-0 overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-white/5" wire:key="quick-composer-photo-{{ $index }}">
                                <div class="relative aspect-square bg-zinc-100 dark:bg-white/10">
                                    <img class="size-full object-cover" src="{{ $photo->temporaryUrl() }}" alt="{{ __('Reference image :number', ['number' => $index + 1]) }}">
                                    <div class="absolute top-1 right-1 z-10">
                                        <flux:button type="button" size="xs" variant="filled" icon="x-mark" wire:click="removePhoto({{ $index }})" :aria-label="__('Remove reference image :number', ['number' => $index + 1])" />
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </x-image-upload-grid>
                    <div class="text-sm text-zinc-500" wire:loading wire:target="newPhotos">{{ __('Uploading image...') }}</div>
                    <flux:error name="photos" />
                    <flux:error name="photos.*" />

                    @if ($photos !== [])
                        <flux:button class="w-full" type="button" variant="outline" icon="sparkles" wire:click="analyzeImages" wire:loading.attr="disabled" wire:target="analyzeImages,newPhotos">
                            <span wire:loading.remove wire:target="analyzeImages">{{ $analyzed ? __('Analyze again') : __('Analyze image') }}</span>
                            <span wire:loading wire:target="analyzeImages">{{ __('Finding suitable edits...') }}</span>
                        </flux:button>
                    @endif
                </section>

                @if ($suggestions !== [])
                    <section class="space-y-3" aria-labelledby="quick-suggestions-heading">
                        <div>
                            <flux:heading id="quick-suggestions-heading" size="sm">{{ __('Choose a suitable edit') }}</flux:heading>
                            <flux:text class="mt-1" variant="subtle">{{ __('GenAnh selected the best match first. Choose another option if needed.') }}</flux:text>
                        </div>
                        <div class="grid gap-2" role="radiogroup" aria-label="{{ __('AI edit suggestions') }}">
                            @foreach ($suggestions as $index => $suggestion)
                                @php($suggestedTool = $tools[$suggestion['tool']] ?? null)
                                @if ($suggestedTool)
                                    <button type="button" class="w-full rounded-xl border p-3 text-start transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 {{ $selectedSuggestion === $index ? 'border-amber-400 bg-amber-50 dark:border-amber-300/40 dark:bg-amber-300/10' : 'border-zinc-200 hover:border-amber-300 dark:border-white/10 dark:hover:border-amber-300/30' }}" role="radio" aria-checked="{{ $selectedSuggestion === $index ? 'true' : 'false' }}" wire:click="chooseSuggestion({{ $index }})">
                                        <span class="flex items-center justify-between gap-3">
                                            <span class="font-medium text-zinc-950 dark:text-white">{{ __($suggestedTool['title']) }}</span>
                                            @if ($selectedSuggestion === $index)<flux:badge color="amber">{{ __('Selected') }}</flux:badge>@endif
                                        </span>
                                        <span class="mt-1 block text-sm leading-5 text-zinc-500 dark:text-zinc-400">{{ $suggestion['reason'] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($analyzed)
                    <section class="space-y-3" aria-labelledby="quick-prompt-heading">
                        <div class="flex items-center justify-between gap-3">
                            <flux:heading id="quick-prompt-heading" size="sm">{{ __('What should GenAnh change?') }}</flux:heading>
                            @if ($resolvedToolConfig)<flux:badge color="amber">{{ __($resolvedToolConfig['title']) }}</flux:badge>@endif
                        </div>
                        <flux:textarea wire:model.live.debounce.300ms="request" rows="5" resize="vertical" maxlength="500" :placeholder="__('Describe the result in natural language, for example: remove the person behind me and keep everything else unchanged.')" required />
                        <div class="flex justify-end"><flux:text class="tabular-nums" variant="subtle">{{ mb_strlen($request) }}/500</flux:text></div>
                        <flux:error name="request" />
                    </section>
                @endif

                @if ($intentSummary !== '')
                    <div class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-950 dark:bg-emerald-400/10 dark:text-emerald-100">
                        <div class="font-semibold">{{ __('GenAnh understands that...') }}</div>
                        <p class="mt-1">{{ $intentSummary }}</p>
                    </div>
                @endif

                @if ($questions !== [])
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                        <div class="font-semibold">{{ __('Please clarify') }}</div>
                        <ul class="mt-2 list-disc space-y-1 ps-5">@foreach ($questions as $question)<li>{{ $question }}</li>@endforeach</ul>
                    </div>
                @endif
            </div>

            <div class="shrink-0 border-t border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
                <flux:button class="w-full" type="submit" variant="primary" color="amber" :disabled="!$analyzed" wire:loading.attr="disabled" wire:target="newPhotos,analyzeImages,createImage">
                    <span wire:loading.remove wire:target="createImage">{{ __('Create edited image') }}</span>
                    <span wire:loading wire:target="createImage">{{ __('Creating image...') }}</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
