<?php

namespace App\Http\Controllers;

use App\Models\GeneratedMedia;
use GdImage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ImageOptimizer\OptimizerChainFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use Throwable;

class ImageDownloadController extends Controller
{
    public function __invoke(GeneratedMedia $image): BinaryFileResponse
    {
        $user = Auth::user();
        abort_unless(
            $image->status === 'succeeded'
            && is_string($image->result_path)
            && ($image->is_published || ($user && ($user->isAdmin() || $image->user_id === $user->id))),
            404,
        );

        $sourcePath = Storage::disk('public')->path($image->result_path);
        abort_unless(is_file($sourcePath), 404);

        $temporaryPath = tempnam(sys_get_temp_dir(), 'genanh-download-');

        if ($temporaryPath === false) {
            throw new \RuntimeException('Không tạo được file download tạm.');
        }

        unlink($temporaryPath);
        $temporaryPath .= '.jpg';

        try {
            $this->createJpeg($sourcePath, $temporaryPath);
            OptimizerChainFactory::create((array) config('media-library.image_optimizers'))
                ->throws()
                ->optimize($temporaryPath);
            $this->writeMetadata($image, $temporaryPath);

            $name = pathinfo($image->downloadName(), PATHINFO_FILENAME).'.jpg';

            return response()
                ->download($temporaryPath, $name, ['Content-Type' => 'image/jpeg'])
                ->deleteFileAfterSend();
        } catch (Throwable $e) {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }

            throw $e;
        }
    }

    private function createJpeg(string $sourcePath, string $destinationPath): void
    {
        $content = file_get_contents($sourcePath);
        $source = is_string($content) ? imagecreatefromstring($content) : false;

        if (! $source instanceof GdImage) {
            throw new \RuntimeException('Không đọc được ảnh để download.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $jpeg = imagecreatetruecolor($width, $height);

        try {
            if (! $jpeg instanceof GdImage) {
                throw new \RuntimeException('Không tạo được ảnh download.');
            }

            $white = imagecolorallocate($jpeg, 255, 255, 255);

            if ($white === false || ! imagefill($jpeg, 0, 0, $white)) {
                throw new \RuntimeException('Không tạo được nền ảnh download.');
            }

            if (! imagecopy($jpeg, $source, 0, 0, 0, 0, $width, $height) || ! imagejpeg($jpeg, $destinationPath, 92)) {
                throw new \RuntimeException('Không ghi được ảnh download.');
            }
        } finally {
            imagedestroy($source);

            if ($jpeg instanceof GdImage) {
                imagedestroy($jpeg);
            }
        }
    }

    private function writeMetadata(GeneratedMedia $image, string $path): void
    {
        $image->loadMissing('tags');
        $title = Str::limit(trim((string) ($image->title ?: $image->prompt)), 200, '');
        $description = Str::limit(trim((string) ($image->description ?: $image->prompt)), 2000, '');
        $comment = Str::limit("Created with GenAnh.com. {$description}", 2000, '');
        $copyright = 'Copyright '.$image->created_at->year.' GenAnh.com';
        $createdAt = ($image->created_at ?? now())->format('Y:m:d H:i:s');
        $arguments = [
            'exiftool',
            '-overwrite_original',
            '-charset',
            'IPTC=UTF8',
            '-IPTC:CodedCharacterSet=UTF8',
            "-EXIF:ImageDescription={$title}",
            '-EXIF:Artist=GenAnh.com',
            '-EXIF:Software=GenAnh.com',
            "-EXIF:Copyright={$copyright}",
            "-EXIF:DateTimeOriginal={$createdAt}",
            "-EXIF:CreateDate={$createdAt}",
            "-EXIF:UserComment={$comment}",
            "-EXIF:ImageUniqueID=GenAnh.com-{$image->id}",
            "-EXIF:XPTitle={$title}",
            '-EXIF:XPAuthor=GenAnh.com',
            "-EXIF:XPComment={$comment}",
            "-IPTC:ObjectName={$title}",
            "-IPTC:Headline={$title}",
            '-IPTC:By-line=GenAnh.com',
            "-IPTC:Caption-Abstract={$description}",
            "-IPTC:CopyrightNotice={$copyright}",
            "-XMP-dc:Title={$title}",
            '-XMP-dc:Creator=GenAnh.com',
            "-XMP-dc:Description={$description}",
            "-XMP-dc:Rights={$copyright}",
            '-XMP-xmp:CreatorTool=GenAnh.com',
            '-XMP-xmpRights:WebStatement=https://genanh.com',
        ];

        foreach ($image->tags->pluck('name') as $tag) {
            $arguments[] = '-IPTC:Keywords+='.$tag;
            $arguments[] = '-XMP-dc:Subject+='.$tag;
        }

        $arguments[] = $path;
        (new Process($arguments))->mustRun();
    }
}
