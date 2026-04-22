<?php
/**
 * Shared helper functions for job-doc-collector.
 */

/**
 * Detect if an image is blurry using ImageMagick's Laplacian variance method.
 * A low variance means the image lacks edges = blurry.
 *
 * @param string $file_path  Absolute path to the image file
 * @param float  $threshold  Below this variance the image is considered blurry (default: 100)
 * @return string  'clear' | 'blurry' | 'skipped' (for PDFs or if Imagick not available)
 */
function detect_blur(string $file_path, float $threshold = 100): string
{
    // PDFs cannot be blur-checked as images — skip
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return 'skipped';
    }

    // Imagick must be installed
    if (!extension_loaded('imagick')) {
        return 'skipped';
    }

    try {
        $imagick = new Imagick($file_path);

        // Convert to grayscale for edge detection
        $imagick->transformImageColorspace(Imagick::COLORSPACE_GRAY);

        // Apply Laplacian edge detection kernel
        $kernel = ImagickKernel::fromBuiltIn(Imagick::KERNEL_LAPLACIAN, "0x1");
        $imagick->filter($kernel);

        // Get channel statistics — standard deviation = spread of edge values
        $stats    = $imagick->getImageChannelStatistics();
        $variance = $stats[Imagick::CHANNEL_GRAY]['standardDeviation'] ?? 0;

        $imagick->destroy();

        return $variance < $threshold ? 'blurry' : 'clear';

    } catch (Exception $e) {
        // If Imagick fails for any reason, skip blur check
        return 'skipped';
    }
}
