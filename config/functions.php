<?php
/**
 * Shared helper functions for job-doc-collector.
 */

/**
 * Detect if an image is blurry using Laplacian variance.
 * Uses GD (always available in XAMPP) with Imagick as optional upgrade.
 *
 * How it works:
 * - Apply a Laplacian edge-detection filter to the image
 * - Calculate the variance (spread) of the resulting pixel values
 * - Sharp images have strong edges → high variance
 * - Blurry images have weak edges → low variance
 *
 * @param string $file_path  Absolute path to the uploaded image file
 * @param float  $threshold  Variance below this = blurry (tune if needed)
 * @return string  'clear' | 'blurry' | 'skipped' (PDFs only)
 */
function detect_blur(string $file_path, float $threshold = 500): string
{
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    // PDFs can't be blur-checked as pixel images
    if ($ext === 'pdf') {
        return 'skipped';
    }

    // --- Prefer Imagick if available ---
    if (extension_loaded('imagick')) {
        return _detect_blur_imagick($file_path, $threshold);
    }

    // --- Fallback: GD library (always in XAMPP) ---
    return _detect_blur_gd($file_path, $threshold);
}

/**
 * Blur detection using Imagick (more accurate).
 */
function _detect_blur_imagick(string $file_path, float $threshold): string
{
    try {
        $imagick = new Imagick($file_path);
        $imagick->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $kernel  = ImagickKernel::fromBuiltIn(Imagick::KERNEL_LAPLACIAN, "0x1");
        $imagick->filter($kernel);
        $stats   = $imagick->getImageChannelStatistics();
        $variance = $stats[Imagick::CHANNEL_GRAY]['standardDeviation'] ?? 0;
        $imagick->destroy();
        return $variance < $threshold ? 'blurry' : 'clear';
    } catch (Exception $e) {
        return 'skipped';
    }
}

/**
 * Blur detection using GD (Laplacian convolution + pixel variance).
 */
function _detect_blur_gd(string $file_path, float $threshold): string
{
    // Load image based on type
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $img = match($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($file_path),
        'png'         => @imagecreatefrompng($file_path),
        default       => false,
    };

    if (!$img) {
        return 'skipped';
    }

    $w = imagesx($img);
    $h = imagesy($img);

    // Convert to grayscale
    imagefilter($img, IMG_FILTER_GRAYSCALE);

    // Laplacian kernel for edge detection
    $kernel = [
        [0,  1, 0],
        [1, -4, 1],
        [0,  1, 0],
    ];

    // Sample pixels and apply Laplacian manually
    // (imageconvolution normalises differently, so we sample manually)
    $values = [];
    $step   = max(1, (int)(min($w, $h) / 100)); // sample ~100x100 grid

    for ($y = 1; $y < $h - 1; $y += $step) {
        for ($x = 1; $x < $w - 1; $x += $step) {
            $sum = 0;
            for ($ky = -1; $ky <= 1; $ky++) {
                for ($kx = -1; $kx <= 1; $kx++) {
                    $pixel = imagecolorat($img, $x + $kx, $y + $ky);
                    $gray  = ($pixel >> 16) & 0xFF; // red channel = gray after filter
                    $sum  += $gray * $kernel[$ky + 1][$kx + 1];
                }
            }
            $values[] = abs($sum);
        }
    }

    imagedestroy($img);

    if (empty($values)) {
        return 'skipped';
    }

    // Calculate variance of Laplacian response
    $mean     = array_sum($values) / count($values);
    $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values);

    return $variance < $threshold ? 'blurry' : 'clear';
}
