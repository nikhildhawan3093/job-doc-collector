<?php
/**
 * Generate Aadhaar PDF Report
 * Generates a structured PDF for a candidate and saves the path to pdf_reports table.
 * Access: hiring manager only (session required).
 */

session_start();
require_once '../config/db.php';
require_once '../vendor/autoload.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access denied.");
}

$application_id = (int)($_GET['id'] ?? 0);
if (!$application_id) {
    die("Invalid application ID.");
}

// Fetch application (must belong to logged-in user)
$app = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM applications WHERE id = $1 AND created_by = $2",
    [$application_id, $_SESSION['user_id']]
));

if (!$app) {
    die("Application not found.");
}

// Fetch Aadhaar document + extracted data
$doc = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM documents WHERE application_id = $1 AND document_type = 'aadhaar' AND processed_status = 'done'",
    [$application_id]
));

if (!$doc) {
    die("Aadhaar data not available. Please ensure the Aadhaar is uploaded and processed.");
}

$aadhaar = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM aadhaar_data WHERE document_id = $1",
    [$doc['id']]
));

if (!$aadhaar) {
    die("Aadhaar extracted data not found.");
}

// Fetch resume extracted data (optional — included if available)
$resume_doc = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM documents WHERE application_id = $1 AND document_type = 'resume' AND processed_status = 'done'",
    [$application_id]
));
$resume = $resume_doc
    ? pg_fetch_assoc(pg_query_params($conn, "SELECT * FROM resume_data WHERE document_id = $1", [$resume_doc['id']]))
    : null;

// ─── Build PDF using FPDF ───

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetMargins(20, 20, 20);

// Header bar
$pdf->SetFillColor(74, 144, 226); // blue
$pdf->Rect(0, 0, 210, 18, 'F');
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 13);
$pdf->SetXY(20, 5);
$pdf->Cell(0, 8, 'Job Doc Collector – Candidate Report', 0, 1, 'L');

// Reset text color
$pdf->SetTextColor(51, 51, 51);
$pdf->Ln(10);

// Section: Candidate Information
_pdf_section_title($pdf, 'Candidate Information');

_pdf_row($pdf, 'Full Name',    $app['candidate_name']);
_pdf_row($pdf, 'Email',        $app['candidate_email']);
_pdf_row($pdf, 'Role Applied', $app['role']);
_pdf_row($pdf, 'Applied On',   date('d M Y', strtotime($app['created_at'])));

$pdf->Ln(6);

// Section: Aadhaar Details
_pdf_section_title($pdf, 'Aadhaar Details');

// Format aadhaar number as XXXX XXXX XXXX
$num = $aadhaar['aadhaar_number'];
$formatted_number = implode(' ', str_split($num, 4));

_pdf_row($pdf, 'Aadhaar Number', $formatted_number);
_pdf_row($pdf, 'Name on Aadhaar', $aadhaar['name']);
_pdf_row($pdf, 'Date of Birth',   $aadhaar['dob']);
_pdf_row($pdf, 'Blur Status',     ucfirst($doc['blur_status']));
_pdf_row($pdf, 'Extracted On',    date('d M Y', strtotime($aadhaar['extracted_at'])));

$pdf->Ln(6);

// Section: Resume Details (only if extracted)
if ($resume) {
    _pdf_section_title($pdf, 'Resume Details');

    _pdf_row($pdf, 'Name',      $resume['name']);
    _pdf_row($pdf, 'Email',     $resume['email']);
    _pdf_row($pdf, 'Phone',     $resume['phone']);
    if (!empty($resume['address']))  _pdf_row($pdf, 'Address',  $resume['address']);
    if (!empty($resume['linkedin'])) _pdf_row($pdf, 'LinkedIn', $resume['linkedin']);
    if (!empty($resume['github']))   _pdf_row($pdf, 'GitHub',   $resume['github']);
    _pdf_row($pdf, 'Education', $resume['education']);

    // Skills — may be long, use MultiCell
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(55, 7, 'Skills:', 0, 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(33, 33, 33);
    $pdf->MultiCell(0, 6, $resume['skills']);

    if ($resume['latest_company'] || $resume['latest_role']) {
        $pdf->Ln(2);
        _pdf_section_title($pdf, 'Latest Experience');
        _pdf_row($pdf, 'Company',    $resume['latest_company']);
        _pdf_row($pdf, 'Role',       $resume['latest_role']);
        _pdf_row($pdf, 'Start Date', $resume['latest_start_date']);
        _pdf_row($pdf, 'End Date',   $resume['latest_end_date']);
    }

    $pdf->Ln(6);
}

// Footer
$pdf->SetY(-20);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(150, 150, 150);
$pdf->Cell(0, 6, 'Generated on ' . date('d M Y, H:i') . ' | Job Doc Collector', 0, 0, 'C');

// ─── Save PDF to file ───
$pdf_dir      = '../uploads/reports/';
if (!is_dir($pdf_dir)) mkdir($pdf_dir, 0755, true);

$pdf_filename = 'report_' . $application_id . '_' . time() . '.pdf';
$pdf_path     = $pdf_dir . $pdf_filename;
$pdf->Output('F', $pdf_path);

$pdf_url = 'uploads/reports/' . $pdf_filename;

// Delete previous report for this application (keep only latest)
$old_report = pg_fetch_assoc(pg_query_params($conn,
    "SELECT * FROM pdf_reports WHERE document_id = $1",
    [$doc['id']]
));

if ($old_report) {
    $old_file = '../' . $old_report['pdf_path'];
    if (file_exists($old_file)) unlink($old_file);

    pg_query_params($conn,
        "UPDATE pdf_reports SET pdf_path = $1, generated_at = CURRENT_TIMESTAMP WHERE id = $2",
        [$pdf_url, $old_report['id']]
    );
} else {
    pg_query_params($conn,
        "INSERT INTO pdf_reports (document_id, pdf_path) VALUES ($1, $2)",
        [$doc['id'], $pdf_url]
    );
}

// Redirect back to application detail page
header('Location: application_detail.php?id=' . $application_id . '&report=generated');
exit;

// ─── Helper functions ───

/** Renders a coloured section title */
function _pdf_section_title(FPDF $pdf, string $title): void
{
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(240, 244, 251);
    $pdf->SetTextColor(74, 144, 226);
    $pdf->Cell(0, 8, $title, 0, 1, 'L', true);
    $pdf->SetTextColor(51, 51, 51);
    $pdf->Ln(2);
}

/** Renders a label + value row */
function _pdf_row(FPDF $pdf, string $label, string $value): void
{
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(55, 7, $label . ':', 0, 0);

    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(33, 33, 33);
    $pdf->Cell(0, 7, $value, 0, 1);
}
