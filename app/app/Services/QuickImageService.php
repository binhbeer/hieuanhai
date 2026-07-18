<?php

namespace App\Services;

use App\Models\GeneratedMedia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class QuickImageService extends ImageCreationService
{
    /**
     * @param  list<UploadedFile>  $photos
     * @return list<array{tool: string, request: string, reason: string}>
     */
    public function suggestQuickOptions(array $photos, ?string $landingTool = null): array
    {
        return $this->suggestQuickEditOptions($photos, $landingTool);
    }

    /**
     * @param  list<UploadedFile>  $photos
     */
    public function resolveQuickTool(array $photos, string $request): string
    {
        return $this->resolveQuickEditTool($photos, $request);
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @return array<string, mixed>
     */
    public function preflight(array $photos, string $request, string $tool): array
    {
        return $this->quickEditPreflight($photos, $request, $tool);
    }

    /**
     * @param  list<UploadedFile>  $photos
     * @param  array<string, mixed>  $metadata
     */
    public function createQuickPending(
        Request $request,
        array $photos,
        string $prompt,
        string $tool,
        array $metadata = [],
    ): GeneratedMedia {
        return $this->createQuickEditPending($request, $photos, $prompt, $tool, $metadata);
    }
}
