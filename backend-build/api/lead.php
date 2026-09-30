<?php
// ============================================================================
// POST /api/lead.php — contact / pilot-request form submissions
// Accepts: application/json  OR  application/x-www-form-urlencoded
// Fields: name*, email*, phone, business_type, message*, source_page
// Honeypot field: "website" (bots fill it; humans never see it)
// Rate limit: 5 submissions / hour / IP
// Stores into `leads` with status='new'. Returns JSON.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    vq_json(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

// --- Parse input (JSON body preferred, form fields as fallback) -------------
$in = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $in = $decoded;
    }
}

// --- Honeypot: silently "succeed" so bots learn nothing ----------------------
if (!empty($in['website'])) {
    vq_json(['ok' => true, 'id' => null]);
}

// --- Rate limit ---------------------------------------------------------------
if (!vq_rate_limit('lead', 5, 3600)) {
    vq_json(['ok' => false, 'error' => 'Too many requests. Please try again later.'], 429);
}

// --- Validate -----------------------------------------------------------------
$errors = [];

$name         = trim((string) ($in['name'] ?? ''));
$email        = trim((string) ($in['email'] ?? ''));
$phone        = trim((string) ($in['phone'] ?? ''));
$businessType = trim((string) ($in['business_type'] ?? ''));
$message      = trim((string) ($in['message'] ?? ''));
$sourcePage   = trim((string) ($in['source_page'] ?? ''));

if ($name === '' || mb_strlen($name) > 100) {
    $errors['name'] = 'Please enter your name.';
}
if ($email === '' || mb_strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($phone !== '' && !preg_match('/^[+\d][\d\s\-()]{5,25}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}
$allowedTypes = ['real-estate', 'hotel', 'clinic', 'hospital', 'other', ''];
if (!in_array($businessType, $allowedTypes, true)) {
    $errors['business_type'] = 'Invalid business type.';
}
if ($message === '' || mb_strlen($message) > 2000) {
    $errors['message'] = 'Please enter a message (max 2000 characters).';
}
if (mb_strlen($sourcePage) > 255) {
    $sourcePage = mb_substr($sourcePage, 0, 255);
}

if ($errors !== []) {
    vq_json(['ok' => false, 'errors' => $errors], 422);
}

// --- Store --------------------------------------------------------------------
try {
    $pdo = vq_pdo();
    $stmt = $pdo->prepare(
        'INSERT INTO leads (name, email, phone, business_type, message, source_page, status)
         VALUES (:name, :email, :phone, :business_type, :message, :source_page, \'new\')'
    );
    $stmt->execute([
        ':name'          => $name,
        ':email'         => $email,
        ':phone'         => $phone !== '' ? $phone : null,
        ':business_type' => $businessType !== '' ? $businessType : null,
        ':message'       => $message,
        ':source_page'   => $sourcePage !== '' ? $sourcePage : null,
    ]);

    vq_json(['ok' => true, 'id' => (int) $pdo->lastInsertId()], 201);
} catch (Throwable $e) {
    error_log('[vq] lead.php insert failed: ' . $e->getMessage());
    vq_json(['ok' => false, 'error' => 'Could not save your enquiry. Please try again later.'], 500);
}
