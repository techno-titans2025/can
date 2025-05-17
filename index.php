<?php
// Ensure UTF-8 encoding
header('Content-Type: text/html; charset=UTF-8');

// Check for intl extension
if (!function_exists('idn_to_ascii')) {
    die("<p>PHP intl extension is required for EAI support. Please install/enable it.</p>");
}

function isValidUtf8($string)
{
    return mb_check_encoding($string, 'UTF-8');
}

function validateEaiEmail($email)
{
    if (!isValidUtf8($email)) {
        error_log("validateEaiEmail: Invalid UTF-8 for email: " . bin2hex($email));
        return ['valid' => false, 'error' => 'Invalid UTF-8 encoding'];
    }
    if (!preg_match('/^[^@]+@[^@]+$/', $email)) {
        error_log("validateEaiEmail: Invalid format for email: $email");
        return ['valid' => false, 'error' => 'Must contain exactly one @'];
    }
    $parts = explode('@', $email, 2);
    [$local, $domain] = $parts;

    // Local part: Allow Unicode letters, numbers, combining marks, and common symbols
    if (!preg_match('/^[\p{L}\p{N}\p{M}!#$%&\'*+\/=?^_`{|}~.-]+$/u', $local)) {
        error_log("validateEaiEmail: Local part failed Unicode-compatible validation for: $local");
        return ['valid' => false, 'error' => 'Local part contains invalid characters'];
    }
    if ($local === '' || preg_match('/^\.|\.$|\.\./u', $local)) {
        error_log("validateEaiEmail: Invalid local part pattern for email: $email");
        return ['valid' => false, 'error' => 'Invalid local part: empty, starts/ends with dot, or consecutive dots'];
    }
    $normalizedLocal = normalizer_normalize($local, Normalizer::FORM_C);
    if ($normalizedLocal === false || strlen($normalizedLocal) > 64) {
        error_log("validateEaiEmail: Local part normalization failed or too long for email: $email");
        return ['valid' => false, 'error' => 'Local part too long (>64 bytes) or invalid normalization'];
    }

    // Domain: RFC 5890/5891
    if (!preg_match('/^[a-zA-Z0-9\x{00A0}-\x{FFFF}.-]+$/u', $domain)) {
        error_log("validateEaiEmail: Invalid domain characters for email: $email");
        return ['valid' => false, 'error' => 'Invalid domain characters'];
    }
    if (strlen($domain) > 255) {
        error_log("validateEaiEmail: Domain too long for email: $email");
        return ['valid' => false, 'error' => 'Domain too long (>255 bytes)'];
    }
    $labels = explode('.', $domain);
    if (count($labels) < 2) {
        error_log("validateEaiEmail: Too few domain labels for email: $email");
        return ['valid' => false, 'error' => 'Domain must have at least two labels'];
    }
    foreach ($labels as $label) {
        if ($label === '' || $label === '-' || str_starts_with($label, '-') || str_ends_with($label, '-')) {
            error_log("validateEaiEmail: Invalid domain label for email: $email");
            return ['valid' => false, 'error' => 'Invalid domain label'];
        }
        $normalizedLabel = normalizer_normalize($label, Normalizer::FORM_C);
        if ($normalizedLabel === false || strlen($normalizedLabel) > 63) {
            error_log("validateEaiEmail: Domain label normalization failed or too long for email: $email");
            return ['valid' => false, 'error' => 'Domain label too long (>63 bytes) or invalid normalization'];
        }
    }
    return ['valid' => true];
}

// Process form submission
$email = isset($_GET['email']) ? trim(urldecode($_GET['email'])) : '';
$error = '';

if ($email) {
    $validation = validateEaiEmail($email);
    if (!$validation['valid']) {
        $error = $validation['error'];
    } else {
        // Redirect to check.php with encoded email
        header('Location: check.php?email=' . urlencode($email));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EAI CHECKER</title>
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>
    <main>
        <header>
            <div class="header_text">
                <span class="text head">Techno Titans EAI checker</span>
            </div>
        </header>

        <section>
            <div class="form_box">
                <?php if ($error): ?>
                    <p class="error">⚠️ Invalid email format: <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form action="index.php" method="get" accept-charset="UTF-8">
                    <div class="box">
                        <div class="email_text_box">
                            <span class="text">Email</span>
                        </div>
                        <div class="textbox">
                            <input
                                placeholder="Enter Your Email"
                                type="name"
                                name="email"
                                id="email"
                                class="text"
                                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                                required />
                        </div>
                    </div>
                    <div class="box">
                        <div class="submit_botton">
                            <button type="submit" name="submit" class="botton">
                                <span class="text btn">Submit</span>
                            </button>
                        </div>
                    </div>
                </form>
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