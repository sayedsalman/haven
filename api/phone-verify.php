<?php
/**
 * Phone number verification with a 6-digit OTP (#5), like the Gmail sign-up page.
 *   POST {action:"send",   phone:"+8801XXXXXXXXX"}
 *   POST {action:"verify", phone:"+8801XXXXXXXXX", code:"123456"}
 * SMS delivery: define TWILIO_SID, TWILIO_TOKEN and TWILIO_FROM in includes/config.php to send real SMS.
 * Without them the code is returned in the response ("demo mode") so the flow can be shown without paying for SMS.
 */
require_once __DIR__ . '/_lib.php';
$pdo = hv_pdo();
$d = hv_input();
$action = (string)($d['action'] ?? '');

function hv_norm_phone(string $p): string {
    $p = preg_replace('/[\s\-\(\)\.]/', '', $p);
    return preg_match('/^\+[1-9][0-9]{7,14}$/', $p) ? $p : '';
}
$phone = hv_norm_phone((string)($d['phone'] ?? ''));
if ($phone === '') hv_json(['success' => false, 'message' => 'Enter the number with country code, e.g. +8801712345678.'], 422);

if ($action === 'send') {
    $uid = hv_uid();
    // phone already verified on another account?
    if (hv_has_column($pdo, 'users', 'phone_verified')) {
        $t = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND phone_verified = 1 AND id <> ? LIMIT 1");
        $t->execute([$phone, $uid]);
        if ($t->fetch()) hv_json(['success' => false, 'message' => 'This number is already verified on another account.'], 409);
    }
    $rate = $pdo->prepare("SELECT COUNT(*) FROM phone_verifications WHERE phone = ? AND created_at > (NOW() - INTERVAL 10 MINUTE)");
    $rate->execute([$phone]);
    if ((int)$rate->fetchColumn() >= 3) hv_json(['success' => false, 'message' => 'Too many codes requested. Try again in 10 minutes.'], 429);

    $code = (string)random_int(100000, 999999);
    $pdo->prepare("UPDATE phone_verifications SET is_used = 1 WHERE phone = ? AND is_used = 0")->execute([$phone]);
    $pdo->prepare("INSERT INTO phone_verifications (phone, code_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
        ->execute([$phone, password_hash($code, PASSWORD_DEFAULT)]);

    $sent = false;
    if (defined('TWILIO_SID') && defined('TWILIO_TOKEN') && defined('TWILIO_FROM') && function_exists('curl_init')) {
        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
            CURLOPT_USERPWD => TWILIO_SID . ':' . TWILIO_TOKEN,
            CURLOPT_POSTFIELDS => http_build_query(['To' => $phone, 'From' => TWILIO_FROM, 'Body' => "Your Haven code is $code (valid 10 min)"]),
        ]);
        curl_exec($ch);
        $sent = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 201;
        curl_close($ch);
        if (!$sent) hv_json(['success' => false, 'message' => 'Could not send the SMS. Please try again.'], 502);
    }
    $out = ['success' => true, 'message' => $sent ? 'Code sent by SMS.' : 'Demo mode: no SMS provider configured, so the code is shown below.', 'demo' => !$sent];
    if (!$sent) $out['demo_code'] = $code;
    hv_json($out);
}

if ($action === 'verify') {
    $code = preg_replace('/\D/', '', (string)($d['code'] ?? ''));
    $q = $pdo->prepare("SELECT *, (expires_at > NOW()) AS still_valid FROM phone_verifications WHERE phone = ? AND is_used = 0 ORDER BY id DESC LIMIT 1");
    $q->execute([$phone]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row || !(int)$row['still_valid']) hv_json(['success' => false, 'message' => 'Code expired. Request a new one.'], 410);
    if ((int)$row['attempts'] >= 5) hv_json(['success' => false, 'message' => 'Too many wrong attempts. Request a new code.'], 429);
    if (!password_verify($code, $row['code_hash'])) {
        $pdo->prepare("UPDATE phone_verifications SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
        hv_json(['success' => false, 'message' => 'Wrong code.'], 422);
    }
    $pdo->prepare("UPDATE phone_verifications SET is_used = 1 WHERE id = ?")->execute([$row['id']]);
    $_SESSION['phone_verified'] = ['phone' => $phone, 'at' => time()];
    if (hv_uid() && hv_has_column($pdo, 'users', 'phone_verified')) {
        $pdo->prepare("UPDATE users SET phone = ?, phone_verified = 1, phone_verified_at = NOW() WHERE id = ?")->execute([$phone, hv_uid()]);
    }
    hv_json(['success' => true, 'message' => 'Phone number verified.', 'phone' => $phone]);
}
hv_json(['success' => false, 'message' => 'Unknown action'], 400);
