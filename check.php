<?php
// Ensure UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

// Check for intl extension
if (!function_exists('idn_to_ascii')) {
    die("<p>PHP intl extension is required for EAI support. Please install/enable it.</p><a href='index.php'>← Back</a>");
}

function isValidUtf8($string)
{
    return mb_check_encoding($string, 'UTF-8');
}

function isEaiEmail($email)
{
    if (!isValidUtf8($email)) {
        return false;
    }
    // Check for non-ASCII in local part or domain
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$local, $domain] = $parts;
    return preg_match('/[^\x00-\x7F]/u', $local) || preg_match('/[^\x00-\x7F]/u', $domain);
}

function toAsciiDomain($email)
{
    if (!isValidUtf8($email)) {
        return ['success' => false, 'error' => 'Invalid UTF-8 encoding'];
    }
    $email = normalizer_normalize($email, Normalizer::FORM_C);
    if ($email === false) {
        return ['success' => false, 'error' => 'Normalization failed'];
    }
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return ['success' => false, 'error' => 'Invalid email format'];
    }
    [$local, $domain] = $parts;
    $asciiDomain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46, $info);
    if ($asciiDomain === false) {
        return ['success' => false, 'error' => 'Punycode conversion failed: ' . ($info['errors'] ?? 'Unknown error')];
    }
    return ['success' => true, 'result' => $local . '@' . $asciiDomain];
}

function validateEaiEmail($email)
{
    if (!isValidUtf8($email)) {
        return ['valid' => false, 'error' => 'Invalid UTF-8 encoding'];
    }
    // Basic format: local@domain
    if (!preg_match('/^[^@]+@[^@]+$/', $email)) {
        return ['valid' => false, 'error' => 'Must contain exactly one @'];
    }
    $parts = explode('@', $email, 2);
    [$local, $domain] = $parts;

    // Local part: RFC 6531 allows printable UTF-8, but we restrict for safety
    if (!preg_match('/^[a-zA-Z0-9\x{00A0}-\x{FFFF}!#$%&\'*+\/=?^_`{|}~-]+$/u', $local)) {
        return ['valid' => false, 'error' => 'Invalid local part'];
    }
    if (strlen($local) > 64) {
        return ['valid' => false, 'error' => 'Local part too long (>64 bytes)'];
    }

    // Domain: RFC 5890/5891 (IDNA2008) + dots and hyphens
    if (!preg_match('/^[a-zA-Z0-9\x{00A0}-\x{FFFF}.-]+$/u', $domain)) {
        return ['valid' => false, 'error' => 'Invalid domain characters'];
    }
    if (strlen($domain) > 255) {
        return ['valid' => false, 'error' => 'Domain too long (>255 bytes)'];
    }
    // Check domain labels
    $labels = explode('.', $domain);
    foreach ($labels as $label) {
        if (strlen($label) > 63) {
            return ['valid' => false, 'error' => 'Domain label too long (>63 bytes)'];
        }
        if ($label === '' || $label === '-' || str_starts_with($label, '-') || str_ends_with($label, '-')) {
            return ['valid' => false, 'error' => 'Invalid domain label'];
        }
    }
    return ['valid' => true];
}

// Sanitize input
$email = isset($_GET['email']) ? trim(urldecode($_GET['email'])) : '';
if (!$email) {
    die("<p>⚠️ No email provided. Go back and try again.</p><a href='index.php'>← Back</a>");
}

$validation = validateEaiEmail($email);
if (!$validation['valid']) {
    echo "<p>⚠️ Invalid email format: " . htmlspecialchars($validation['error'], ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<a href='index.php'>← Back</a>";
    exit;
}

$isEai = isEaiEmail($email);
$asciiResult = toAsciiDomain($email);
?>

<!DOCTYPE html>
<html>

<head>
    <title>EAI Check Result</title>
    <meta charset="UTF-8">
</head>

<body>
    <h1>Result for: <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></h1>
    <ul>
        <li><strong>Is EAI:</strong> <?= $isEai ? 'Yes' : 'No' ?></li>
        <li><strong>Punycode version:</strong> <?= $asciiResult['success'] ? htmlspecialchars($asciiResult['result'], ENT_QUOTES, 'UTF-8') : 'Unable to convert (' . htmlspecialchars($asciiResult['error'], ENT_QUOTES, 'UTF-8') . ')' ?></li>
    </ul>
    <a href="index.php">← Back</a>
</body>

</html>