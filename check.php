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
        error_log("isEaiEmail: Invalid UTF-8 for email: " . bin2hex($email));
        return false;
    }
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        error_log("isEaiEmail: Invalid format for email: $email");
        return false;
    }
    [$local, $domain] = $parts;
    return preg_match('/[^\x00-\x7F]/u', $local) || preg_match('/[^\x00-\x7F]/u', $domain);
}

function toAsciiDomain($email)
{
    if (!isValidUtf8($email)) {
        error_log("toAsciiDomain: Invalid UTF-8 for email: " . bin2hex($email));
        return ['success' => false, 'error' => 'Invalid UTF-8 encoding'];
    }
    $email = normalizer_normalize($email, Normalizer::FORM_C);
    if ($email === false) {
        error_log("toAsciiDomain: Normalization failed for email: $email");
        return ['success' => false, 'error' => 'Normalization failed'];
    }
    $parts = explode('@', $email, 2);
    if (count($parts) !== 2) {
        error_log("toAsciiDomain: Invalid format for email: $email");
        return ['success' => false, 'error' => 'Invalid email format'];
    }
    [$local, $domain] = $parts;
    $asciiDomain = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46, $info);
    if ($asciiDomain === false) {
        error_log("toAsciiDomain: Punycode conversion failed for email: $email, errors: " . json_encode($info));
        return ['success' => false, 'error' => 'Punycode conversion failed: ' . ($info['errors'] ?? 'Unknown error')];
    }
    return ['success' => true, 'result' => $local . '@' . $asciiDomain];
}

// Sanitize input
$email = isset($_GET['email']) ? trim(urldecode($_GET['email'])) : '';
if (!$email) {
    die("<p>⚠️ No email provided. Go back and try again.</p><a href='index.php'>← Back</a>");
}

$isEai = isEaiEmail($email);
$asciiResult = toAsciiDomain($email);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EAI Check Result</title>
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>
    <main>
        <header>
            <div class="header_text">
                <span class="text head">Techno Titans EAI Checker - Results</span>
            </div>
        </header>

        <section>
            <div class="results">
                <h1>Result for: <?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></h1>
                <ul>
                    <li><strong>Is EAI:</strong> <?= $isEai ? 'Yes' : 'No' ?></li>
                    <li><strong>Punycode version:</strong>
                        <?= $asciiResult['success']
                            ? htmlspecialchars($asciiResult['result'], ENT_QUOTES, 'UTF-8')
                            : 'Unable to convert (' . htmlspecialchars($asciiResult['error'], ENT_QUOTES, 'UTF-8') . ')' ?>
                    </li>
                </ul>
                <a href="index.php">← Back</a>
            </div>
        </section>

        <footer>
            <div class="footer_area">
                <div class="footer_box">
                    <span class="footer_text">Techno Titans</span>
                </div>
                <div class="footer_box">
                    <span class="footer_text">9800000000</span>
                </div>
                <div class="footer_box">
                    <span class="footer_text">techno@gmial.com</span>
                </div>
            </div>
        </footer>
    </main>
</body>

</html>