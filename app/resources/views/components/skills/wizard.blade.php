@props(['page', 'tool', 'step', 'errorMessage', 'inputPaths', 'productInputs', 'autoWrite', 'autoStyle', 'imageTypes'])

<flux:modal name="skill-wizard" flyout class="flex w-full max-w-none flex-col overflow-hidden! p-0! md:w-[440px]" wire:model.self="showWizard" @close="closeWizard">
    <form wire:submit="submit" class="flex min-h-0 flex-1 flex-col" wire:change.debounce.500ms="saveDraft">
        <div class="relative isolate shrink-0 overflow-hidden border-b border-zinc-200 px-5 py-7 pe-14 text-white dark:border-white/10">
            <img class="absolute inset-0 -z-20 size-full object-cover" src="{{ asset($tool === 'product-detail' ? 'images/skills/product-detail.webp' : 'images/skills/marketing-poster.webp') }}" width="1200" height="675" alt="" aria-hidden="true">
            <div class="absolute inset-0 -z-10 bg-linear-to-r from-black/85 via-black/60 to-black/20"></div>
            <flux:heading class="text-white!" size="lg">{{ $tool === 'product-detail' ? __('Product detail images') : __('Marketing poster') }}</flux:heading>
            <p class="mt-1 text-sm text-white/75">{{ __('Step :step of :total', ['step' => $step, 'total' => $page->lastStep()]) }}@if ($page->draftProject?->submitted_at) · {{ __('New version :version', ['version' => $page->latestVersion($page->draftProject) + 1]) }}@endif</p>
        </div>

        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5">
            @if ($errorMessage)
                <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-400/30 dark:bg-red-400/10 dark:text-red-100">{{ $errorMessage }}</div>
            @endif

            @if ($step === 1)
                <div class="space-y-4">
                    <flux:heading>{{ __('Set up the basics') }}</flux:heading>
                    <flux:input wire:model.live.debounce.500ms="projectName" :label="__('Project name')" />

                    @if ($tool === 'product-detail')
                        <div class="space-y-3">
                            <div class="flex items-center justify-between"><flux:heading size="sm">{{ __('Product image') }}</flux:heading><flux:text>{{ $productInputs['product'] ? '1/1' : '0/1' }}</flux:text></div>
                            <flux:file-upload wire:model="newProductPhoto" accept="image/jpeg,image/png,image/webp,image/avif">
                                @if ($productInputs['product'])
                                    <div class="flex cursor-pointer items-center justify-between rounded-2xl border border-dashed border-zinc-300 p-3 dark:border-white/15">
                                        <div class="flex items-center gap-3"><x-iconsax-two-gallery class="size-5 text-zinc-500" /><span class="text-sm">1/1</span></div>
                                        <img class="size-14 rounded-xl object-cover" src="{{ Storage::disk('public')->url($productInputs['product']) }}" alt="{{ __('Product image') }}">
                                    </div>
                                @else
                                    <flux:file-upload.dropzone :heading="__('Upload product image')" :text="__('JPG, PNG, WebP or AVIF.')" with-progress inline />
                                @endif
                            </flux:file-upload>
                            @if ($productInputs['product'])<flux:button size="xs" variant="ghost" type="button" wire:click="removeProductInput('product')">{{ __('Remove product image') }}</flux:button>@endif
                            <flux:error name="newProductPhoto" />
                        </div>

                        <details class="group rounded-2xl border border-zinc-200 p-4 dark:border-white/10" open>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3"><span class="font-medium">{{ __('Extra reference images') }} <span class="ms-1 font-normal text-zinc-500">{{ __('Optional') }}</span></span><flux:icon.chevron-down class="size-4 transition group-open:rotate-180" /></summary>
                            <div class="mt-4 space-y-4">
                                @foreach ([['logo', 'newLogoPhoto', __('Logo'), 1], ['model', 'newModelPhoto', __('Consistent model'), 1]] as [$role, $property, $label, $limit])
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between"><flux:text>{{ $label }}</flux:text><flux:text variant="subtle">{{ $productInputs[$role] ? '1/1' : '0/1' }}</flux:text></div>
                                        <flux:file-upload wire:model="{{ $property }}" accept="image/jpeg,image/png,image/webp,image/avif">
                                            <div class="relative flex size-20 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-zinc-300 dark:border-white/15">
                                                @if ($productInputs[$role])<img class="size-full object-cover" src="{{ Storage::disk('public')->url($productInputs[$role]) }}" alt="{{ $label }}">@else<flux:icon.plus class="size-6 text-zinc-500" />@endif
                                            </div>
                                        </flux:file-upload>
                                        @if ($productInputs[$role])<flux:button size="xs" variant="ghost" type="button" wire:click="removeProductInput('{{ $role }}')">{{ __('Remove :label image', ['label' => $label]) }}</flux:button>@endif
                                        <flux:error name="{{ $property }}" />
                                    </div>
                                @endforeach

                                <div class="space-y-2">
                                    <div class="flex items-center justify-between"><flux:text>{{ __('More product photos') }}</flux:text><flux:text variant="subtle">{{ count($productInputs['additional_products']) }}/2</flux:text></div>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($productInputs['additional_products'] as $index => $path)
                                            <div class="relative size-20 overflow-hidden rounded-xl"><img class="size-full object-cover" src="{{ Storage::disk('public')->url($path) }}" alt="{{ __('Additional product image :number', ['number' => $index + 1]) }}"><flux:button class="absolute top-1 right-1" size="xs" variant="filled" type="button" icon="x-mark" wire:click="removeProductInput('additional_products', {{ $index }})" :aria-label="__('Remove additional product image :number', ['number' => $index + 1])" /></div>
                                        @endforeach
                                        @if (count($productInputs['additional_products']) < 2)
                                            <flux:file-upload wire:model="newAdditionalProductPhotos" accept="image/jpeg,image/png,image/webp,image/avif" multiple>
                                                <div class="flex size-20 cursor-pointer items-center justify-center rounded-xl border border-dashed border-zinc-300 dark:border-white/15"><flux:icon.plus class="size-6 text-zinc-500" /></div>
                                            </flux:file-upload>
                                        @endif
                                    </div>
                                    <flux:error name="newAdditionalProductPhotos" />
                                </div>
                            </div>
                        </details>
                    @else
                        <div class="space-y-3">
                            <div class="flex items-center justify-between"><flux:heading size="sm">{{ __('Reference images') }}</flux:heading><flux:text>{{ $inputPaths->count() }}/{{ \App\Support\AppSettings::maxReferencePhotos() }}</flux:text></div>
                            @if ($inputPaths->isNotEmpty())
                                <div class="grid grid-cols-3 gap-2">
                                    @foreach ($inputPaths as $index => $path)
                                        <div class="group relative overflow-hidden rounded-xl bg-zinc-100 dark:bg-white/10"><img class="aspect-square size-full object-cover" src="{{ Storage::disk('public')->url($path) }}" alt="{{ __('Reference image :number', ['number' => $index + 1]) }}"><flux:button class="absolute top-1 right-1" size="xs" variant="filled" type="button" icon="x-mark" wire:click="removeInput({{ $index }})" :aria-label="__('Remove reference image :number', ['number' => $index + 1])" /></div>
                                    @endforeach
                                </div>
                            @endif
                            @if ($inputPaths->count() < \App\Support\AppSettings::maxReferencePhotos())
                                <flux:file-upload wire:model="newPhotos" accept="image/jpeg,image/png,image/webp,image/avif" multiple><flux:file-upload.dropzone :heading="__('Add brand or product references')" :text="__('JPG, PNG, WebP or AVIF. Up to :count images total.', ['count' => \App\Support\AppSettings::maxReferencePhotos()])" with-progress inline /></flux:file-upload>
                            @endif
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

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model.live="aspectRatio" :label="__('Aspect ratio')">
                            @foreach (['1:1', '3:4', '4:3', '4:5', '9:16', '16:9'] as $ratio)<flux:select.option value="{{ $ratio }}">{{ $ratio }}</flux:select.option>@endforeach
                        </flux:select>
                        <flux:select wire:model.live="language" :label="__('Text language')"><flux:select.option value="vi">Tiếng Việt</flux:select.option><flux:select.option value="en">English</flux:select.option></flux:select>
                    </div>
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

        <div class="shrink-0 border-t border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
            <div class="flex gap-3">
                @if ($step > 1)<flux:button class="w-28" type="button" wire:click="previousStep">{{ __('Back') }}</flux:button>@endif
                @if ($step < $page->lastStep())
                    <flux:button class="flex-1" variant="primary" type="button" wire:click="nextStep" wire:loading.attr="disabled">{{ __('Next') }}</flux:button>
                @else
                    <flux:button class="flex-1" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="submit,newPhotos,newProductPhoto,newLogoPhoto,newModelPhoto,newAdditionalProductPhotos"><span wire:loading.remove wire:target="submit">{{ $page->draftProject?->submitted_at ? __('Create new version') : ($tool === 'product-detail' ? __('Generate :count images', ['count' => count($imageTypes)]) : __('Make poster')) }}</span><span wire:loading wire:target="submit">{{ __('Queuing project...') }}</span></flux:button>
                @endif
            </div>
        </div>
    </form>
</flux:modal>
