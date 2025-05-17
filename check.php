<?php

// Ensure UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

// Check if intl extension is available
if (!function_exists('idn_to_ascii')) {
    die("<p>⚠️ PHP intl extension is required for EAI support. Please install/enable it.</p><a href='index.php'>← Back</a>");
}

function isEaiEmail($email)
{
    // Check for non-ASCII characters
    return preg_match('/[^\x00-\x7F]/u', $email);
}

function toAsciiDomain($email)
{
    // Normalize email to NFC
    $email = normalizer_normalize($email, Normalizer::FORM_C);
    if ($email === false) {
        error_log("Normalization failed for email: $email");
        return false;
    }

    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        error_log("Invalid email format: $email");
        return false;
    }

    [$local, $domain] = $parts;

    // Convert domain to Punycode
    $asciiDomain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46, $info);
    if ($asciiDomain === false) {
        error_log("Punycode conversion failed for domain: $domain, Info: " . print_r($info, true));
        return false;
    }

    return $local . '@' . $asciiDomain;
}

function validateEaiEmail($email)
{
    // Basic syntax: one @, non-empty local and domain parts
    if (!preg_match('/^[^@]+@[^@]+$/', $email)) {
        error_log("Basic syntax check failed for email: $email");
        return false;
    }

    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        error_log("Invalid part count for email: $email");
        return false;
    }

    [$local, $domain] = $parts;

    // Strip quotes from local part if present
    if (preg_match('/^"(.+)"$/', $local, $matches)) {
        $local = $matches[1];
    }

    // Check if email is EAI
    $isEai = isEaiEmail($email);

    // Local part validation
    if ($isEai) {
        // EAI local part: Unicode letters, numbers, and allowed special characters
        if (!preg_match('/^[\p{L}\p{N}!#$%&\'*+\/=?^_`{|}~-]+$/u', $local)) {
            error_log("EAI local part validation failed for: $local");
            return false;
        }
    } else {
        // Non-EAI local part: ASCII letters, numbers, and allowed special characters, no consecutive dots
        if (!preg_match('/^[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+(\.[a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~-]+)*$/u', $local)) {
            error_log("Non-EAI local part validation failed for: $local");
            return false;
        }
    }

    // Domain validation
    if ($isEai) {
        // EAI domain: Unicode letters, numbers, dots, and hyphens
        if (!preg_match('/^[\p{L}\p{N}.-]+$/u', $domain)) {
            error_log("EAI domain validation failed for: $domain");
            return false;
        }
    } else {
        // Non-EAI domain: ASCII letters, numbers, dots, and hyphens
        if (!preg_match('/^[a-zA-Z0-9.-]+$/u', $domain)) {
            error_log("Non-EAI domain validation failed for: $domain");
            return false;
        }
    }

    // No TLD check for hackathon purposes
    return true;
}

$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';

if (!$email || !validateEaiEmail($email)) {
    echo "<p>⚠️ Invalid email format. Go back and try again.</p>";
    echo "<a href='index.php'>← Back</a>";
    exit;
}

$isEai = isEaiEmail($email);
$asciiEmail = toAsciiDomain($email);

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
        <li><strong>Is EAI:</strong> <?= $isEai ? 'Yes ✅' : 'No ❌' ?></li>
        <li><strong>Punycode version:</strong> <?= $asciiEmail ? htmlspecialchars($asciiEmail, ENT_QUOTES, 'UTF-8') : '❌ Unable to convert (possibly invalid domain)' ?></li>
    </ul>
    <a href="index.php">← Back</a>
</body>

</html>