<?php
/**
 * Shared helper functions for job-doc-collector.
 */

// ─────────────────────────────────────────────
// AADHAAR DATA VALIDATION
// ─────────────────────────────────────────────

/**
 * Validate extracted Aadhaar fields.
 * Rules:
 *   - aadhaar_number: exactly 12 digits (spaces allowed between groups)
 *   - dob: must be a valid date (DD/MM/YYYY or YYYY-MM-DD)
 *   - name: must not be empty
 *
 * @param array $data  ['aadhaar_number' => '', 'name' => '', 'dob' => '']
 * @return array  ['valid' => bool, 'errors' => [field => message]]
 */
function validate_aadhaar_data(array $data): array
{
    $errors = [];

    // Aadhaar number: strip spaces, must be exactly 12 digits
    $number = preg_replace('/\s+/', '', $data['aadhaar_number'] ?? '');
    if (!preg_match('/^\d{12}$/', $number)) {
        $errors['aadhaar_number'] = 'Aadhaar number must be exactly 12 digits.';
    }

    // Name: must not be empty
    $name = trim($data['name'] ?? '');
    if ($name === '') {
        $errors['name'] = 'Name must not be empty.';
    }

    // DOB: must be a valid date — accepts DD/MM/YYYY or YYYY-MM-DD
    $dob = trim($data['dob'] ?? '');
    if ($dob === '') {
        $errors['dob'] = 'Date of birth must not be empty.';
    } else {
        $parsed = false;
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $format) {
            $dt = DateTime::createFromFormat($format, $dob);
            if ($dt && $dt->format($format === 'd/m/Y' ? 'd/m/Y' : ($format === 'Y-m-d' ? 'Y-m-d' : 'd-m-Y')) === $dob) {
                $parsed = true;
                break;
            }
        }
        if (!$parsed) {
            $errors['dob'] = 'Date of birth is not a valid date.';
        }
    }

    return ['valid' => empty($errors), 'errors' => $errors];
}

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
// RESUME TEXT EXTRACTION — smalot/pdfparser
// ─────────────────────────────────────────────

/**
 * Extract raw text from a PDF resume using smalot/pdfparser.
 *
 * @param string $file_path  Absolute or relative path to the PDF file
 * @return string  Extracted plain text, or empty string on failure
 */
function extract_resume_text(string $file_path): string
{
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        return '';
    }
    require_once $autoload;

    try {
        $parser   = new \Smalot\PdfParser\Parser();
        $pdf      = $parser->parseFile($file_path);
        $text     = $pdf->getText();

        // Normalise whitespace: collapse multiple blank lines, trim
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    } catch (\Exception $e) {
        return '';
    }
}

// ─────────────────────────────────────────────
// AADHAAR OCR — Mistral Vision
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

    if (!defined('MISTRAL_API_KEY') || !MISTRAL_API_KEY) {
        return $result;
    }

    $ext        = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $file_data  = base64_encode(file_get_contents($file_path));
    $mime_type  = match($ext) {
        'pdf'  => 'application/pdf',
        'png'  => 'image/png',
        default => 'image/jpeg',
    };

    $prompt = 'This is an Indian Aadhaar card. Extract the following fields and respond ONLY in this exact JSON format, no explanation:
{"aadhaar_number": "XXXX XXXX XXXX", "name": "Full Name", "dob": "DD/MM/YYYY"}
If a field is not visible, use an empty string. Do not include anything else in your response.';

    // PDFs use document_url type, images use image_url type
    $media_part = ($ext === 'pdf')
        ? ['type' => 'document_url', 'document_url' => "data:{$mime_type};base64,{$file_data}"]
        : ['type' => 'image_url',    'image_url'    => ['url' => "data:{$mime_type};base64,{$file_data}"]];

    $payload = [
        'model'    => 'pixtral-12b-2409',
        'messages' => [[
            'role'    => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                $media_part,
            ]
        ]],
        'max_tokens' => 300,
    ];

    $ch = curl_init('https://api.mistral.ai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . MISTRAL_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 30,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return $result;

    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? '';

    // Strip markdown code fences if model wraps the JSON
    $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
    $text = preg_replace('/\s*```$/', '', $text);

    $parsed = json_decode(trim($text), true);
    if (is_array($parsed)) {
        $result['aadhaar_number'] = $parsed['aadhaar_number'] ?? '';
        $result['name']           = $parsed['name'] ?? '';
        $result['dob']            = $parsed['dob'] ?? '';
    }

    return $result;
}
