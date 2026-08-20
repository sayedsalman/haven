<?php


declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';



$HAVEN_SMTP_HOST = defined('HAVEN_SMTP_HOST')
    ? HAVEN_SMTP_HOST
    : (getenv('HAVEN_SMTP_HOST') ?: 'smtp.gmail.com');

$HAVEN_SMTP_PORT = defined('HAVEN_SMTP_PORT')
    ? (int) HAVEN_SMTP_PORT
    : (int)(getenv('HAVEN_SMTP_PORT') ?: 587);

$HAVEN_SMTP_USERNAME = defined('HAVEN_SMTP_USERNAME')
    ? HAVEN_SMTP_USERNAME
    : (getenv('HAVEN_SMTP_USERNAME') ?: '');

$HAVEN_SMTP_PASSWORD = defined('HAVEN_SMTP_PASSWORD')
    ? HAVEN_SMTP_PASSWORD
    : (getenv('HAVEN_SMTP_PASSWORD') ?: '');

$HAVEN_SMTP_ENCRYPTION = defined('HAVEN_SMTP_ENCRYPTION')
    ? HAVEN_SMTP_ENCRYPTION
    : (getenv('HAVEN_SMTP_ENCRYPTION') ?: 'tls');

$HAVEN_SMTP_FROM_EMAIL = defined('HAVEN_SMTP_FROM_EMAIL')
    ? HAVEN_SMTP_FROM_EMAIL
    : (getenv('HAVEN_SMTP_FROM_EMAIL') ?: $HAVEN_SMTP_USERNAME);

$HAVEN_SMTP_FROM_NAME = defined('HAVEN_SMTP_FROM_NAME')
    ? HAVEN_SMTP_FROM_NAME
    : (getenv('HAVEN_SMTP_FROM_NAME') ?: 'Haven');

function smtp_read_response($socket): string
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;

        if (preg_match('/^\d{3}\s/', $line)) {
            break;
        }

        if (strlen($response) > 20000) {
            break;
        }
    }

    return $response;
}

function smtp_expect($socket, array $codes): string
{
    $response = smtp_read_response($socket);

    $code = (int)substr(trim($response), 0, 3);

    if (!in_array($code, $codes, true)) {
        throw new RuntimeException(
            'SMTP server rejected the request. SMTP response: ' .
            trim($response)
        );
    }

    return $response;
}

function smtp_command($socket, string $command, array $codes): string
{
    if (fwrite($socket, $command . "\r\n") === false) {
        throw new RuntimeException('Could not write to SMTP server.');
    }

    return smtp_expect($socket, $codes);
}

function send_haven_email(
    string $to,
    string $subject,
    string $html,
    string $fromEmail,
    string $fromName,
    string $smtpHost,
    int $smtpPort,
    string $smtpUsername,
    string $smtpPassword,
    string $encryption = 'tls'
): void {
    if (
        $smtpHost === '' ||
        $smtpPort <= 0 ||
        $smtpUsername === '' ||
        $smtpPassword === '' ||
        !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)
    ) {
        throw new RuntimeException(
            'SMTP is not configured. Set HAVEN_SMTP_HOST, HAVEN_SMTP_PORT, ' .
            'HAVEN_SMTP_USERNAME, HAVEN_SMTP_PASSWORD and HAVEN_SMTP_FROM_EMAIL.'
        );
    }

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid recipient email address.');
    }

    $timeout = 20;

    if (strtolower($encryption) === 'ssl') {
        $remote = 'ssl://' . $smtpHost . ':' . $smtpPort;
    } else {
        $remote = $smtpHost . ':' . $smtpPort;
    }

    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $smtpHost
        ]
    ]);

    $errno = 0;
    $errstr = '';

    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        throw new RuntimeException(
            'Could not connect to SMTP server: ' . $errstr .
            ' (' . $errno . ')'
        );
    }

    stream_set_timeout($socket, $timeout);

    try {
        smtp_expect($socket, [220]);

        $hostname = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $hostname = preg_replace('/:\d+$/', '', $hostname);
        $hostname = preg_replace('/[^a-zA-Z0-9.\-]/', '', $hostname);

        smtp_command($socket, 'EHLO ' . ($hostname ?: 'localhost'), [250]);

        if (strtolower($encryption) === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);

            $cryptoOk = stream_socket_enable_crypto(
                $socket,
                true,
                STREAM_CRYPTO_METHOD_TLS_CLIENT
            );

            if ($cryptoOk !== true) {
                throw new RuntimeException('Could not establish TLS encryption with SMTP server.');
            }

            smtp_command($socket, 'EHLO ' . ($hostname ?: 'localhost'), [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($smtpUsername), [334]);
        smtp_command($socket, base64_encode($smtpPassword), [235]);

        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);

        smtp_command($socket, 'DATA', [354]);

        $safeFromName = addcslashes($fromName, "\\\"");
        $safeSubject = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($subject, 'UTF-8', 'B')
            : $subject;

        $messageId = '<' . bin2hex(random_bytes(12)) . '@' .
            (preg_replace('/[^a-zA-Z0-9.\-]/', '', $hostname ?: 'localhost')) . '>';

        $body =
            'From: "' . $safeFromName . '" <' . $fromEmail . ">\r\n" .
            'To: <' . $to . ">\r\n" .
            'Subject: ' . $safeSubject . "\r\n" .
            "Date: " . date(DATE_RFC2822) . "\r\n" .
            'Message-ID: ' . $messageId . "\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n" .
            "\r\n" .
            $html . "\r\n";

        // SMTP requires every line beginning with "." to be dot-stuffed.
        $body = preg_replace('/^\./m', '..', $body);

        if (fwrite($socket, $body . ".\r\n") === false) {
            throw new RuntimeException('Could not send email body to SMTP server.');
        }

        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221, 250]);
    } finally {
        fclose($socket);
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['register_csrf'])) {
        $_SESSION['register_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['register_csrf'];
}

function verify_csrf(?string $token): bool
{
    return !empty($token)
        && !empty($_SESSION['register_csrf'])
        && hash_equals($_SESSION['register_csrf'], $token);
}

function clean_username(string $username): string
{
    return strtolower(trim($username));
}

function valid_username(string $username): bool
{
    return (bool)preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username);
}

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

