<?php

namespace App\Service;

use App\Entity\RedditPost;
use App\Utils\RedditResolutionPicker;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MediaService
{
    private string $storageDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public/reddit')]
        string $storageDir,
        #[Autowire('%reddit.target_width%')]
        private int $targetWidth
    ) {
        $this->storageDir = $storageDir;
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0775, true);
        }
    }

    /**
     * Downloads media (video and thumbnail from GIF) and saves paths to the post entity.
     */
    public function downloadAndProcess(RedditPost $post, array $payload): bool
    {
        $fullname = $post->getFullname();

        $gifResolutions = $payload['gif_resolutions'] ?? [];
        if (!empty($gifResolutions)) {
            $closestGif = RedditResolutionPicker::findClosest($gifResolutions, $this->targetWidth);
            $gifUrl     = $closestGif['url'] ?? null;
            if ($gifUrl && str_starts_with($gifUrl, 'http')) {
                $tmpGif   = $this->storageDir . '/' . $fullname . '_thumb_src.gif';
                $thumbPng = $this->storageDir . '/' . $fullname . '_thumb.png';
                if ($this->downloadFile($gifUrl, $tmpGif)) {
                    if ($this->extractFirstFrameFromGif($tmpGif, $thumbPng)) {
                        $post->setLocalThumbnail('reddit/' . $fullname . '_thumb.png');
                    }
                    @unlink($tmpGif);
                }
            }
        }

        $mp4Resolutions = $payload['mp4_resolutions'] ?? [];
        $bestMp4Url     = null;
        if (!empty($mp4Resolutions)) {
            $closest    = RedditResolutionPicker::findClosest($mp4Resolutions, $this->targetWidth);
            $bestMp4Url = $closest['url'] ?? null;
        }

        if (!$bestMp4Url) {
            return false;
        }

        $filename = $fullname . '.mp4';
        $path     = $this->storageDir . '/' . $filename;
        if (!$this->downloadMp4($bestMp4Url, $path)) {
            return false;
        }
        $post->setLocalMp4('reddit/' . $filename);

        return true;
    }

    private function getExtension(string $url, string $default): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return $default;
        }
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        return $ext ?: $default;
    }

    private function downloadFile(string $url, string $destPath): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            return false;
        }
        return file_put_contents($destPath, $content) !== false;
    }

    private function downloadMp4(string $url, string $destPath): bool
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            return false;
        }
        return file_put_contents($destPath, $content) !== false;
    }

    /**
     * Creates a static thumbnail (PNG) from the first frame of a GIF using the GD library.
     */
    private function extractFirstFrameFromGif(string $gifPath, string $pngDestPath): bool
    {
        if (!is_readable($gifPath)) {
            return false;
        }
        $img = imagecreatefromgif($gifPath);
        if ($img === false) {
            return false;
        }

        imagealphablending($img, false);
        imagesavealpha($img, true);
        $ok = imagepng($img, $pngDestPath) === true;
        imagedestroy($img);
        return $ok;
    }
}
