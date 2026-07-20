@props(['page', 'tool', 'step', 'errorMessage', 'inputPaths', 'productInputs', 'autoWrite', 'autoStyle', 'imageTypes'])

<flux:modal name="skill-wizard" flyout :closable="false" class="flex w-full max-w-none flex-col overflow-hidden! p-0! md:w-[440px]" wire:model.self="showWizard" @close="closeWizard">
    <form wire:submit="submit" class="flex min-h-0 flex-1 flex-col" wire:change.debounce.500ms="saveDraft">
        <div class="relative isolate shrink-0 overflow-hidden border-b border-zinc-200 px-5 py-7 pe-14 text-white dark:border-white/10">
            <img class="absolute inset-0 -z-20 size-full object-cover" src="{{ asset($tool === 'product-detail' ? 'images/skills/product-detail.webp' : 'images/skills/marketing-poster.webp') }}" width="1200" height="675" alt="" aria-hidden="true">
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-black/85 via-black/60 to-black/20"></div>
            <flux:heading class="text-white!" size="lg">{{ $tool === 'product-detail' ? __('Product detail images') : __('Marketing poster') }}</flux:heading>
            <p class="mt-1 text-sm text-white/75">{{ __('Step :step of :total', ['step' => $step, 'total' => $page->lastStep()]) }}@if ($page->draftProject?->submitted_at) · {{ __('New version :version', ['version' => $page->latestVersion($page->draftProject) + 1]) }}@endif</p>
            <div class="absolute top-0 end-0 mt-4 me-4">
                <flux:modal.close>
                    <flux:button variant="primary" icon="x-mark" size="sm" :aria-label="__('Close modal')" />
                </flux:modal.close>
            </div>
        </div>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
            @if ($errorMessage)
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-100">{{ $errorMessage }}</div>
            @endif

            @if ($step === 1)
                <div class="space-y-4">
                    @if ($page->isEditingProject())
                        <flux:input wire:model.live.debounce.500ms="projectName" :label="__('Project name')" />
                    @endif

                    @if ($tool === 'product-detail')
                        <x-image-upload-grid model="newProductPhoto" :count="$productInputs['product'] ? 1 : 0" :limit="1" :heading="__('Product image')" :add-label="__('Add image')" :multiple="false">
                            @if ($productInputs['product'])
                                <div class="group relative overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/10">
                                    <img class="aspect-square size-full object-cover" src="{{ asset('storage/'.$productInputs['product']) }}" alt="{{ __('Product image') }}">
                                    <flux:button class="absolute top-1 right-1" size="xs" variant="filled" type="button" icon="x-mark" wire:click="removeProductInput('product')" :aria-label="__('Remove product image')" />
                                </div>
                            @endif
                        </x-image-upload-grid>
                        <flux:error name="newProductPhoto" />

                        <details class="group rounded-2xl border border-zinc-200 p-4 dark:border-white/10">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3"><span class="font-medium">{{ __('Extra reference images') }} <span class="ms-1 font-normal text-zinc-500">{{ __('Optional') }}</span></span><flux:icon.chevron-down class="size-4 transition group-open:rotate-180" /></summary>
                            <div class="mt-4 space-y-4">
                                @foreach ([['logo', 'newLogoPhoto', __('Logo')], ['model', 'newModelPhoto', __('Consistent model')]] as [$role, $property, $label])
                                    <div class="space-y-2">
                                        <x-image-upload-grid :model="$property" :count="$productInputs[$role] ? 1 : 0" :limit="1" :heading="$label" :add-label="__('Add image')" :multiple="false">
                                            @if ($productInputs[$role])
                                                <div class="group relative overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/10">
                                                    <img class="aspect-square size-full object-cover" src="{{ asset('storage/'.$productInputs[$role]) }}" alt="{{ $label }}">
                                                    <flux:button class="absolute top-1 right-1" size="xs" variant="filled" type="button" icon="x-mark" wire:click="removeProductInput('{{ $role }}')" :aria-label="__('Remove :label image', ['label' => $label])" />
                                                </div>
                                            @endif
                                        </x-image-upload-grid>
                                        <flux:error name="{{ $property }}" />
                                    </div>
                                @endforeach

                                <div class="space-y-2">
                                    <x-image-upload-grid model="newAdditionalProductPhotos" :count="count($productInputs['additional_products'])" :limit="2" :heading="__('More product photos')" :add-label="__('Add image')">
                                        @foreach ($productInputs['additional_products'] as $index => $path)
                                            <div class="relative overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/10"><img class="aspect-square size-full object-cover" src="{{ asset('storage/'.$path) }}" alt="{{ __('Additional product image :number', ['number' => $index + 1]) }}"><flux:button class="absolute top-1 right-1" size="xs" variant="filled" type="button" icon="x-mark" wire:click="removeProductInput('additional_products', {{ $index }})" :aria-label="__('Remove additional product image :number', ['number' => $index + 1])" /></div>
                                        @endforeach
                                    </x-image-upload-grid>
                                    <flux:error name="newAdditionalProductPhotos" />
                                </div>
                            </div>
                        </details>
                    @else
                        <div>
                            <x-image-upload-grid model="newPhotos" :count="$inputPaths->count()" :limit="\App\Support\AppSettings::maxReferencePhotos()" :heading="__('Reference images')" :add-label="__('Add image')">
                                @foreach ($inputPaths as $index => $path)
                                    <div class="group relative overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/10"><img class="aspect-square size-full object-cover" src="{{ asset('storage/'.$path) }}" alt="{{ __('Reference image :number', ['number' => $index + 1]) }}"><flux:button class="absolute top-1 right-1" size="xs" variant="filled" type="button" icon="x-mark" wire:click="removeInput({{ $index }})" :aria-label="__('Remove reference image :number', ['number' => $index + 1])" /></div>
                                @endforeach
                            </x-image-upload-grid>
                            <flux:error name="newPhotos" />
                        </div>
                    @endif

                    @if ($tool === 'product-detail')
                        <flux:input wire:model.live.debounce.500ms="productName" :label="__('Product name')" :placeholder="__('Optional')" />
                    @else
                        <flux:input wire:model.live.debounce.500ms="posterTopic" :label="__('Poster topic')" required />
                        <flux:switch wire:model.live="autoWrite" :label="__('Auto-write copy')" />
                        @unless ($autoWrite)<flux:textarea wire:model.live.debounce.500ms="posterCopy" :label="__('Content and copy')" rows="3" />@endunless
                    @endif
                </div>
            @elseif ($step === 2 && $tool === 'product-detail')
                <div class="space-y-4">
                    <div><flux:heading>{{ __('Choose image types') }}</flux:heading><flux:text class="mt-1">{{ __('Each selected type creates one image.') }}</flux:text></div>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['hero' => 'Hero Banner', 'close-up' => 'Close-up Details', 'lifestyle' => 'Lifestyle Scene', 'material' => 'Material', 'how-to' => 'How to Use', 'brand' => 'Brand Closing'] as $value => $label)
                            <label class="flex min-h-12 cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 p-3 text-sm dark:border-white/10"><flux:checkbox wire:model.live="imageTypes" value="{{ $value }}" /><span>{{ __($label) }}</span></label>
                        @endforeach
                    </div>
                    @foreach ($imageTypes as $value)@if (Str::startsWith($value, 'custom:'))<div class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700 dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-100">{{ Str::after($value, 'custom:') }}</div>@endif @endforeach
                    <div class="flex gap-2"><flux:input class="flex-1" wire:model="customImageType" :placeholder="__('Custom image type')" /><flux:button type="button" wire:click="addCustomImageType">{{ __('Add') }}</flux:button></div>
                    <flux:error name="imageTypes" />
                </div>
            @elseif ($step === 2)
                <div class="space-y-4">
                    <div><flux:heading>{{ __('Choose a style') }}</flux:heading><flux:text class="mt-1">{{ __('Pick one or let AI decide.') }}</flux:text></div>
                    <flux:switch wire:model.live="autoStyle" :label="__('Let AI choose the style')" />
                    @unless ($autoStyle)<flux:input wire:model.live.debounce.500ms="posterStyle" :label="__('Custom style')" :placeholder="__('e.g. Minimalist commercial photography')" />@endunless
                </div>
            @else
                <div class="space-y-5">
                    <div><flux:heading>{{ __('Add extra notes') }}</flux:heading><flux:text class="mt-1">{{ __('Anything else the AI should know?') }}</flux:text></div>
                    <flux:textarea wire:model.live.debounce.500ms="notes" :label="__('Extra notes')" rows="5" />
                    <div class="rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-400/10 dark:text-amber-100">{{ __('AI-generated text and product details are best-effort. Review every result before publishing.') }}</div>
                </div>
            @endif
        </div>

        <div class="shrink-0 space-y-3 border-t border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-2">
                <flux:dropdown align="start">
                    <flux:button type="button" size="sm" variant="outline" icon:trailing="chevron-down" :aria-label="__('Image model')">
                        {{ \App\Support\AppSettings::imageModelLabel($page->imageModel) }}
                    </flux:button>
                    <flux:menu class="min-w-56">
                        <flux:menu.radio.group wire:model.live="imageModel">
                            @foreach (\App\Support\AppSettings::enabledImageModels() as $model)
                                <flux:menu.radio :value="$model" wire:key="studio-image-model-{{ md5($model) }}">{{ \App\Support\AppSettings::imageModelLabel($model) }}</flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>
                <flux:dropdown align="start">
                    <flux:button type="button" size="sm" variant="outline" icon:trailing="chevron-down" :aria-label="__('Aspect ratio')">
                        {{ $page->aspectRatio }}
                    </flux:button>
                    <flux:menu class="min-w-56">
                        <flux:menu.radio.group wire:model.live="aspectRatio">
                            @foreach (['1:1', '3:4', '4:3', '4:5', '9:16', '16:9'] as $ratio)
                                <flux:menu.radio :value="$ratio" wire:key="studio-aspect-{{ $ratio }}">
                                    <span class="flex items-center gap-3">
                                        <span class="flex size-5 items-center justify-center">
                                            <span class="{{ \App\Support\GptImageOptions::aspectRatioIconClasses()[$ratio] ?? 'size-3.5' }} rounded-[2px] border-2 border-current opacity-70"></span>
                                        </span>
                                        <span class="flex flex-col">
                                            <span>{{ $ratio }}</span>
                                            <span class="text-xs font-normal text-zinc-500 dark:text-zinc-400">{{ \App\Support\GptImageOptions::aspectRatioDescriptions()[$ratio] ?? '' }}</span>
                                        </span>
                                    </span>
                                </flux:menu.radio>
                            @endforeach
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>
                <flux:dropdown align="start">
                    <flux:button type="button" size="sm" variant="outline" icon:trailing="chevron-down" :aria-label="__('Text language')">{{ $page->language === 'vi' ? 'Tiếng Việt' : 'English' }}</flux:button>
                    <flux:menu class="min-w-40">
                        <flux:menu.radio.group wire:model.live="language">
                            <flux:menu.radio value="vi">Tiếng Việt</flux:menu.radio>
                            <flux:menu.radio value="en">English</flux:menu.radio>
                        </flux:menu.radio.group>
                    </flux:menu>
                </flux:dropdown>
            </div>
            @if ($step >= $page->lastStep())
                <x-ai-data-consent />
            @endif
            <div class="flex gap-3">
                @if ($step > 1)<flux:button class="w-28" type="button" wire:click="previousStep">{{ __('Back') }}</flux:button>@endif
                @if ($step < $page->lastStep())
                    <flux:button class="flex-1" variant="primary" color="violet" type="button" wire:click="nextStep" wire:loading.attr="disabled">{{ __('Next') }}</flux:button>
                @else
                    <flux:button class="flex-1" variant="primary" color="violet" type="submit" wire:loading.attr="disabled" wire:target="submit,newPhotos,newProductPhoto,newLogoPhoto,newModelPhoto,newAdditionalProductPhotos"><span wire:loading.remove wire:target="submit">{{ $page->draftProject?->submitted_at ? __('Create new version') : ($tool === 'product-detail' ? __('Generate :count images', ['count' => count($imageTypes)]) : __('Make poster')) }}</span><span wire:loading wire:target="submit">{{ __('Queuing project...') }}</span></flux:button>
                @endif
            </div>
        </div>
    </form>
</flux:modal>