function generate_otp(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
|
| Your existing Database class apparently uses db() / getConnection()
| rather than exposing PDO prepare() directly.
|
*/

$db = null;

try {
    /*
     * Most likely based on your existing Haven structure.
     */
    if (class_exists('Database')) {

        /*
         * Try static connection methods first.
         */
        if (method_exists('Database', 'getInstance')) {
            $database = Database::getInstance();

            if (method_exists($database, 'getConnection')) {
                $db = $database->getConnection();
            } elseif (method_exists($database, 'getPdo')) {
                $db = $database->getPdo();
            }
        }

        /*
         * If the project exposes db().
         */
        if (!$db && function_exists('db')) {
            $db = db();
        }
    }

    /*
     * If the database object itself is PDO.
     */
    if ($db instanceof Database) {

        if (method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        } elseif (method_exists($db, 'getPdo')) {
            $db = $db->getPdo();
        }
    }

    if (!$db instanceof PDO) {
        throw new RuntimeException(
            'Could not obtain PDO connection from your existing Database class.'
        );
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    /*
     * AJAX should receive JSON.
     */
    if (
        isset($_POST['action'])
        || isset($_GET['action'])
    ) {
        json_response([
            'success' => false,
            'message' => 'Database connection could not be established.'
        ], 500);
    }

    /*
     * Don't expose database credentials/errors to visitors.
     */
    die(
        '<div style="
            max-width:700px;
            margin:80px auto;
            padding:30px;
            font-family:Arial;
            background:#fff;
            border-radius:20px;
            box-shadow:0 20px 60px rgba(0,0,0,.08);
        ">
        <h2 style="color:#557A68;">Haven registration is temporarily unavailable</h2>
        <p>Please contact the administrator.</p>
        </div>'
    );
}

/*
|--------------------------------------------------------------------------
| AJAX
|--------------------------------------------------------------------------
*/

$action = $_POST['action'] ?? $_GET['action'] ?? null;

/*
|--------------------------------------------------------------------------
| Username availability
|--------------------------------------------------------------------------
*/

if ($action === 'check_username') {

    $username = clean_username($_POST['username'] ?? '');

    if (!valid_username($username)) {
        json_response([
            'success' => true,
            'available' => false,
            'message' => 'Use 3–30 letters, numbers or underscores.'
        ]);
    }

    $stmt = $db->prepare(
        "SELECT id
         FROM users
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->execute([$username]);

    $exists = (bool)$stmt->fetch();

    json_response([
        'success' => true,
        'available' => !$exists,
        'message' => $exists
            ? 'This username is already taken.'
            : 'Username is available.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Email availability
|--------------------------------------------------------------------------
*/

if ($action === 'check_email') {

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!valid_email($email)) {
        json_response([
            'success' => true,
            'available' => false,
            'message' => 'Enter a valid email address.'
        ]);
    }

    $stmt = $db->prepare(
        "SELECT id
         FROM users
         WHERE LOWER(email) = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);

    $exists = (bool)$stmt->fetch();

    json_response([
        'success' => true,
        'available' => !$exists,
        'message' => $exists
            ? 'An account already exists with this email.'
            : 'Email is available.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Send OTP
|--------------------------------------------------------------------------
*/

if ($action === 'send_otp') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        json_response([
            'success' => false,
            'message' => 'Security verification failed.'
        ], 403);
    }

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!valid_email($email)) {
        json_response([
            'success' => false,
            'message' => 'Please provide a valid email address.'
        ], 422);
    }

    /*
     * Check existing account.
     */
    $stmt = $db->prepare(
        "SELECT id, email_verified
         FROM users
         WHERE LOWER(email) = ?
         LIMIT 1"
    );

    $stmt->execute([$email]);

    $existing = $stmt->fetch();

    if ($existing && (int)$existing['email_verified'] === 1) {
        json_response([
            'success' => false,
            'message' => 'This email is already registered.'
        ], 409);
    }

    /*
     * Rate limiting.
     */
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM otp_verifications
         WHERE target = ?
         AND type = 'email'
         AND created_at > (NOW() - INTERVAL 10 MINUTE)"
    );

    $stmt->execute([$email]);

    $recentCount = (int)$stmt->fetchColumn();

    if ($recentCount >= 5) {
        json_response([
            'success' => false,
            'message' => 'Too many verification attempts. Please try again later.'
        ], 429);
    }

    /*
     * Generate OTP.
     */
    $otp = generate_otp();

    /*
     * If user does not exist yet, create temporary registration
     * record using session.
     *
     * We intentionally do NOT create the final account until
     * the email is verified.
     */
    $_SESSION['pending_registration_email'] = $email;

    /*
     * Store OTP.
     *
     * user_id = 0 is used because account is not created yet.
     */
    $stmt = $db->prepare(
        "INSERT INTO otp_verifications
        (
            user_id,
            otp_code,
            type,
            target,
            expires_at,
            is_used,
            attempts
        )
        VALUES
        (
            0,
            ?,
            'email',
            ?,
            DATE_ADD(NOW(), INTERVAL 10 MINUTE),
            0,
            0
        )"
    );

    $stmt->execute([
        password_hash($otp, PASSWORD_DEFAULT),
        $email
    ]);

    /*
     * Email message.
     *
     * For cPanel:
     * - PHP mail() may work if your domain mail is configured.
     * - For production, SMTP/PHPMailer is recommended.
     */
    $subject = 'Your Haven verification code';

    $message = "
    <html>
    <body style='
        margin:0;
        padding:0;
        background:#f5f2eb;
        font-family:Arial,sans-serif;
    '>

        <div style='
            max-width:600px;
            margin:40px auto;
            background:#ffffff;
            border-radius:24px;
            padding:40px;
            box-shadow:0 15px 50px rgba(50,70,60,.08);
        '>

            <h1 style='
                margin:0 0 10px;
                color:#527566;
            '>
                Welcome to Haven 🌿
            </h1>

            <p style='
                color:#66736d;
                font-size:16px;
                line-height:1.7;
            '>
                You're one step away from creating your Haven account.
            </p>

            <div style='
                margin:30px 0;
                padding:25px;
                text-align:center;
                background:#f2f7f3;
                border-radius:18px;
            '>

                <div style='
                    font-size:13px;
                    color:#77827c;
                    margin-bottom:8px;
                '>
                    YOUR VERIFICATION CODE
                </div>

                <div style='
                    font-size:38px;
                    letter-spacing:9px;
                    font-weight:bold;
                    color:#4f7565;
                '>
                    {$otp}
                </div>

            </div>

            <p style='
                color:#77827c;
                line-height:1.6;
            '>
                This code expires in 10 minutes.
                If you did not request this code, you can safely ignore this email.
            </p>

            <p style='
                margin-top:30px;
                color:#527566;
                font-weight:bold;
            '>
                Haven — a gentler place to be.
            </p>

        </div>

    </body>
    </html>
    ";

    /*
     * Send through authenticated SMTP.
     *
     * We deliberately do NOT fall back to PHP mail(), because many hosts
     * return false or silently drop mail() messages when local mail/DMARC
     * is not configured.
     */
    try {
        send_haven_email(
            $email,
            $subject,
            $message,
            $HAVEN_SMTP_FROM_EMAIL,
            $HAVEN_SMTP_FROM_NAME,
            $HAVEN_SMTP_HOST,
            $HAVEN_SMTP_PORT,
            $HAVEN_SMTP_USERNAME,
            $HAVEN_SMTP_PASSWORD,
            $HAVEN_SMTP_ENCRYPTION
        );
    } catch (Throwable $e) {
        error_log(
            'HAVEN SMTP OTP email failed for ' . $email . ': ' . $e->getMessage()
        );

        json_response([
            'success' => false,
            'message' => 'Verification email could not be sent. ' .
                         'Please check the SMTP settings in register.php.'
        ], 500);
    }

    json_response([
        'success' => true,
        'message' => 'Verification code sent. Please check your email.',
        'expires' => 600
    ]);
}

/*
|--------------------------------------------------------------------------
| Verify OTP
|--------------------------------------------------------------------------
*/

if ($action === 'verify_otp') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        json_response([
            'success' => false,
            'message' => 'Security verification failed.'
        ], 403);
    }

    $email = strtolower(trim($_POST['email'] ?? ''));
    $otp   = trim($_POST['otp'] ?? '');

    if (!valid_email($email)) {
        json_response([
            'success' => false,
            'message' => 'Invalid email.'
        ], 422);
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        json_response([
            'success' => false,
            'message' => 'Enter the six-digit code.'
        ], 422);
    }

    $stmt = $db->prepare(
        "SELECT *
         FROM otp_verifications
         WHERE target = ?
         AND type = 'email'
         AND is_used = 0
         AND expires_at > NOW()
         ORDER BY id DESC
         LIMIT 1"
    );

    $stmt->execute([$email]);

    $verification = $stmt->fetch();

    if (!$verification) {

        json_response([
            'success' => false,
            'message' => 'This code has expired. Please request a new code.'
        ], 422);
    }

    /*
     * Attempts.
     */
    if ((int)$verification['attempts'] >= 5) {

        json_response([
            'success' => false,
            'message' => 'Too many incorrect attempts. Please request a new code.'
        ], 429);
    }

    /*
     * Verify hashed OTP.
     */
    if (!password_verify($otp, $verification['otp_code'])) {

        $stmt = $db->prepare(
            "UPDATE otp_verifications
             SET attempts = attempts + 1
             WHERE id = ?"
        );

        $stmt->execute([
            $verification['id']
        ]);

        json_response([
            'success' => false,
            'message' => 'Incorrect verification code.'
        ], 422);
    }

    /*
     * Mark OTP used.
     */
    $stmt = $db->prepare(
        "UPDATE otp_verifications
         SET is_used = 1
         WHERE id = ?"
    );

    $stmt->execute([
        $verification['id']
    ]);

    $_SESSION['verified_registration_email'] = $email;

    json_response([
        'success' => true,
        'message' => 'Email verified successfully.'
    ]);
}

/*
|--------------------------------------------------------------------------
| Final registration
|--------------------------------------------------------------------------
*/

if ($action === 'register') {

    if (!verify_csrf($_POST['csrf'] ?? null)) {
        json_response([
            'success' => false,
            'message' => 'Security verification failed.'
        ], 403);
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $username = clean_username($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $anonymousName = trim($_POST['anonymous_name'] ?? '');

    $country = trim($_POST['country'] ?? '');
    $city = trim($_POST['city'] ?? '');

    $language = trim($_POST['language'] ?? 'en');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $gender = trim($_POST['gender'] ?? '');

    $avatarType = trim($_POST['avatar_type'] ?? 'color');
    $avatarColor = trim($_POST['avatar_color'] ?? '#7BA88E');
    $avatarIcon = trim($_POST['avatar_icon'] ?? 'leaf');

    /*
     * Email must be verified.
     */
    if (
        empty($_SESSION['verified_registration_email'])
        || $_SESSION['verified_registration_email'] !== $email
    ) {
        json_response([
            'success' => false,
            'message' => 'Please verify your email before creating your account.'
        ], 422);
    }

    /*
     * Basic validation.
     */
    if ($fullName !== '' && mb_strlen($fullName) > 100) {
        json_response([
            'success' => false,
            'message' => 'Name is too long.'
        ], 422);
    }

    if (!valid_email($email)) {
        json_response([
            'success' => false,
            'message' => 'Please enter a valid email.'
        ], 422);
    }

    if (!valid_username($username)) {
        json_response([
            'success' => false,
            'message' => 'Username must contain 3–30 letters, numbers or underscores.'
        ], 422);
    }

    if (!password_is_strong($password)) {
        json_response([
            'success' => false,
            'message' => 'Password does not meet the security requirements.'
        ], 422);
    }

    if ($password !== $confirmPassword) {
        json_response([
            'success' => false,
            'message' => 'Passwords do not match.'
        ], 422);
    }

    /*
     * Anonymous name.
     */
    if ($anonymousName === '') {
        $anonymousName = 'Quiet Soul';
    }

    if (mb_strlen($anonymousName) > 50) {
        json_response([
            'success' => false,
            'message' => 'Your Haven name is too long.'
        ], 422);
    }

    /*
     * Avatar colour validation.
     */
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $avatarColor)) {
        $avatarColor = '#7BA88E';
    }

    /*
     * Allowed avatar types.
     */
    $allowedAvatarTypes = [
        'color',
        'icon'
    ];

    if (!in_array($avatarType, $allowedAvatarTypes, true)) {
        $avatarType = 'color';
    }

    /*
     * Allowed genders.
     */
    $allowedGender = [
        '',
        'male',
        'female',
        'other',
        'prefer_not_to_say'
    ];

    if (!in_array($gender, $allowedGender, true)) {
        $gender = '';
    }

    /*
     * Check username and email again immediately before insertion.
     * This protects against race conditions.
     */
    $stmt = $db->prepare(
        "SELECT id
         FROM users
         WHERE username = ?
         LIMIT 1"
    );

    $stmt->execute([
        $username
    ]);

    if ($stmt->fetch()) {
        json_response([
            'success' => false,
            'message' => 'That username has just been taken. Please choose another.'
        ], 409);
    }

    $stmt = $db->prepare(
        "SELECT id
         FROM users
         WHERE LOWER(email) = ?
         LIMIT 1"
    );

    $stmt->execute([
        $email
    ]);

    if ($stmt->fetch()) {
        json_response([
            'success' => false,
            'message' => 'An account with this email already exists.'
        ], 409);
    }

    /*
     * Transaction.
     */
    try {

        $db->beginTransaction();

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        /*
         * Create user.
         */
        $stmt = $db->prepare(
            "INSERT INTO users
            (
                full_name,
                email,
                phone,
                username,
                password_hash,
                role,
                anonymous_name,
                avatar_id,
                avatar_type,
                bio,
                country,
                city,
                language,
                birth_date,
                gender,
                is_active,
                occupation,
                education,
                avatar_color,
                avatar_icon,
                reminder_time,
                email_verified
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                'user',
                ?,
                1,
                ?,
                '',
                ?,
                ?,
                ?,
                ?,
                ?,
                1,
                NULL,
                NULL,
                ?,
                ?,
                '20:00',
                1
            )"
        );

        $stmt->execute([
            $fullName !== '' ? $fullName : null,
            $email,
            $phone !== '' ? $phone : null,
            $username,
            $passwordHash,
            $anonymousName,
            $avatarType,
            $country !== '' ? $country : null,
            $city !== '' ? $city : null,
            $language,
            $birthDate !== '' ? $birthDate : null,
            $gender !== '' ? $gender : null,
            $avatarColor,
            $avatarIcon
        ]);

        $userId = (int)$db->lastInsertId();

        /*
         * Create Haven profile.
         */
        $stmt = $db->prepare(
            "INSERT INTO profiles
            (
                user_id,
                anonymous_name,
                avatar_color,
                mood_energy,
                mood_stress,
                mood_sleep,
                privacy,
                current_chapter,
                current_mood,
                current_mood_emoji
            )
            VALUES
            (
                ?,
                ?,
                ?,
                5,
                5,
                5,
                'public',
                'Taking things one day at a time.',
                'Good',
                '🌱'
            )"
        );

        $stmt->execute([
            $userId,
            $anonymousName,
            $avatarColor
        ]);

        /*
         * Commit.
         */
        $db->commit();

        /*
         * Clear verification session.
         */
        unset(
            $_SESSION['verified_registration_email'],
            $_SESSION['pending_registration_email']
        );

        /*
         * Login session is NOT automatically created.
         *
         * User must login normally.
         */
        json_response([
            'success' => true,
            'message' => 'Your Haven account has been created successfully.',
            'redirect' => 'login.php?registered=1'
        ]);

    } catch (Throwable $e) {

        if ($db->inTransaction()) {
            $db->rollBack();
        }

        error_log(
            'HAVEN registration error: ' . $e->getMessage()
        );

        json_response([
            'success' => false,
            'message' => 'We could not create your account right now. Please try again.'
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| HTML
|--------------------------------------------------------------------------
*/

$csrf = csrf_token();

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="#f5f2eb"
    >

    <title>Create Your Haven Account</title>

    <!-- Google Font -->
    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap"
        rel="stylesheet"
    >

    <!-- GSAP -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"
        defer
    ></script>

    <!-- Anime.js -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.2/anime.min.js"
        defer
    ></script>

    <!-- Three.js -->
    <script
        src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"
        defer
    ></script>

    <style>

        :root {

            --cream: #f5f2eb;
            --cream-2: #fbfaf6;

            --sage: #6f927f;
            --sage-dark: #4f705f;
            --sage-light: #dce9e0;

            --peach: #e8c7b5;
            --text: #34423a;
            --muted: #78827c;

            --white: rgba(255,255,255,.76);

            --shadow:
                18px 18px 40px rgba(92,83,67,.09),
                -12px -12px 30px rgba(255,255,255,.9);

            --radius: 28px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {

            margin:0;

            min-height:100vh;

            font-family:
                "DM Sans",
                sans-serif;

            color:var(--text);

            background:
                radial-gradient(
                    circle at 10% 10%,
                    rgba(217,235,224,.75),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 90% 80%,
                    rgba(236,210,195,.65),
                    transparent 28%
                ),
                var(--cream);

            overflow-x:hidden;
        }

        #three-bg {

            position:fixed;

            inset:0;

            width:100%;
            height:100%;

            pointer-events:none;

            z-index:0;

            opacity:.55;
        }

        .page {

            position:relative;

            z-index:2;

            min-height:100vh;

            display:flex;

            align-items:center;

            justify-content:center;

            padding:40px 20px;
        }

        .shell {

            width:min(1180px,100%);

            display:grid;

            grid-template-columns:
                .8fr 1.2fr;

            gap:30px;
        }

        /*
        |--------------------------------------------------------------------------
        | Welcome Panel
        |--------------------------------------------------------------------------
        */

        .welcome {

            position:relative;

            min-height:700px;

            padding:55px;

            border-radius:38px;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.72),
                    rgba(238,244,239,.68)
                );

            border:
                1px solid rgba(255,255,255,.85);

            box-shadow:var(--shadow);

            overflow:hidden;

            display:flex;

            flex-direction:column;

            justify-content:space-between;
        }

        .welcome::before {

            content:"";

            position:absolute;

            width:280px;
            height:280px;

            border-radius:50%;

            background:
                rgba(197,224,207,.35);

            top:-120px;
            right:-90px;

            filter:blur(2px);
        }

        .brand {

            display:flex;

            align-items:center;

            gap:12px;

            font-weight:700;

            letter-spacing:.03em;
        }

        .brand-mark {

            width:44px;
            height:44px;

            display:grid;

            place-items:center;

            border-radius:15px;

            background:
                linear-gradient(
                    145deg,
                    #dcece2,
                    #edf3ee
                );

            box-shadow:
                7px 7px 14px rgba(100,120,110,.10),
                -5px -5px 12px rgba(255,255,255,.95);

            color:var(--sage-dark);

            font-size:21px;
        }

        .welcome-content {

            position:relative;

            z-index:2;
        }

        .eyebrow {

            display:inline-flex;

            align-items:center;

            gap:7px;

            padding:8px 13px;

            margin-bottom:22px;

            border-radius:999px;

            background:
                rgba(221,235,225,.7);

            color:var(--sage-dark);

            font-size:13px;

            font-weight:600;
        }

        h1 {

            margin:0;

            max-width:540px;

            font-family:
                "Playfair Display",
                serif;

            font-size:
                clamp(42px,5vw,66px);

            line-height:1.04;

            letter-spacing:-.035em;

            color:#40584c;
        }

        .welcome-description {

            max-width:500px;

            margin-top:22px;

            color:var(--muted);

            line-height:1.8;

            font-size:16px;
        }

        .values {

            display:grid;

            gap:13px;

            margin-top:35px;
        }

        .value {

            display:flex;

            align-items:center;

            gap:13px;

            color:#617067;

            font-size:14px;
        }

        .value-icon {

            width:34px;
            height:34px;

            display:grid;

            place-items:center;

            border-radius:12px;

            background:#edf3ee;

            box-shadow:
                inset 2px 2px 5px rgba(80,90,80,.06),
                3px 3px 8px rgba(80,90,80,.07);
        }

        .welcome-footer {

            color:#89938d;

            font-size:12px;

            line-height:1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | Registration Card
        |--------------------------------------------------------------------------
        */

        .register-card {

            padding:35px;

            border-radius:38px;

            background:
                rgba(255,255,255,.72);

            backdrop-filter:blur(24px);

            -webkit-backdrop-filter:blur(24px);

            border:
                1px solid rgba(255,255,255,.9);

            box-shadow:var(--shadow);
        }

        .register-header {

            margin-bottom:26px;
        }

        .register-header h2 {

            margin:0 0 8px;

            font-family:
                "Playfair Display",
                serif;

            font-size:36px;

            color:#425b4e;
        }

        .register-header p {

            margin:0;

            color:var(--muted);

            line-height:1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | Progress
        |--------------------------------------------------------------------------
        */

        .progress {

            display:flex;

            gap:7px;

            margin-bottom:30px;
        }

        .progress-item {

            flex:1;

            height:5px;

            border-radius:999px;

            background:#e5e8e4;

            transition:.4s ease;
        }

        .progress-item.active {

            background:var(--sage);
        }

        /*
        |--------------------------------------------------------------------------
        | Sections
        |--------------------------------------------------------------------------
        */

        .form-step {

            display:none;

            animation:fadeIn .4s ease;
        }

        .form-step.active {

            display:block;
        }

        @keyframes fadeIn {

            from {
                opacity:0;
                transform:translateY(10px);
            }

            to {
                opacity:1;
                transform:none;
            }
        }

        .step-title {

            margin:0 0 20px;

            font-size:20px;

            color:#50655a;
        }

        .grid {

            display:grid;

            grid-template-columns:
                repeat(2,1fr);

            gap:17px;
        }

        .field {

            margin-bottom:17px;
        }

        .field.full {

            grid-column:1/-1;
        }

        label {

            display:block;

            margin-bottom:8px;

            font-size:13px;

            font-weight:600;

            color:#607067;
        }

        .input-wrap {

            position:relative;
        }

        input,
        select {

            width:100%;

            height:52px;

            border:none;

            outline:none;

            padding:
                0 16px;

            border-radius:16px;

            background:
                rgba(246,247,243,.88);

            color:var(--text);

            font-family:inherit;

            font-size:14px;

            box-shadow:
                inset 3px 3px 8px rgba(100,100,90,.055),
                inset -3px -3px 8px rgba(255,255,255,.85);

            transition:
                .25s ease;
        }

        input:focus,
        select:focus {

            background:#fff;

            box-shadow:
                0 0 0 3px rgba(111,146,127,.13),
                inset 2px 2px 6px rgba(100,100,90,.04);
        }

        input.invalid,
        select.invalid {

            box-shadow:
                0 0 0 3px rgba(205,110,110,.13);
        }

        input.valid {

            box-shadow:
                0 0 0 3px rgba(111,146,127,.13);
        }

        .field-message {

            min-height:18px;

            margin-top:6px;

            font-size:11px;

            color:var(--muted);
        }

        .field-message.success {

            color:#5e8b70;
        }

        .field-message.error {

            color:#b66d6d;
        }

        /*
        |--------------------------------------------------------------------------
        | Password
        |--------------------------------------------------------------------------
        */

        .password-meter {

            height:5px;

            margin-top:8px;

            border-radius:99px;

            background:#e7e8e4;

            overflow:hidden;
        }

        .password-meter span {

            display:block;

            width:0;

            height:100%;

            border-radius:inherit;

            transition:.3s;
        }

        .requirements {

            display:grid;

            grid-template-columns:
                repeat(2,1fr);

            gap:6px;

            margin-top:10px;

            font-size:11px;

            color:#929b96;
        }

        .requirement.ok {

            color:#629078;
        }

        /*
        |--------------------------------------------------------------------------
        | Email Verification
        |--------------------------------------------------------------------------
        */

        .verification-box {

            padding:20px;

            border-radius:20px;

            background:
                linear-gradient(
                    145deg,
                    #f0f6f1,
                    #f8f7f1
                );

            border:
                1px solid rgba(216,228,219,.8);

            margin-top:12px;
        }

        .verification-status {

            display:flex;

            align-items:center;

            gap:10px;

            font-size:13px;

            color:#65736b;

            margin-bottom:15px;
        }

        .status-dot {

            width:10px;
            height:10px;

            border-radius:50%;

            background:#d0d6d1;

            transition:.3s;
        }

        .status-dot.verified {

            background:#75a787;

            box-shadow:0 0 0 5px rgba(117,167,135,.12);
        }

        .otp-row {

            display:flex;

            gap:8px;

            margin-top:15px;
        }

        .otp {

            width:52px;

            height:60px;

            padding:0;

            text-align:center;

            font-size:23px;

            font-weight:700;

            border-radius:15px;
        }

        /*
        |--------------------------------------------------------------------------
        | Avatar
        |--------------------------------------------------------------------------
        */

        .avatar-preview {

            width:90px;
            height:90px;

            margin:0 auto 18px;

            border-radius:30px;

            display:grid;

            place-items:center;

            color:#fff;

            font-size:35px;

            background:#7ba88e;

            box-shadow:
                10px 10px 25px rgba(80,90,80,.12),
                -8px -8px 20px rgba(255,255,255,.9);

            transition:.4s ease;
        }

        .avatar-options {

            display:flex;

            flex-wrap:wrap;

            justify-content:center;

            gap:9px;

            margin-bottom:20px;
        }

        .avatar-option {

            width:42px;
            height:42px;

            border:none;

            border-radius:13px;

            cursor:pointer;

            background:#fff;

            box-shadow:
                4px 4px 10px rgba(80,90,80,.08),
                -4px -4px 10px rgba(255,255,255,.9);

            transition:.25s;
        }

        .avatar-option:hover {

            transform:translateY(-3px);
        }

        .avatar-option.active {

            outline:
                3px solid rgba(111,146,127,.22);

            transform:scale(1.05);
        }

        /*
        |--------------------------------------------------------------------------
        | Buttons
        |--------------------------------------------------------------------------
        */

        .actions {

            display:flex;

            gap:12px;

            margin-top:28px;
        }

        button {

            font-family:inherit;
        }

        .btn {

            border:none;

            min-height:52px;

            padding:
                0 22px;

            border-radius:17px;

            cursor:pointer;

            font-weight:700;

            transition:
                transform .2s,
                box-shadow .2s,
                opacity .2s;
        }

        .btn:hover {

            transform:translateY(-2px);
        }

        .btn:active {

            transform:translateY(0);
        }

        .btn-primary {

            flex:1;

            color:#fff;

            background:
                linear-gradient(
                    135deg,
                    #759a86,
                    #5f806f
                );

            box-shadow:
                8px 8px 18px rgba(85,110,95,.15);
        }

        .btn-secondary {

            background:#eef1ed;

            color:#607066;
        }

        .btn:disabled {

            opacity:.5;

            cursor:not-allowed;

            transform:none;
        }

        .verify-button {

            margin-top:10px;

            width:100%;
        }

        /*
        |--------------------------------------------------------------------------
        | Alert
        |--------------------------------------------------------------------------
        */

        .alert {

            display:none;

            padding:13px 15px;

            margin-bottom:18px;

            border-radius:15px;

            font-size:13px;

            line-height:1.5;
        }

        .alert.show {

            display:block;
        }

        .alert.error {

            background:#fff0ef;

            color:#a75f5b;
        }

        .alert.success {

            background:#edf7ef;

            color:#5b866b;
        }

        /*
        |--------------------------------------------------------------------------
        | Login
        |--------------------------------------------------------------------------
        */

        .login-link {

            text-align:center;

            margin-top:25px;

            font-size:13px;

            color:var(--muted);
        }

        .login-link a {

            color:var(--sage-dark);

            font-weight:700;

            text-decoration:none;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media(max-width:900px) {

            .shell {

                grid-template-columns:1fr;
            }

            .welcome {

                min-height:auto;

                padding:38px;
            }

            .welcome-footer {

                margin-top:40px;
            }
        }

        @media(max-width:600px) {

            .page {

                padding:15px;
            }

            .welcome,
            .register-card {

                border-radius:26px;

                padding:25px;
            }

            .welcome {

                min-height:auto;
            }

            h1 {

                font-size:42px;
            }

            .register-header h2 {

                font-size:30px;
            }

            .grid {

                grid-template-columns:1fr;
            }

            .field.full {

                grid-column:auto;
            }

            .otp {

                width:calc((100vw - 100px) / 6);

                max-width:52px;

                height:52px;

                font-size:19px;
            }

            .actions {

                flex-direction:column;
            }
        }

    </style>

</head>

<body>

<canvas id="three-bg"></canvas>

<div class="page">

    <main class="shell">

        <!-- ==========================================================
             WELCOME
        =========================================================== -->

        <section class="welcome">

            <div>

                <div class="brand">

                    <div class="brand-mark">
                        🌿
                    </div>

                    <span>HAVEN</span>

                </div>

            </div>

            <div class="welcome-content">

                <div class="eyebrow">
                    <span>●</span>
                    A gentler digital space
                </div>

                <h1>
                    You don't have to carry everything alone.
                </h1>

                <p class="welcome-description">

                    Haven is a calm community where you can express
                    yourself, discover supportive resources, connect
                    with people and take small steps toward feeling better.

                </p>

                <div class="values">

                    <div class="value">

                        <div class="value-icon">
                            🌱
                        </div>

                        <span>
                            Share at your own pace
                        </span>

                    </div>

                    <div class="value">

                        <div class="value-icon">
                            🫶
                        </div>

                        <span>
                            Receive thoughtful community support
                        </span>

                    </div>

                    <div class="value">

                        <div class="value-icon">
                            🔒
                        </div>

                        <span>
                            Your account stays under your control
                        </span>

                    </div>

                </div>

            </div>

            <div class="welcome-footer">

                Haven provides supportive community tools and AI
                guidance, but it is not a replacement for professional
                medical or emergency care.

            </div>

        </section>

        <!-- ==========================================================
             REGISTER
        =========================================================== -->

        <section class="register-card">

            <div class="register-header">

                <h2>
                    Create your Haven
                </h2>

                <p>
                    Start with a few details. You can change most of
                    them later.
                </p>

            </div>

            <div
                id="alert"
                class="alert"
            ></div>

            <div class="progress">

                <div
                    class="progress-item active"
                    data-progress="1"
                ></div>

                <div
                    class="progress-item"
                    data-progress="2"
                ></div>

                <div
                    class="progress-item"
                    data-progress="3"
                ></div>

            </div>

            <form
                id="registerForm"
                autocomplete="off"
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= h($csrf) ?>"
                >

                <!-- ==================================================
                     STEP 1
                =================================================== -->

                <div
                    class="form-step active"
                    data-step="1"
                >

                    <h3 class="step-title">
                        Let's start with the basics
                    </h3>

                    <div class="grid">

                        <div class="field full">

                            <label>
                                Name
                            </label>

                            <input
                                type="text"
                                name="full_name"
                                maxlength="100"
                                placeholder="Your name"
                            >

                        </div>

                        <div class="field">

                            <label>
                                Username *
                            </label>

                            <input
                                id="username"
                                type="text"
                                name="username"
                                minlength="3"
                                maxlength="30"
                                placeholder="e.g. nightowl"
                                required
                            >

                            <div
                                id="usernameMessage"
                                class="field-message"
                            >
                                3–30 letters, numbers or underscores.
                            </div>

                        </div>

                        <div class="field">

                            <label>
                                Email *
                            </label>

                            <input
                                id="email"
                                type="email"
                                name="email"
                                placeholder="you@example.com"
                                required
                            >

                            <div
                                id="emailMessage"
                                class="field-message"
                            >
                                Your email must be verified.
                            </div>

                        </div>

                        <div class="field">

                            <label>
                                Phone
                            </label>

                            <input
                                type="tel"
                                name="phone"
                                placeholder="+880..."
                            >

                        </div>

                        <div class="field">

                            <label>
                                Language
                            </label>

                            <select name="language">

                                <option value="en">
                                    English
                                </option>

                                <option value="bn">
                                    বাংলা
                                </option>

                            </select>

                        </div>

                        <div class="field">

                            <label>
                                Password *
                            </label>

                            <input
                                id="password"
                                type="password"
                                name="password"
                                placeholder="Create a strong password"
                                required
                            >

                            <div class="password-meter">
                                <span id="passwordMeter"></span>
                            </div>

                            <div class="requirements">

                                <span
                                    id="reqLength"
                                >
                                    ○ 8+ characters
                                </span>

                                <span
                                    id="reqUpper"
                                >
                                    ○ Uppercase
                                </span>

                                <span
                                    id="reqLower"
                                >
                                    ○ Lowercase
                                </span>

                                <span
                                    id="reqNumber"
                                >
                                    ○ Number
                                </span>

                                <span
                                    id="reqSymbol"
                                >
                                    ○ Symbol
                                </span>

                            </div>

                        </div>

                        <div class="field">

                            <label>
                                Confirm password *
                            </label>

                            <input
                                id="confirmPassword"
                                type="password"
                                name="confirm_password"
                                placeholder="Repeat password"
                                required
                            >

                            <div
                                id="passwordMatch"
                                class="field-message"
                            ></div>

                        </div>

                    </div>

                    <div class="actions">

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="nextStep1"
                        >
                            Continue
                            <span>→</span>
                        </button>

                    </div>

                </div>

                <!-- ==================================================
                     STEP 2
                =================================================== -->

                <div
                    class="form-step"
                    data-step="2"
                >

                    <h3 class="step-title">
                        A little about you
                    </h3>

                    <div class="grid">

                        <div class="field">

                            <label>
                                Country
                            </label>

                            <select
                                id="country"
                                name="country"
                            >

                                <option value="">
                                    Select country
                                </option>

                                <option value="Bangladesh">
                                    Bangladesh
                                </option>

                                <option value="United States">
                                    United States
                                </option>

                            </select>

                        </div>

                        <div class="field">

                            <label>
                                City
                            </label>

                            <select
                                id="city"
                                name="city"
                                disabled
                            >

                                <option value="">
                                    Select country first
                                </option>

                            </select>

                        </div>

                        <div class="field">

                            <label>
                                Date of birth
                            </label>

                            <input
                                type="date"
                                name="birth_date"
                                max="<?= date('Y-m-d') ?>"
                            >

                        </div>

                        <div class="field">

                            <label>
                                Gender
                            </label>

                            <select name="gender">

                                <option value="">
                                    Prefer not to say
                                </option>

                                <option value="male">
                                    Male
                                </option>

                                <option value="female">
                                    Female
                                </option>

                                <option value="other">
                                    Other
                                </option>

                            </select>

                        </div>

                        <div class="field full">

                            <label>
                                Your Haven name
                            </label>

                            <input
                                type="text"
                                name="anonymous_name"
                                maxlength="50"
                                placeholder="e.g. Quiet Moon, Night Owl, Gentle Soul"
                            >

                            <div class="field-message">
                                This can be shown instead of your real name in the community.
                            </div>

                        </div>

                    </div>

                    <div class="actions">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-back="1"
                        >
                            ← Back
                        </button>

                        <button
                            type="button"
                            class="btn btn-primary"
                            id="nextStep2"
                        >
                            Continue →
                        </button>

                    </div>

                </div>

                <!-- ==================================================
                     STEP 3
                =================================================== -->

                <div
                    class="form-step"
                    data-step="3"
                >

                    <h3 class="step-title">
                        Make your Haven identity yours
                    </h3>

                    <div class="avatar-preview" id="avatarPreview">
                        🌿
                    </div>

                    <div class="avatar-options">

                        <button
                            type="button"
                            class="avatar-option active"
                            data-icon="🌿"
                            data-color="#7BA88E"
                            style="background:#7BA88E"
                        >
                            🌿
                        </button>

                        <button
                            type="button"
                            class="avatar-option"
                            data-icon="🌙"
                            data-color="#8497B4"
                            style="background:#8497B4"
                        >
                            🌙
                        </button>

                        <button
                            type="button"
                            class="avatar-option"
                            data-icon="☀️"
                            data-color="#D5A66B"
                            style="background:#D5A66B"
                        >
                            ☀️
                        </button>

                        <button
                            type="button"
                            class="avatar-option"
                            data-icon="🌸"
                            data-color="#C88E9B"
                            style="background:#C88E9B"
                        >
                            🌸
                        </button>

                        <button
                            type="button"
                            class="avatar-option"
                            data-icon="🕊️"
                            data-color="#8EA8A1"
                            style="background:#8EA8A1"
                        >
                            🕊️
                        </button>

                        <button
                            type="button"
                            class="avatar-option"
                            data-icon="⭐"
                            data-color="#A493C7"
                            style="background:#A493C7"
                        >
                            ⭐
                        </button>

                    </div>

                    <input
                        type="hidden"
                        name="avatar_type"
                        value="icon"
                    >

                    <input
                        type="hidden"
                        id="avatarColor"
                        name="avatar_color"
                        value="#7BA88E"
                    >

                    <input
                        type="hidden"
                        id="avatarIcon"
                        name="avatar_icon"
                        value="leaf"
                    >

                    <div class="field">

                        <label>
                            Account identity
                        </label>

                        <select name="avatar_type">

                            <option value="icon">
                                Haven avatar
                            </option>

                            <option value="color">
                                Colour avatar
                            </option>

                        </select>

                    </div>

                    <div class="verification-box">

                        <div class="verification-status">

                            <span
                                class="status-dot"
                                id="verificationDot"
                            ></span>

                            <span id="verificationText">
                                Your email must be verified before your Haven account can be created.
                            </span>

                        </div>

                        <button
                            type="button"
                            class="btn btn-primary verify-button"
                            id="sendVerification"
                        >
                            Send verification code
                        </button>

                        <div
                            id="otpArea"
                            style="display:none;"
                        >

                            <div class="field-message">
                                Enter the six-digit code. It will be checked automatically.
                            </div>

                            <div class="otp-row">

                                <input
                                    class="otp"
                                    maxlength="1"
                                    inputmode="numeric"
                                    data-otp="0"
                                >

                                <input
                                    class="otp"
                                    maxlength="1"
                                    inputmode="numeric"
                                    data-otp="1"
                                >

                                <input
                                    class="otp"
                                    maxlength="1"
                                    inputmode="numeric"
                                    data-otp="2"
                                >

                                <input
                                    class="otp"
                                    maxlength="1"
                                    inputmode="numeric"
                                    data-otp="3"
                                >

                                <input
                                    class="otp"
                                    maxlength="1"
                                    inputmode="numeric"
                                    data-otp="4"
                                >

                                <input
                                    class="otp"
                                    maxlength="1"
                                    inputmode="numeric"
                                    data-otp="5"
                                >

                            </div>

                            <div
                                id="otpMessage"
                                class="field-message"
                            ></div>

                            <button
                                type="button"
                                class="btn btn-secondary verify-button"
                                id="resendOtp"
                                disabled
                            >
                                Resend code
                            </button>

                        </div>

                    </div>

                    <div class="actions">

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-back="2"
                        >
                            ← Back
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="createAccount"
                            disabled
                        >
                            Create my Haven 🌿
                        </button>

                    </div>

                </div>

            </form>

            <div class="login-link">

                Already have a Haven account?

                <a href="login.php">
                    Sign in
                </a>

            </div>

        </section>

    </main>

</div>

<script>

/*
|--------------------------------------------------------------------------
| Global
|--------------------------------------------------------------------------
*/

const csrf = <?= json_encode($csrf) ?>;

const form = document.getElementById('registerForm');

let currentStep = 1;

let usernameAvailable = false;
let emailAvailable = false;
let emailVerified = false;

let usernameTimer = null;
let emailTimer = null;

let otpTimer = null;
let resendSeconds = 0;

/*
|--------------------------------------------------------------------------
| Alert
|--------------------------------------------------------------------------
*/

function showAlert(message, type = 'error') {

    const alert = document.getElementById('alert');

    alert.textContent = message;

    alert.className = 'alert show ' + type;

    if (window.gsap) {

        gsap.fromTo(
            alert,
            {
                opacity:0,
                y:-8
            },
            {
                opacity:1,
                y:0,
                duration:.35,
                ease:'power2.out'
            }
        );
    }
}

/*
|--------------------------------------------------------------------------
| API helper
|--------------------------------------------------------------------------
*/

async function api(action, data = {}) {

    const body = new URLSearchParams();

    body.append('action', action);
    body.append('csrf', csrf);

    Object.entries(data).forEach(([key, value]) => {
        body.append(key, value ?? '');
    });

    const response = await fetch(
        window.location.href,
        {
            method:'POST',
            credentials:'same-origin',
            headers:{
                'Content-Type':
                    'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With':
                    'XMLHttpRequest',
                'Accept':
                    'application/json'
            },
            body
        }
    );

    const raw = await response.text();

    let result;

    try {
        result = JSON.parse(raw);
    } catch (parseError) {
        console.error('Registration server response was not JSON:', raw);
        throw new Error(
            'The server returned an invalid response. Check the PHP error log.'
        );
    }

    if (!response.ok && !result.message) {
        result.message =
            'Server error (' + response.status + '). Please try again.';
    }

    return result;
}

/*
|--------------------------------------------------------------------------
| Steps
|--------------------------------------------------------------------------
*/

function showStep(step) {

    currentStep = step;

    document.querySelectorAll('.form-step')
        .forEach(section => {

            section.classList.remove('active');

        });

    const active =
        document.querySelector(
            `.form-step[data-step="${step}"]`
        );

    if (active) {

        active.classList.add('active');

        if (window.gsap) {

            gsap.fromTo(
                active,
                {
                    opacity:0,
                    x:15
                },
                {
                    opacity:1,
                    x:0,
                    duration:.45,
                    ease:'power3.out'
                }
            );
        }
    }

    document.querySelectorAll('.progress-item')
        .forEach(item => {

            const n =
                Number(item.dataset.progress);

            item.classList.toggle(
                'active',
                n <= step
            );
        });

    window.scrollTo({
        top:0,
        behavior:'smooth'
    });
}

document.querySelectorAll('[data-back]')
    .forEach(button => {

        button.addEventListener('click', () => {

            showStep(
                Number(button.dataset.back)
            );

        });

    });

/*
|--------------------------------------------------------------------------
| Password strength
|--------------------------------------------------------------------------
*/

const password =
    document.getElementById('password');

const confirmPassword =
    document.getElementById('confirmPassword');

function updatePassword() {

    const value = password.value;

    const tests = {

        length:
            value.length >= 8,

        upper:
            /[A-Z]/.test(value),

        lower:
            /[a-z]/.test(value),

        number:
            /[0-9]/.test(value),

        symbol:
            /[^A-Za-z0-9]/.test(value)
    };

    document.getElementById('reqLength')
        .classList.toggle('ok', tests.length);

    document.getElementById('reqUpper')
        .classList.toggle('ok', tests.upper);

    document.getElementById('reqLower')
        .classList.toggle('ok', tests.lower);

    document.getElementById('reqNumber')
        .classList.toggle('ok', tests.number);

    document.getElementById('reqSymbol')
        .classList.toggle('ok', tests.symbol);

    Object.entries(tests).forEach(
        ([key, valid]) => {

            const element =
                document.getElementById(
                    'req' +
                    key.charAt(0).toUpperCase() +
                    key.slice(1)
                );

            if (element) {

                element.textContent =
                    (valid ? '✓ ' : '○ ') +
                    element.textContent
                        .replace(/^✓ |^○ /, '');

            }

        }
    );

    const score =
        Object.values(tests)
            .filter(Boolean)
            .length;

    const meter =
        document.getElementById('passwordMeter');

    meter.style.width =
        ((score / 5) * 100) + '%';

    /*
     * Keep styling calm rather than aggressive.
     */
    meter.style.background =
        score <= 2
            ? '#d49b91'
            : score === 3
                ? '#d1b276'
                : '#76a486';

    updatePasswordMatch();
}

function updatePasswordMatch() {

    const message =
        document.getElementById('passwordMatch');

    if (!confirmPassword.value) {

        message.textContent = '';

        return;
    }

    if (
        password.value ===
        confirmPassword.value
    ) {

        message.textContent =
            'Passwords match.';

        message.className =
            'field-message success';

    } else {

        message.textContent =
            'Passwords do not match.';

        message.className =
            'field-message error';
    }
}

password.addEventListener(
    'input',
    updatePassword
);

confirmPassword.addEventListener(
    'input',
    updatePassword
);

/*
|--------------------------------------------------------------------------
| Username realtime check
|--------------------------------------------------------------------------
*/

const username =
    document.getElementById('username');

const usernameMessage =
    document.getElementById('usernameMessage');

username.addEventListener(
    'input',
    () => {

        usernameAvailable = false;

        clearTimeout(usernameTimer);

        const value =
            username.value.trim();

        if (!/^[a-zA-Z0-9_]{3,30}$/.test(value)) {

            usernameMessage.textContent =
                '3–30 letters, numbers or underscores.';

            usernameMessage.className =
                'field-message error';

            username.classList.remove('valid');

            return;
        }

        usernameMessage.textContent =
            'Checking availability...';

        usernameMessage.className =
            'field-message';

        usernameTimer = setTimeout(
            async () => {

                try {

                    const result =
                        await api(
                            'check_username',
                            {
                                username:value
                            }
                        );

                    usernameAvailable =
                        result.available === true;

                    usernameMessage.textContent =
                        result.message;

                    usernameMessage.className =
                        'field-message ' +
                        (
                            usernameAvailable
                                ? 'success'
                                : 'error'
                        );

                    username.classList.toggle(
                        'valid',
                        usernameAvailable
                    );

                } catch(error) {

                    usernameMessage.textContent =
                        'Could not check username.';

                    usernameMessage.className =
                        'field-message error';
                }

            },
            450
        );

    }
);

/*
|--------------------------------------------------------------------------
| Email realtime check
|--------------------------------------------------------------------------
*/

const email =
    document.getElementById('email');

const emailMessage =
    document.getElementById('emailMessage');

email.addEventListener(
    'input',
    () => {

        emailAvailable = false;
        emailVerified = false;

        document
            .getElementById('verificationDot')
            .classList.remove('verified');

        document.getElementById('verificationText').textContent =
            'Your email must be verified before your Haven account can be created.';

        const createAccountButton =
            document.getElementById('createAccount');

        if (createAccountButton) {
            createAccountButton.disabled = true;
        }

        const otpArea = document.getElementById('otpArea');
        if (otpArea) {
            otpArea.style.display = 'none';
        }

        document.querySelectorAll('.otp').forEach(input => {
            input.value = '';
        });

        clearTimeout(emailTimer);

        const value =
            email.value.trim();

        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {

            emailMessage.textContent =
                'Enter a valid email address.';

            emailMessage.className =
                'field-message error';

            return;
        }

        emailMessage.textContent =
            'Checking email...';

        emailMessage.className =
            'field-message';

        emailTimer = setTimeout(
            async () => {

                try {

                    const result =
                        await api(
                            'check_email',
                            {
                                email:value
                            }
                        );

                    emailAvailable =
                        result.available === true;

                    emailMessage.textContent =
                        result.message;

                    emailMessage.className =
                        'field-message ' +
                        (
                            emailAvailable
                                ? 'success'
                                : 'error'
                        );

                } catch(error) {

                    emailMessage.textContent =
                        'Could not check email.';

                    emailMessage.className =
                        'field-message error';
                }

            },
            500
        );

    }
);

/*
|--------------------------------------------------------------------------
| Step 1 validation
|--------------------------------------------------------------------------
*/

document
    .getElementById('nextStep1')
    .addEventListener(
        'click',
        () => {

            updatePassword();

            if (!usernameAvailable) {

                showAlert(
                    'Please choose an available username.'
                );

                username.focus();

                return;
            }

            if (!emailAvailable) {

                showAlert(
                    'Please enter an available email address.'
                );

                email.focus();

                return;
            }

            if (
                !password_is_client_valid()
            ) {

                showAlert(
                    'Please complete all password requirements.'
                );

                password.focus();

                return;
            }

            if (
                password.value !==
                confirmPassword.value
            ) {

                showAlert(
                    'Your passwords do not match.'
                );

                confirmPassword.focus();

                return;
            }

            showStep(2);
        }
    );

function password_is_client_valid() {

    const value = password.value;

    return (
        value.length >= 8 &&
        /[A-Z]/.test(value) &&
        /[a-z]/.test(value) &&
        /[0-9]/.test(value) &&
        /[^A-Za-z0-9]/.test(value)
    );
}

/*
|--------------------------------------------------------------------------
| Country / city
|--------------------------------------------------------------------------
*/

const cities = {

    "Bangladesh":[
        "Dhaka",
        "Chattogram",
        "Sylhet",
        "Rajshahi",
        "Khulna",
        "Barishal",
        "Rangpur",
        "Mymensingh",
        "Cumilla",
        "Cox's Bazar"
    ],

    "United States":[
        "New York",
        "Los Angeles",
        "Chicago",
        "Houston",
        "Phoenix",
        "Philadelphia",
        "San Antonio",
        "San Diego",
        "Dallas",
        "Austin",
        "Seattle",
        "Boston",
        "Washington"
    ]
};

document
    .getElementById('country')
    .addEventListener(
        'change',
        function(){

            const city =
                document.getElementById('city');

            city.innerHTML =
                '<option value="">Select city</option>';

            const list =
                cities[this.value] || [];

            list.forEach(
                name => {

                    const option =
                        document.createElement('option');

                    option.value = name;

                    option.textContent = name;

                    city.appendChild(option);

                }
            );

            city.disabled =
                list.length === 0;
        }
    );

/*
|--------------------------------------------------------------------------
| Step 2
|--------------------------------------------------------------------------
*/

document
    .getElementById('nextStep2')
    .addEventListener(
        'click',
        () => {

            showStep(3);

        }
    );

/*
|--------------------------------------------------------------------------
| Avatar
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll('.avatar-option')
    .forEach(button => {

        button.addEventListener(
            'click',
            () => {

                document
                    .querySelectorAll('.avatar-option')
                    .forEach(
                        item =>
                            item.classList.remove('active')
                    );

                button.classList.add('active');

                const icon =
                    button.dataset.icon;

                const color =
                    button.dataset.color;

                const preview =
                    document.getElementById(
                        'avatarPreview'
                    );

                preview.textContent = icon;

                preview.style.background =
                    color;

                document.getElementById(
                    'avatarColor'
                ).value = color;

                document.getElementById(
                    'avatarIcon'
                ).value = icon;

                if (window.anime) {

                    anime({
                        targets:preview,

                        scale:[
                            {
                                value:1.08,
                                duration:180
                            },
                            {
                                value:1,
                                duration:350
                            }
                        ],

                        easing:'easeOutElastic(1,.5)'
                    });

                }

            }
        );

    });

/*
|--------------------------------------------------------------------------
| Send verification
|--------------------------------------------------------------------------
*/

document
    .getElementById('sendVerification')
    .addEventListener(
        'click',
        sendVerification
    );

async function sendVerification() {

    const emailValue =
        email.value.trim();

    if (!emailAvailable) {

        showAlert(
            'Please enter an available email first.'
        );

        return;
    }

    const button =
        document.getElementById(
            'sendVerification'
        );

    button.disabled = true;

    button.textContent =
        'Sending code...';

    try {

        const result =
            await api(
                'send_otp',
                {
                    email:emailValue
                }
            );

        if (!result.success) {

            showAlert(
                result.message
            );

            button.disabled = false;

            button.textContent =
                'Send verification code';

            return;
        }

        showAlert(
            result.message,
            'success'
        );

        document.getElementById(
            'otpArea'
        ).style.display = 'block';

        document.getElementById(
            'verificationText'
        ).textContent =
            'A verification code has been sent to your email.';

        startResendCountdown();

        setTimeout(
            () => {

                const first =
                    document.querySelector(
                        '.otp[data-otp="0"]'
                    );

                if (first) {
                    first.focus();
                }

            },
            300
        );

    } catch(error) {

        showAlert(
            'Unable to send verification code.'
        );

        button.disabled = false;

        button.textContent =
            'Send verification code';
    }
}

/*
|--------------------------------------------------------------------------
| OTP
|--------------------------------------------------------------------------
*/

const otpInputs =
    document.querySelectorAll('.otp');

otpInputs.forEach(
    (input, index) => {

        input.addEventListener(
            'input',
            () => {

                input.value =
                    input.value.replace(
                        /\D/g,
                        ''
                    );

                if (
                    input.value &&
                    index < otpInputs.length - 1
                ) {

                    otpInputs[index + 1].focus();

                }

                checkOtpAutomatically();

            }
        );

        input.addEventListener(
            'keydown',
            event => {

                if (
                    event.key === 'Backspace' &&
                    !input.value &&
                    index > 0
                ) {

                    otpInputs[index - 1].focus();

                }

            }
        );

        input.addEventListener(
            'paste',
            event => {

                event.preventDefault();

                const text =
                    (
                        event.clipboardData ||
                        window.clipboardData
                    )
                    .getData('text')
                    .replace(/\D/g,'');

                if (text.length === 6) {

                    otpInputs.forEach(
                        (item,i) => {

                            item.value =
                                text[i] || '';

                        }
                    );

                    checkOtpAutomatically();
                }

            }
        );

    }
);

async function checkOtpAutomatically() {

    const otp =
        Array.from(otpInputs)
            .map(input => input.value)
            .join('');

    if (otp.length !== 6) {
        return;
    }

    const message =
        document.getElementById(
            'otpMessage'
        );

    message.textContent =
        'Checking your code...';

    message.className =
        'field-message';

    try {

        const result =
            await api(
                'verify_otp',
                {
                    email:email.value.trim(),
                    otp
                }
            );

        if (result.success) {

            emailVerified = true;

            document
                .getElementById(
                    'verificationDot'
                )
                .classList.add(
                    'verified'
                );

            document
                .getElementById(
                    'verificationText'
                )
                .textContent =
                    'Your email has been verified successfully.';

            message.textContent =
                '✓ Email verified successfully.';

            message.className =
                'field-message success';

            document
                .getElementById(
                    'createAccount'
                )
                .disabled = false;

            /*
             * Celebration animation.
             */
            if (window.anime) {

                anime({
                    targets:
                        '.verification-box',

                    scale:[
                        1,
                        1.015,
                        1
                    ],

                    duration:600,

                    easing:'easeOutQuad'
                });

            }

        } else {

            message.textContent =
                result.message;

            message.className =
                'field-message error';

            otpInputs.forEach(
                input => {

                    input.classList.add(
                        'invalid'
                    );

                }
            );

            setTimeout(
                () => {

                    otpInputs.forEach(
                        input => {

                            input.classList.remove(
                                'invalid'
                            );

                        }
                    );

                },
                800
            );
        }

    } catch(error) {

        message.textContent =
            'Unable to verify the code.';

        message.className =
            'field-message error';
    }
}

/*
|--------------------------------------------------------------------------
| Resend timer
|--------------------------------------------------------------------------
*/

function startResendCountdown() {

    clearInterval(otpTimer);

    resendSeconds = 60;

    const button =
        document.getElementById(
            'resendOtp'
        );

    button.disabled = true;

    otpTimer =
        setInterval(
            () => {

                resendSeconds--;

                button.textContent =
                    `Resend code (${resendSeconds}s)`;

                if (resendSeconds <= 0) {

                    clearInterval(otpTimer);

                    button.disabled = false;

                    button.textContent =
                        'Resend code';
                }

            },
            1000
        );
}

document
    .getElementById('resendOtp')
    .addEventListener(
        'click',
        sendVerification
    );

/*
|--------------------------------------------------------------------------
| Final registration
|--------------------------------------------------------------------------
*/

form.addEventListener(
    'submit',
    async event => {

        event.preventDefault();

        if (!emailVerified) {

            showAlert(
                'Please verify your email first.'
            );

            return;
        }

        const button =
            document.getElementById(
                'createAccount'
            );

        button.disabled = true;

        button.textContent =
            'Creating your Haven...';

        const data =
            new FormData(form);

        const values = {};

        data.forEach(
            (value,key) => {

                values[key] = value;

            }
        );

        /*
         * Remove duplicate avatar_type confusion.
         */
        values.avatar_type =
            document.querySelector(
                'select[name="avatar_type"]'
            ).value;

        try {

            const result =
                await api(
                    'register',
                    values
                );

            if (!result.success) {

                showAlert(
                    result.message
                );

                button.disabled = false;

                button.textContent =
                    'Create my Haven 🌿';

                return;
            }

            showAlert(
                result.message,
                'success'
            );

            /*
             * Beautiful completion animation.
             */
            if (window.gsap) {

                gsap.to(
                    '.register-card',
                    {
                        scale:.98,
                        opacity:.4,
                        duration:.5
                    }
                );
            }

            setTimeout(
                () => {

                    window.location.href =
                        result.redirect;

                },
                1200
            );

        } catch(error) {

            showAlert(
                'Something went wrong while creating your account.'
            );

            button.disabled = false;

            button.textContent =
                'Create my Haven 🌿';
        }

    }
);

/*
|--------------------------------------------------------------------------
| GSAP page entrance
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'load',
    () => {

        if (window.gsap) {

            gsap.from(
                '.welcome',
                {
                    opacity:0,
                    x:-35,
                    duration:1,
                    ease:'power3.out'
                }
            );

            gsap.from(
                '.register-card',
                {
                    opacity:0,
                    x:35,
                    duration:1,
                    delay:.15,
                    ease:'power3.out'
                }
            );

            gsap.from(
                '.brand',
                {
                    opacity:0,
                    y:-15,
                    duration:.7,
                    delay:.35
                }
            );

        }

    }
);

/*
|--------------------------------------------------------------------------
| Three.js peaceful ambient background
|--------------------------------------------------------------------------
*/

function initThree() {

    if (!window.THREE) {
        return;
    }

    const canvas =
        document.getElementById(
            'three-bg'
        );

    const scene =
        new THREE.Scene();

    const camera =
        new THREE.PerspectiveCamera(
            55,
            window.innerWidth /
            window.innerHeight,
            .1,
            100
        );

    camera.position.z = 8;

    const renderer =
        new THREE.WebGLRenderer({
            canvas,
            alpha:true,
            antialias:true
        });

    renderer.setPixelRatio(
        Math.min(
            window.devicePixelRatio,
            1.5
        )
    );

    renderer.setSize(
        window.innerWidth,
        window.innerHeight
    );

    /*
     * Gentle particles.
     */
    const geometry =
        new THREE.BufferGeometry();

    const count = 260;

    const positions =
        new Float32Array(
            count * 3
        );

    for (
        let i = 0;
        i < count * 3;
        i += 3
    ) {

        positions[i] =
            (Math.random() - .5) * 14;

        positions[i + 1] =
            (Math.random() - .5) * 9;

        positions[i + 2] =
            (Math.random() - .5) * 8;
    }

    geometry.setAttribute(
        'position',
        new THREE.BufferAttribute(
            positions,
            3
        )
    );

    const material =
        new THREE.PointsMaterial({

            color:0x789783,

            size:.035,

            transparent:true,

            opacity:.35
        });

    const particles =
        new THREE.Points(
            geometry,
            material
        );

    scene.add(particles);

    /*
     * Soft floating orb.
     */
    const orbGeometry =
        new THREE.SphereGeometry(
            1.3,
            32,
            32
        );

    const orbMaterial =
        new THREE.MeshBasicMaterial({

            color:0xcfe3d5,

            transparent:true,

            opacity:.08
        });

    const orb =
        new THREE.Mesh(
            orbGeometry,
            orbMaterial
        );

    orb.position.set(
        -4,
        2,
        -2
    );

    scene.add(orb);

    function animate() {

        requestAnimationFrame(
            animate
        );

        particles.rotation.y +=
            0.00035;

        particles.rotation.x +=
            0.0001;

        orb.position.y =
            2 +
            Math.sin(
                Date.now() * .0004
            ) * .35;

        renderer.render(
            scene,
            camera
        );
    }

    animate();

    window.addEventListener(
        'resize',
        () => {

            camera.aspect =
                window.innerWidth /
                window.innerHeight;

            camera.updateProjectionMatrix();

            renderer.setSize(
                window.innerWidth,
                window.innerHeight
            );

        }
    );
}

window.addEventListener(
    'load',
    initThree
);

</script>

</body>
</html>
