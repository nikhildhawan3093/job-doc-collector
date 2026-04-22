<?php
/**
 * Shared helper functions for job-doc-collector.
 */

// ─────────────────────────────────────────────
// BLUR DETECTION
// ─────────────────────────────────────────────

/**
 * Detect if an image is blurry using Laplacian variance.
 * Uses GD (always in XAMPP) with Imagick as an optional upgrade.
 *
 * @param string $file_path  Absolute path to the uploaded image
 * @param float  $threshold  Variance below this = blurry
 * @return string  'clear' | 'blurry' | 'skipped' (PDFs only)
 */
function detect_blur(string $file_path, float $threshold = 500): string
{
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

    if ($ext === 'pdf') {
        return 'skipped';
    }

    if (extension_loaded('imagick')) {
        return _detect_blur_imagick($file_path, $threshold);
    }

    return _detect_blur_gd($file_path, $threshold);
}

function _detect_blur_imagick(string $file_path, float $threshold): string
{
    try {
        $imagick = new Imagick($file_path);
        $imagick->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $kernel  = ImagickKernel::fromBuiltIn(Imagick::KERNEL_LAPLACIAN, "0x1");
        $imagick->filter($kernel);
        $stats    = $imagick->getImageChannelStatistics();
        $variance = $stats[Imagick::CHANNEL_GRAY]['standardDeviation'] ?? 0;
        $imagick->destroy();
        return $variance < $threshold ? 'blurry' : 'clear';
    } catch (Exception $e) {
        return 'skipped';
    }
}

function _detect_blur_gd(string $file_path, float $threshold): string
{
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $img = match($ext) {
        'jpg', 'jpeg' => @imagecreatefromjpeg($file_path),
        'png'         => @imagecreatefrompng($file_path),
        default       => false,
    };

    if (!$img) return 'skipped';

    $w = imagesx($img);
    $h = imagesy($img);

    imagefilter($img, IMG_FILTER_GRAYSCALE);

    // Laplacian kernel
    $kernel = [[0, 1, 0], [1, -4, 1], [0, 1, 0]];
    $values = [];
    $step   = max(1, (int)(min($w, $h) / 100));

    for ($y = 1; $y < $h - 1; $y += $step) {
        for ($x = 1; $x < $w - 1; $x += $step) {
            $sum = 0;
            for ($ky = -1; $ky <= 1; $ky++) {
                for ($kx = -1; $kx <= 1; $kx++) {
                    $pixel = imagecolorat($img, $x + $kx, $y + $ky);
                    $gray  = ($pixel >> 16) & 0xFF;
                    $sum  += $gray * $kernel[$ky + 1][$kx + 1];
                }
            }
            $values[] = abs($sum);
        }
    }

    imagedestroy($img);

    if (empty($values)) return 'skipped';

    $mean     = array_sum($values) / count($values);
    $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values);

    return $variance < $threshold ? 'blurry' : 'clear';
}

// ─────────────────────────────────────────────
// AADHAAR OCR — OpenAI GPT-4o Vision
// ─────────────────────────────────────────────

/**
 * Extract Aadhaar Number, Name, and DOB from an Aadhaar card image
 * using OpenAI GPT-4o Vision API.
 *
 * @param string $file_path  Absolute path to the Aadhaar image (JPG/PNG)
 * @return array  ['aadhaar_number' => '', 'name' => '', 'dob' => ''] or empty strings on failure
 */
function extract_aadhaar_data(string $file_path): array
{
    $result = ['aadhaar_number' => '', 'name' => '', 'dob' => ''];

    // PDFs cannot be sent as vision images
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return $result;
    }

    if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) {
        return $result;
    }

    // Encode image as base64
    $image_data = base64_encode(file_get_contents($file_path));
    $mime_type  = ($ext === 'png') ? 'image/png' : 'image/jpeg';

    $payload = [
        'model'      => 'gpt-4o',
        'max_tokens' => 300,
        'messages'   => [[
            'role'    => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' =>
                        'This is an Indian Aadhaar card. Extract the following fields and respond ONLY in this exact JSON format, no explanation:
{"aadhaar_number": "XXXX XXXX XXXX", "name": "Full Name", "dob": "DD/MM/YYYY"}
If a field is not visible, use an empty string. Do not include anything else in your response.'
                ],
                [
                    'type'      => 'image_url',
                    'image_url' => ['url' => "data:{$mime_type};base64,{$image_data}"]
                ]
            ]
        ]]
    ];

    // Call OpenAI API
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 30,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return $result;

    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? '';

    // Parse the JSON response from GPT
    $parsed = json_decode(trim($text), true);
    if (is_array($parsed)) {
        $result['aadhaar_number'] = $parsed['aadhaar_number'] ?? '';
        $result['name']           = $parsed['name'] ?? '';
        $result['dob']            = $parsed['dob'] ?? '';
    }

    return $result;
}
