<?php

namespace App\Jobs;

use App\Models\ProductImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Processes raw product images asynchronously:
 * 1. Reads from temporary storage path
 * 2. Resizes to max 1200px width (aspect ratio preserved)
 * 3. Converts to WebP format (70-80% size reduction)
 * 4. Replaces original file and updates DB record
 */
class ProcessProductImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        private readonly string $productImageId,
        private readonly string $originalPath
    ) {}

    public function handle(): void
    {
        $productImage = ProductImage::find($this->productImageId);

        if (!$productImage) {
            Log::warning("ProcessProductImageJob: ProductImage {$this->productImageId} not found, skipping.");
            return;
        }

        $disk = Storage::disk('public');
        $absolutePath = $disk->path($this->originalPath);

        if (!file_exists($absolutePath)) {
            Log::warning("ProcessProductImageJob: File not found at {$absolutePath}");
            return;
        }

        try {
            $image = Image::read($absolutePath);

            // Resize if wider than 1200px, maintain aspect ratio
            if ($image->width() > 1200) {
                $image->scale(width: 1200);
            }

            // Generate WebP filename
            $directory = pathinfo($this->originalPath, PATHINFO_DIRNAME);
            $filenameWithoutExt = pathinfo($this->originalPath, PATHINFO_FILENAME);
            $webpPath = $directory . '/' . $filenameWithoutExt . '.webp';
            $webpAbsolutePath = $disk->path($webpPath);

            // Encode to WebP with quality 80 (balances quality vs size)
            $image->toWebp(quality: 80)->save($webpAbsolutePath);

            // Remove original non-webp file if different
            if ($this->originalPath !== $webpPath && file_exists($absolutePath)) {
                @unlink($absolutePath);
            }

            // Update DB record with new WebP path
            $productImage->update(['image' => $webpPath]);

            $originalSize = filesize($webpAbsolutePath);
            Log::info("ProcessProductImageJob: Converted {$this->originalPath} -> {$webpPath} ({$originalSize} bytes)");

        } catch (\Throwable $e) {
            Log::error("ProcessProductImageJob FAILED: {$e->getMessage()}");
            // Don't delete original on failure — keep the raw upload intact
            throw $e;
        }
    }
}
