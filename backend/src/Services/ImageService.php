<?php

declare(strict_types=1);

namespace NK\Services;

use NK\Config\Constants;
use NK\Repositories\MenuRepository;
use ZipArchive;

class ImageService
{
    /** Maximum individual file size inside the ZIP (10 MB) */
    private const MAX_IMAGE_BYTES = 10 * 1024 * 1024;

    /** JPEG quality for WebP conversion */
    private const WEBP_QUALITY = 82;

    /** Thumbnail dimension (square crop) */
    private const THUMB_SIZE = 300;

    /** Full-size max dimension */
    private const FULL_MAX = 1200;

    /** Allowed MIME types */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    private MenuRepository $repo;
    private string $publicBase;

    public function __construct()
    {
        $this->repo = new MenuRepository();
        $this->publicBase = __DIR__ . '/../../public/menu-images';
    }

    // ─────────────────────────────────────────────────────────── Public API ──

    /**
     * Process a ZIP file containing item images.
     * Each file inside the ZIP should be named {item_id}.{jpg|png|webp}.
     * Returns a summary of processed / skipped / failed items.
     */
    public function processZipUpload(string $tmpPath, string $sheetType): array
    {
        $zip = new ZipArchive();
        $result = $zip->open($tmpPath);

        if ($result !== true) {
            return ['ok' => false, 'error' => 'ZIP_OPEN_FAILED', 'message' => "Could not open ZIP file (code: {$result})."];
        }

        $this->ensureDirs($sheetType);

        $processed = 0;
        $skipped   = [];
        $failed    = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }

            // Skip directories and hidden files
            if (str_ends_with($name, '/') || str_starts_with(basename($name), '.')) {
                continue;
            }

            $baseName = basename($name);
            $stem     = pathinfo($baseName, PATHINFO_FILENAME);
            $itemId   = (int) $stem;

            if ($itemId <= 0) {
                $skipped[] = ['file' => $name, 'reason' => 'Filename is not a numeric item ID'];
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false || strlen($content) === 0) {
                $skipped[] = ['file' => $name, 'reason' => 'Could not read file contents'];
                continue;
            }

            if (strlen($content) > self::MAX_IMAGE_BYTES) {
                $skipped[] = ['file' => $name, 'reason' => 'File exceeds 10 MB limit'];
                continue;
            }

            // Validate MIME using finfo
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->buffer($content);

            if (!in_array($mime, self::ALLOWED_MIME, true)) {
                $skipped[] = ['file' => $name, 'reason' => "Unsupported MIME type: {$mime}"];
                continue;
            }

            try {
                $paths = $this->saveImageContent($content, $mime, $itemId, $sheetType);
                // Update DB record
                $this->repo->upsertItemImage($itemId, $paths['full'], $paths['thumb']);
                // Update item image_url column to point to served path
                $servePath = '/menu-images/' . $sheetType . '/' . $itemId . '.webp';
                $this->repo->updateItem($itemId, ['image_url' => $servePath]);
                $processed++;
            } catch (\Throwable $e) {
                $failed[] = ['file' => $name, 'reason' => $e->getMessage()];
            }
        }

        $zip->close();

        return [
            'ok'        => true,
            'processed' => $processed,
            'skipped'   => $skipped,
            'failed'    => $failed,
        ];
    }

    // ─────────────────────────────────────────────────── Image processing ──

    /**
     * Convert raw image bytes to WebP, save full + thumbnail, return their paths.
     */
    private function saveImageContent(string $content, string $mime, int $itemId, string $sheetType): array
    {
        $gdImage = $this->loadGdImage($content, $mime);

        if ($gdImage === false) {
            throw new \RuntimeException("GD could not load image for item {$itemId}.");
        }

        $origW = imagesx($gdImage);
        $origH = imagesy($gdImage);

        // ── Full size ──────────────────────────────────────────────────────
        [$newW, $newH] = $this->fitDimensions($origW, $origH, self::FULL_MAX, self::FULL_MAX);
        $fullImg = imagescale($gdImage, $newW, $newH, IMG_BICUBIC);

        $fullDir  = $this->publicBase . '/' . $sheetType;
        $fullPath = $fullDir . '/' . $itemId . '.webp';
        imagewebp($fullImg, $fullPath, self::WEBP_QUALITY);
        imagedestroy($fullImg);

        // ── Thumbnail (square crop from center) ───────────────────────────
        $thumbImg = $this->squareCrop($gdImage, self::THUMB_SIZE);

        $thumbDir  = $this->publicBase . '/' . $sheetType . '-thumb';
        $thumbPath = $thumbDir . '/' . $itemId . '.webp';
        imagewebp($thumbImg, $thumbPath, self::WEBP_QUALITY);
        imagedestroy($thumbImg);

        imagedestroy($gdImage);

        return ['full' => $fullPath, 'thumb' => $thumbPath];
    }

    private function loadGdImage(string $content, string $mime): \GdImage|false
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'nk_img_');
        file_put_contents($tmpFile, $content);

        try {
            return match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($tmpFile),
                'image/png'  => imagecreatefrompng($tmpFile),
                'image/webp' => imagecreatefromwebp($tmpFile),
                'image/gif'  => imagecreatefromgif($tmpFile),
                default      => false,
            };
        } finally {
            @unlink($tmpFile);
        }
    }

    private function fitDimensions(int $w, int $h, int $maxW, int $maxH): array
    {
        if ($w <= $maxW && $h <= $maxH) {
            return [$w, $h];
        }
        $ratio = min($maxW / $w, $maxH / $h);
        return [(int) round($w * $ratio), (int) round($h * $ratio)];
    }

    private function squareCrop(\GdImage $src, int $size): \GdImage
    {
        $origW = imagesx($src);
        $origH = imagesy($src);

        $minDim  = min($origW, $origH);
        $cropX   = (int) (($origW - $minDim) / 2);
        $cropY   = (int) (($origH - $minDim) / 2);

        $square = imagecreatetruecolor($size, $size);
        imagecopyresampled($square, $src, 0, 0, $cropX, $cropY, $size, $size, $minDim, $minDim);

        return $square;
    }

    /**
     * Process a single image file uploaded by the admin for a specific menu item.
     * Saves full + thumbnail WebP, updates DB image_url, returns served URL.
     */
    public function processSingleUpload(string $tmpPath, int $itemId, string $sheetType): array
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'INVALID_FILE', 'message' => "Unsupported MIME type: {$mime}. Use JPEG, PNG, WebP, or GIF."];
        }

        $content = file_get_contents($tmpPath);
        if ($content === false || strlen($content) === 0) {
            return ['ok' => false, 'error' => 'READ_ERROR', 'message' => 'Failed to read uploaded image.'];
        }

        if (strlen($content) > self::MAX_IMAGE_BYTES) {
            return ['ok' => false, 'error' => 'FILE_TOO_LARGE', 'message' => 'Image must be under 10 MB.'];
        }

        $this->ensureDirs($sheetType);

        $paths = $this->saveImageContent($content, $mime, $itemId, $sheetType);
        $this->repo->upsertItemImage($itemId, $paths['full'], $paths['thumb']);
        $servePath = '/menu-images/' . $sheetType . '/' . $itemId . '.webp';
        $this->repo->updateItem($itemId, ['image_url' => $servePath]);

        return [
            'ok'       => true,
            'imageUrl' => $servePath,
            'message'  => 'Image uploaded and applied.',
        ];
    }

    private function ensureDirs(string $sheetType): void
    {
        $dirs = [
            $this->publicBase . '/' . $sheetType,
            $this->publicBase . '/' . $sheetType . '-thumb',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0750, true);
            }
        }
    }
}
