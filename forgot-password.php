<?php
/**
 * HAVEN
 * Forgot Password — Request Reset Link
 *
 * Matches register.php's visual language and DB-connection
 * resilience. Uses includes/mailer.php (PHPMailer) for sending.
 */

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function rlog(string $m, array $c = []): void
{
    $d = __DIR__ . '/logs';

    if (!is_dir($d)) {
        @mkdir($d, 0755, true);
    }

    @error_log(
        date('Y-m-d H:i:s') . ' | ' . $m . ' | ' .
        json_encode($c, JSON_UNESCAPED_UNICODE) . PHP_EOL,
        3,
        $d . '/password_reset.log'
    );

    @error_log($m . ' | ' . json_encode($c));
}

function csrf(): string
{
    if (empty($_SESSION['reset_csrf'])) {
        $_SESSION['reset_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['reset_csrf'];
}

function csrf_ok(?string $v): bool
{
    return !empty($v)
        && !empty($_SESSION['reset_csrf'])
        && hash_equals($_SESSION['reset_csrf'], $v);
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
|
| Same resilient connection logic as register.php, so this page
| works with whatever your Database class actually exposes
| instead of assuming a bare $pdo global exists.
|
*/

$db = null;

try {

    if (class_exists('Database')) {

        if (method_exists('Database', 'getInstance')) {
            $database = Database::getInstance();

            if (method_exists($database, 'getConnection')) {
                $db = $database->getConnection();
            } elseif (method_exists($database, 'getPdo')) {
                $db = $database->getPdo();
            }
        }

        if (!$db && function_exists('db')) {
            $db = db();
        }
    }

    if ($db instanceof Database) {
        if (method_exists($db, 'getConnection')) {
            $db = $db->getConnection();
        } elseif (method_exists($db, 'getPdo')) {
            $db = $db->getPdo();
        }
    }

    if (!$db instanceof PDO && isset($pdo) && $pdo instanceof PDO) {
        $db = $pdo;
    }

    if (!$db instanceof PDO) {
        throw new RuntimeException(
            'Could not obtain a PDO connection.'
        );
    }

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    rlog('DB connection failed', ['error' => $e->getMessage()]);

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
        <h2 style="color:#557A68;">Password reset is temporarily unavailable</h2>
        <p>Please contact the administrator.</p>
        </div>'
    );
}

if (!defined('RESET_TOKEN_LIFE')) {
    define('RESET_TOKEN_LIFE', 3600);
}

$errors  = [];
$success = '';
$email   = '';

/*
|--------------------------------------------------------------------------
| Mail
|--------------------------------------------------------------------------
*/

function sendResetEmail(string $to, string $username, string $resetLink): array
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->Subject = 'Haven — Password Reset Request';
        $mail->isHTML(true);

        $logoUrl     = 'https://salman.rfnhsc.com/mind/logo.png';
        $logoContent = @file_get_contents($logoUrl);

        if ($logoContent !== false) {
            $mail->addStringEmbeddedImage(
                $logoContent,
                'logo',
                'logo.png',
                'base64',
                'image/png'
            );
        }

        $mail->Body    = buildResetEmailHTML($username, $resetLink);
        $mail->AltBody =
            "Hello {$username},\n\n" .
            "We received a request to reset your Haven password.\n" .
            "Open this link to choose a new one:\n{$resetLink}\n\n" .
            "This link expires in 1 hour.\n\n— Haven";

        $mail->send();

        return ['success' => true, 'error' => null];

    } catch (Exception $e) {
        rlog('Reset email failed', ['error' => $mail->ErrorInfo]);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

function buildResetEmailHTML(string $username, string $resetLink): string
{
    $u = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset your Haven password</title>
<style>
    body { margin:0; padding:0; background:#f5f2eb; font-family:'DM Sans', Arial, sans-serif; }
    .container { max-width:560px; margin:40px auto; background:#fbfaf6; border-radius:28px;
        padding:36px 40px; box-shadow:0 20px 50px rgba(92,83,67,.08); }
    .header { text-align:center; margin-bottom:22px; }
    .logo { max-width:64px; height:auto; display:block; margin:0 auto 10px; }
    .greeting { font-family:'Playfair Display', serif; font-size:26px; font-weight:600; color:#40584c; margin:4px 0 6px; }
    .sub { font-size:15px; color:#78827c; margin:0 0 18px; }
    .body-text { font-size:16px; line-height:1.75; color:#34423a; margin:16px 0; }
    .btn-wrap { text-align:center; margin:30px 0 22px; }
    .btn { display:inline-block; background:linear-gradient(135deg,#759a86,#5f806f); color:#ffffff !important;
        text-decoration:none; padding:14px 34px; border-radius:40px; font-weight:700; font-size:16px; }
    .footer { font-size:13px; color:#8f9b91; border-top:1px solid #e9ede4; padding-top:16px; margin-top:26px; text-align:center; line-height:1.5; }
    .alt-link { word-break:break-all; font-size:13px; background:#f2f5f0; padding:10px 14px; border-radius:12px; margin:12px 0; color:#4f705f; }
    .highlight { color:#5f806f; font-weight:600; }
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="cid:logo" alt="Haven" class="logo">
        <div class="greeting">Password Reset</div>
        <div class="sub">Hello, <span class="highlight">{$u}</span></div>
    </div>
    <div class="body-text">
        We received a request to reset your password for your Haven account.
        Click the button below to choose a new one.
    </div>
    <div class="btn-wrap">
        <a href="{$l}" class="btn">Reset Password</a>
    </div>
    <div class="body-text" style="font-size:14px; color:#78827c;">
        If the button doesn't work, copy and paste this link into your browser:
    </div>
    <div class="alt-link">{$l}</div>
    <div class="body-text" style="font-size:14px; color:#78827c; margin-top:8px;">
        This link expires in <strong>1 hour</strong>.
    </div>
    <div class="footer">
        If you didn't request this, you can safely ignore this email.<br>
        &copy; 2026 Haven. All rights reserved.
    </div>
</div>
</body>
</html>
HTML;
}

/*
|--------------------------------------------------------------------------
| Handle POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'request_reset'
) {

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (!csrf_ok($_POST['csrf'] ?? '')) {
        $errors[] = 'Security check failed. Please refresh and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {

        try {
            $stmt = $db->prepare(
                'SELECT id, username
                 FROM users
                 WHERE LOWER(email) = LOWER(?)
                 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $success = 'If that email is registered, we\'ve sent a password reset link.';
            } else {

                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_LIFE);

                $del = $db->prepare(
                    'DELETE FROM password_resets WHERE user_id = ?'
                );
                $del->execute([$user['id']]);

                $ins = $db->prepare(
                    'INSERT INTO password_resets (user_id, token, expires_at)
                     VALUES (?, ?, ?)'
                );
                $ins->execute([$user['id'], $token, $expires]);

                $resetLink =
                    (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') .
                    $_SERVER['HTTP_HOST'] .
                    rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') .
                    '/reset-password.php?token=' . urlencode($token);

                $mailResult = sendResetEmail(
                    $email,
                    $user['username'],
                    $resetLink
                );

                if ($mailResult['success']) {
                    $success = 'If that email is registered, we\'ve sent a password reset link.';
                } else {
                    $errors[] = 'Could not send the reset email. Please try again shortly.';
                }
            }

        } catch (Throwable $e) {
            rlog('Reset request error', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);

            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$csrfToken = csrf();
?>
<!doctype html>
<html lang="en">
<head>

<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Haven — Forgot Password</title>
<link rel="icon" type="image/png" href="https://salman.rfnhsc.com/mind/logo.png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@500;600&display=swap"
    rel="stylesheet"
>

<!-- GSAP -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js" defer></script>
<!-- Anime.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/animejs/3.2.2/anime.min.js" defer></script>
<!-- Lenis (smooth scroll) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lenis/1.1.13/lenis.min.js" defer></script>
<!-- Matter.js (physics) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/matter-js/0.19.0/matter.min.js" defer></script>
<!-- Three.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js" defer></script>

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

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; }

    body {
        margin: 0;
        min-height: 100vh;
        font-family: "DM Sans", sans-serif;
        color: var(--text);
        background:
            radial-gradient(circle at 10% 10%, rgba(217,235,224,.75), transparent 30%),
            radial-gradient(circle at 90% 80%, rgba(236,210,195,.65), transparent 28%),
            var(--cream);
        overflow-x: hidden;
    }

    #three-bg {
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
        opacity: .55;
    }

    #matter-bg {
        position: fixed;
        inset: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 1;
        opacity: .65;
    }

    .page {
        position: relative;
        z-index: 2;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }

    .shell {
        width: min(1080px, 100%);
        display: grid;
        grid-template-columns: .8fr 1fr;
        gap: 30px;
    }

    .welcome {
        position: relative;
        min-height: 560px;
        padding: 50px;
        border-radius: 38px;
        background: linear-gradient(145deg, rgba(255,255,255,.72), rgba(238,244,239,.68));
        border: 1px solid rgba(255,255,255,.85);
        box-shadow: var(--shadow);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .welcome::before {
        content: "";
        position: absolute;
        width: 260px;
        height: 260px;
        border-radius: 50%;
        background: rgba(197,224,207,.35);
        top: -110px;
        right: -80px;
        filter: blur(2px);
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 700;
        letter-spacing: .03em;
        position: relative;
        z-index: 2;
    }

    .brand-mark {
        width: 44px;
        height: 44px;
        display: grid;
        place-items: center;
        border-radius: 15px;
        background: linear-gradient(145deg, #dcece2, #edf3ee);
        box-shadow: 7px 7px 14px rgba(100,120,110,.10), -5px -5px 12px rgba(255,255,255,.95);
        color: var(--sage-dark);
        font-size: 21px;
    }

    .welcome-content { position: relative; z-index: 2; }

    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 13px;
        margin-bottom: 22px;
        border-radius: 999px;
        background: rgba(221,235,225,.7);
        color: var(--sage-dark);
        font-size: 13px;
        font-weight: 600;
    }

    h1 {
        margin: 0;
        max-width: 420px;
        font-family: "Playfair Display", serif;
        font-size: clamp(36px, 4.4vw, 54px);
        line-height: 1.06;
        letter-spacing: -.03em;
        color: #40584c;
    }

    .welcome-description {
        max-width: 420px;
        margin-top: 20px;
        color: var(--muted);
        line-height: 1.8;
        font-size: 16px;
    }

    .welcome-footer {
        color: #89938d;
        font-size: 12px;
        line-height: 1.6;
        position: relative;
        z-index: 2;
    }

    .card {
        padding: 40px;
        border-radius: 38px;
        background: rgba(255,255,255,.72);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255,255,255,.9);
        box-shadow: var(--shadow);
        align-self: center;
    }

    .card-header { margin-bottom: 26px; }

    .card-header h2 {
        margin: 0 0 8px;
        font-family: "Playfair Display", serif;
        font-size: 32px;
        color: #425b4e;
    }

    .card-header p { margin: 0; color: var(--muted); line-height: 1.6; }

    .field { margin-bottom: 18px; }

    label {
        display: block;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #607067;
    }

    input {
        width: 100%;
        height: 52px;
        border: none;
        outline: none;
        padding: 0 16px;
        border-radius: 16px;
        background: rgba(246,247,243,.88);
        color: var(--text);
        font-family: inherit;
        font-size: 14px;
        box-shadow:
            inset 3px 3px 8px rgba(100,100,90,.055),
            inset -3px -3px 8px rgba(255,255,255,.85);
        transition: .25s ease;
    }

    input:focus {
        background: #fff;
        box-shadow:
            0 0 0 3px rgba(111,146,127,.13),
            inset 2px 2px 6px rgba(100,100,90,.04);
    }

    input.invalid {
        box-shadow: 0 0 0 3px rgba(205,110,110,.13);
    }

    .btn {
        border: none;
        width: 100%;
        min-height: 52px;
        padding: 0 22px;
        border-radius: 17px;
        cursor: pointer;
        font-weight: 700;
        font-size: 15px;
        color: #fff;
        background: linear-gradient(135deg, #759a86, #5f806f);
        box-shadow: 8px 8px 18px rgba(85,110,95,.15);
        transition: transform .2s, box-shadow .2s, opacity .2s;
    }

    .btn:hover { transform: translateY(-2px); }
    .btn:active { transform: translateY(0); }
    .btn:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .alert {
        display: none;
        padding: 13px 15px;
        margin-bottom: 18px;
        border-radius: 15px;
        font-size: 13px;
        line-height: 1.5;
    }

    .alert.show { display: block; }
    .alert.error { background: #fff0ef; color: #a75f5b; }
    .alert.success { background: #edf7ef; color: #5b866b; }

    .hint {
        margin-top: 20px;
        text-align: center;
        font-size: 13px;
        color: var(--muted);
    }

    .hint a {
        color: var(--sage-dark);
        text-decoration: none;
        font-weight: 600;
    }

    .hint a:hover { text-decoration: underline; }

    @media (max-width: 840px) {
        .shell { grid-template-columns: 1fr; }
        .welcome { min-height: auto; padding: 36px; }
    }

</style>
</head>
<body>

<canvas id="three-bg"></canvas>
<canvas id="matter-bg"></canvas>

<div class="page">
    <div class="shell">

        <div class="welcome">
            <div class="brand">
                <div class="brand-mark">🌿</div>
                Haven
            </div>

            <div class="welcome-content">
                <div class="eyebrow">A gentler place to be</div>
                <h1>Let's get you back in</h1>
                <p class="welcome-description">
                    It happens to everyone. Enter the email on your Haven
                    account and we'll send you a link to choose a new
                    password.
                </p>
            </div>

            <div class="welcome-footer">
                Haven — a gentler place to be.
            </div>
        </div>

        <div class="card" id="resetCard">
            <div class="card-header">
                <h2>Forgot password</h2>
                <p>Enter your email and we'll send you a reset link.</p>
            </div>

            <div class="alert <?= $errors ? 'error show' : '' ?>" id="alertError">
                <?php foreach ($errors as $e): ?>
                    <div><?= h($e) ?></div>
                <?php endforeach; ?>
            </div>

            <div class="alert <?= $success ? 'success show' : '' ?>" id="alertSuccess">
                <?= h($success) ?>
            </div>

            <form method="post" id="resetForm" autocomplete="off">
                <input type="hidden" name="action" value="request_reset">
                <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

                <div class="field">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= h($email) ?>"
                        required
                        autofocus
                    >
                </div>

                <button type="submit" class="btn" id="sendBtn">
                    Send reset link
                </button>
            </form>

            <div class="hint">
                <a href="login.php">← Back to login</a>
            </div>
        </div>

    </div>
</div>

<script>

/*
|--------------------------------------------------------------------------
| Lenis — smooth scrolling
|--------------------------------------------------------------------------
*/

window.addEventListener('load', () => {

    if (window.Lenis) {

        const lenis = new Lenis({
            duration: 1.1,
            smoothWheel: true
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }

        requestAnimationFrame(raf);
    }

});

/*
|--------------------------------------------------------------------------
| GSAP — page entrance
|--------------------------------------------------------------------------
*/

window.addEventListener('load', () => {

    if (window.gsap) {

        gsap.from('.welcome', {
            opacity: 0,
            x: -35,
            duration: 1,
            ease: 'power3.out'
        });

        gsap.from('#resetCard', {
            opacity: 0,
            x: 35,
            duration: 1,
            delay: .15,
            ease: 'power3.out'
        });

        gsap.from('.brand', {
            opacity: 0,
            y: -15,
            duration: .7,
            delay: .35
        });
    }

});

/*
|--------------------------------------------------------------------------
| Anime.js — form feedback (validation shake / alert pop)
|--------------------------------------------------------------------------
*/

function shakeField(el) {

    if (window.anime) {

        anime({
            targets: el,
            translateX: [
                { value: -8, duration: 60 },
                { value: 8, duration: 60 },
                { value: -6, duration: 60 },
                { value: 6, duration: 60 },
                { value: 0, duration: 60 }
            ],
            easing: 'easeInOutSine'
        });
    }

    el.classList.add('invalid');

    setTimeout(() => el.classList.remove('invalid'), 900);
}

function popAlert(el) {

    if (window.anime) {

        anime({
            targets: el,
            opacity: [0, 1],
            translateY: [-8, 0],
            duration: 420,
            easing: 'easeOutQuad'
        });
    }
}

document.querySelectorAll('.alert.show').forEach(popAlert);

const resetForm = document.getElementById('resetForm');
const emailInput = document.getElementById('email');
const sendBtn = document.getElementById('sendBtn');

resetForm.addEventListener('submit', event => {

    const value = emailInput.value.trim();
    const valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

    if (!valid) {
        event.preventDefault();
        shakeField(emailInput);
        return;
    }

    sendBtn.disabled = true;
    sendBtn.textContent = 'Sending...';

    if (window.gsap) {
        gsap.to(sendBtn, { scale: .97, duration: .15, yoyo: true, repeat: 1 });
    }
});

/*
|--------------------------------------------------------------------------
| Three.js — ambient particle background (same feel as register.php)
|--------------------------------------------------------------------------
*/

function initThree() {

    if (!window.THREE) return;

    const canvas = document.getElementById('three-bg');

    const scene = new THREE.Scene();

    const camera = new THREE.PerspectiveCamera(
        55,
        window.innerWidth / window.innerHeight,
        .1,
        100
    );

    camera.position.z = 8;

    const renderer = new THREE.WebGLRenderer({
        canvas,
        alpha: true,
        antialias: true
    });

    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
    renderer.setSize(window.innerWidth, window.innerHeight);

    const geometry = new THREE.BufferGeometry();
    const count = 220;
    const positions = new Float32Array(count * 3);

    for (let i = 0; i < count * 3; i += 3) {
        positions[i]     = (Math.random() - .5) * 14;
        positions[i + 1] = (Math.random() - .5) * 9;
        positions[i + 2] = (Math.random() - .5) * 8;
    }

    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

    const material = new THREE.PointsMaterial({
        color: 0x789783,
        size: .035,
        transparent: true,
        opacity: .35
    });

    const particles = new THREE.Points(geometry, material);
    scene.add(particles);

    const orbGeometry = new THREE.SphereGeometry(1.2, 32, 32);
    const orbMaterial = new THREE.MeshBasicMaterial({
        color: 0xe8c7b5,
        transparent: true,
        opacity: .08
    });

    const orb = new THREE.Mesh(orbGeometry, orbMaterial);
    orb.position.set(-4, 2, -2);
    scene.add(orb);

    function animate() {
        requestAnimationFrame(animate);

        particles.rotation.y += 0.00035;
        particles.rotation.x += 0.0001;

        orb.position.y = 2 + Math.sin(Date.now() * .0004) * .35;

        renderer.render(scene, camera);
    }

    animate();

    window.addEventListener('resize', () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
    });
}

window.addEventListener('load', initThree);

/*
|--------------------------------------------------------------------------
| Matter.js — a handful of soft sage/peach bubbles drifting upward
|--------------------------------------------------------------------------
*/

function initMatter() {

    if (!window.Matter) return;

    const {
        Engine, Runner, World, Bodies, Body, Composite
    } = Matter;

    const canvas = document.getElementById('matter-bg');
    const ctx = canvas.getContext('2d');

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    resize();
    window.addEventListener('resize', resize);

    const engine = Engine.create();
    engine.gravity.y = -0.05; // gentle upward drift, not a hard fall

    const bubbles = [];
    const colors = ['#dce9e0', '#e8c7b5', '#c5e0cf'];

    for (let i = 0; i < 14; i++) {

        const radius = 10 + Math.random() * 22;

        const body = Bodies.circle(
            Math.random() * window.innerWidth,
            window.innerHeight + Math.random() * 400,
            radius,
            {
                frictionAir: 0.045,
                restitution: 0.4,
                render: { visible: false }
            }
        );

        Body.setVelocity(body, {
            x: (Math.random() - .5) * .3,
            y: -(0.3 + Math.random() * .4)
        });

        body.customColor = colors[i % colors.length];
        body.customAlpha = 0.12 + Math.random() * 0.18;

        bubbles.push(body);
        Composite.add(engine.world, body);
    }

    const runner = Runner.create();
    Runner.run(runner, engine);

    (function render() {
        requestAnimationFrame(render);

        ctx.clearRect(0, 0, canvas.width, canvas.height);

        bubbles.forEach(b => {

            // recycle bubbles that drift off the top
            if (b.position.y < -60) {
                Body.setPosition(b, {
                    x: Math.random() * canvas.width,
                    y: canvas.height + 60
                });
            }

            ctx.beginPath();
            ctx.arc(b.position.x, b.position.y, b.circleRadius, 0, Math.PI * 2);
            ctx.fillStyle = b.customColor;
            ctx.globalAlpha = b.customAlpha;
            ctx.fill();
            ctx.globalAlpha = 1;
        });

    })();
}

window.addEventListener('load', initMatter);

</script>

</body>
</html>