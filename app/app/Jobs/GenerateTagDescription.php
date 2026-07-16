<?php

namespace App\Jobs;

use App\Ai\TagDescriptionAgent;
use App\Models\Tag;
use App\Support\AppSettings;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use RuntimeException;

class GenerateTagDescription implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable;

    public int $timeout = 330;

    public int $tries = 3;

    public int $uniqueFor = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $tagId) {}

    public function uniqueId(): string
    {
        return 'tag:'.$this->tagId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $tag = Tag::query()->find($this->tagId);

        if (! $tag || filled($tag->description)) {
            return;
        }

        $provider = AppSettings::string('ai.image_provider', (string) config('ai.default', 'openai'));
        $model = AppSettings::string('ai.tag_model')
            ?: AppSettings::string('ai.text_model', (string) config('ai.text_model', 'gpt-5.5'));
        $url = rtrim(AppSettings::string('ai.'.$provider.'_url'), '/');
        $key = AppSettings::string('ai.'.$provider.'_api_key');

        if ($url === '' || $key === '') {
            throw new RuntimeException("Provider AI [$provider] thiếu URL hoặc API key.");
        }

        config([
            "ai.providers.$provider.driver" => 'openrouter',
            "ai.providers.$provider.key" => $key,
            "ai.providers.$provider.url" => $url,
        ]);
        Ai::forgetInstance($provider);

        // ponytail: Eight recent items cap prompt size; sample across time if broad tags need more coverage.
        $examples = $tag->media()
            ->publiclyVisible()
            ->latest('published_at')
            ->limit(8)
            ->get()
            ->map(fn ($image): string => Str::limit((string) ($image->title ?: $image->description ?: $image->prompt), 160, ''))
            ->filter()
            ->implode("\n- ");
        $examples = $examples === '' ? '- chưa có ví dụ' : '- '.$examples;

        $response = TagDescriptionAgent::make()->prompt(
            "Viết meta description cho trang tag sau.\n\nTên tag: {$tag->name}\n\nVí dụ nội dung trong bộ sưu tập:\n{$examples}",
            provider: $provider,
            model: $model,
            timeout: AppSettings::int('ai.image_timeout', (int) config('ai.image_timeout', 300)),
        );
        $data = $response instanceof Arrayable ? $response->toArray() : [];
        $description = Str::of(is_string($data['description'] ?? null) ? $data['description'] : '')
            ->squish()
            ->limit(160, '')
            ->toString();

        if ($description === '') {
            throw new RuntimeException('AI trả về description tag rỗng.');
        }

        $tag->refresh();

        if (blank($tag->description)) {
            $tag->update(['description' => $description]);
        }
    }
}
