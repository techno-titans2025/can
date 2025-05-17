<?php

// Ensure UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

// php -m | grep intl
if (!function_exists('idn_to_ascii')) {
    die("<p> PHP intl extension is required for EAI support. Please install/enable it.</p><a href='index.php'>← Back</a>");
}

function isEaiEmail($email)
{
    // Check for non-ASCII 
    return preg_match('/[^\x00-\x7F]/u', $email);
}

function toAsciiDomain($email)
{
    // Normalize email 
    $email = normalizer_normalize($email, Normalizer::FORM_C);

    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return false;
    }

    [$local, $domain] = $parts;

    // Convert to Punycode
    $asciiDomain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46, $info);

    if ($asciiDomain === false) {
        return false;
    }

    return $local . '@' . $asciiDomain;
}

function validateEaiEmail($email)
{
    if (!preg_match('/^[^@]+@[^@]+$/', $email)) {
        return false;
    }

    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        return false;
    }

    [$local, $domain] = $parts;

    if (!preg_match('/^[a-zA-Z0-9\x{00A0}-\x{FFFF}.-]+$/u', $domain)) {
        return false;
    }

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
        <li><strong>Is EAI:</strong> <?= $isEai ? 'Yes' : 'No' ?></li>
        <li><strong>Punycode version:</strong> <?= $asciiEmail ? htmlspecialchars($asciiEmail, ENT_QUOTES, 'UTF-8') : 'Unable to convert' ?></li>
    </ul>
    <a href="index.php">← Back</a>
</body>

</html>