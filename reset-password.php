<?php
/**
 * HAVEN
 * Reset Password — Set a new password using a valid token
 *
 * Matches register.php / forgot-password.php visual language
 * and DB-connection resilience.
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
    if (empty($_SESSION['reset_password_csrf'])) {
        $_SESSION['reset_password_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['reset_password_csrf'];
}

function csrf_ok(?string $v): bool
{
    return !empty($v)
        && !empty($_SESSION['reset_password_csrf'])
        && hash_equals($_SESSION['reset_password_csrf'], $v);
}

function password_is_strong(string $password): bool
{
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
|
| Same resilient connection logic as register.php / forgot-password.php.
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
        throw new RuntimeException('Could not obtain a PDO connection.');
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

$errors     = [];
$success    = '';
$token      = trim($_GET['token'] ?? '');
$validToken = false;
$userId     = null;
$username   = '';
$userEmail  = '';

/*
|--------------------------------------------------------------------------
| Validate token on page load
|--------------------------------------------------------------------------
*/

if ($token !== '') {

    try {
        $stmt = $db->prepare(
            'SELECT user_id, expires_at
             FROM password_resets
             WHERE token = ?
             LIMIT 1'
        );
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if ($reset) {

            $expires = strtotime($reset['expires_at']);

            if (time() > $expires) {

                $errors[] = 'This reset link has expired. Please request a new one.';

                $del = $db->prepare(
                    'DELETE FROM password_resets WHERE token = ?'
                );
                $del->execute([$token]);

            } else {

                $validToken = true;
                $userId     = (int)$reset['user_id'];

                $uStmt = $db->prepare(
                    'SELECT username, email FROM users WHERE id = ?'
                );
                $uStmt->execute([$userId]);
                $user = $uStmt->fetch();

                $username  = $user['username'] ?? 'there';
                $userEmail = $user['email'] ?? '';
            }

        } else {
            $errors[] = 'Invalid or missing reset token.';
        }

    } catch (Throwable $e) {
        rlog('Token validation error', ['error' => $e->getMessage()]);
        $errors[] = 'Something went wrong. Please try again.';
    }

} else {
    $errors[] = 'No reset token provided.';
}

/*
|--------------------------------------------------------------------------
| Handle password update
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'reset_password'
) {

    if (!$validToken) {
        $errors[] = 'Invalid or expired token. Please request a new reset link.';
    } elseif (!csrf_ok($_POST['csrf'] ?? '')) {
        $errors[] = 'Security check failed. Please refresh and try again.';
    } else {

        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        /*
         * Match register.php's actual password policy: 8+ characters,
         * upper, lower, number, symbol. (The previous version required
         * *exactly* 8 characters and no symbol, which was weaker than
         * — and inconsistent with — what registration enforces.)
         */
        if (!password_is_strong($password)) {
            $errors[] = 'Password must be at least 8 characters and include ' .
                'an uppercase letter, a lowercase letter, a number, and a symbol.';
        }

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {

            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                if (!$hash) {
                    throw new RuntimeException('Password hashing failed.');
                }

                $upd = $db->prepare(
                    'UPDATE users SET password_hash = ? WHERE id = ?'
                );
                $upd->execute([$hash, $userId]);

                $del = $db->prepare(
                    'DELETE FROM password_resets WHERE token = ?'
                );
                $del->execute([$token]);

                /*
                 * Best-effort notification. A failure here should never
                 * block the reset itself, since the password is already
                 * changed at this point.
                 */
                if ($userEmail !== '') {
                    try {
                        sendMail(
                            $userEmail,
                            'Haven — Your password was changed',
                            '<p>Hi ' . h($username) . ',</p>' .
                            '<p>This is a confirmation that your Haven ' .
                            'password was just changed. If this wasn\'t you, ' .
                            'please contact support immediately.</p>',
                            true
                        );
                    } catch (Throwable $e) {
                        rlog('Change notification email failed', [
                            'error' => $e->getMessage(),
                            'user_id' => $userId
                        ]);
                    }
                }

                $success    = 'Your password has been reset. You can now log in.';
                $validToken = false;

            } catch (Throwable $e) {
                rlog('Password reset update error', [
                    'error' => $e->getMessage(),
                    'user_id' => $userId
                ]);

                $errors[] = 'Could not update your password. Please try again.';
            }
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
<title>Haven — Reset Password</title>
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
        --ok: #5b866b;
        --bad: #a75f5b;
        --white: rgba(255,255,255,.76);
        --shadow:
            18px 18px 40px rgba(92,83,67,.09),
            -12px -12px 30px rgba(255,255,255,.9);
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
        display: flex;
        align-items: center;
        justify-content: center;
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

    .card {
        position: relative;
        z-index: 2;
        width: min(480px, calc(100% - 32px));
        margin: 40px auto;
        padding: 40px;
        border-radius: 38px;
        background: rgba(255,255,255,.72);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        border: 1px solid rgba(255,255,255,.9);
        box-shadow: var(--shadow);
        overflow: hidden;
    }

    .card::before {
        content: "";
        position: absolute;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(197,224,207,.35);
        top: -100px;
        right: -70px;
        filter: blur(2px);
        z-index: -1;
    }

    .brand {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 700;
        letter-spacing: .03em;
        margin-bottom: 26px;
    }

    .brand-mark {
        width: 40px;
        height: 40px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        background: linear-gradient(145deg, #dcece2, #edf3ee);
        box-shadow: 7px 7px 14px rgba(100,120,110,.10), -5px -5px 12px rgba(255,255,255,.95);
        color: var(--sage-dark);
        font-size: 19px;
    }

    h1 {
        margin: 0 0 6px;
        font-family: "Playfair Display", serif;
        font-size: 30px;
        color: #425b4e;
    }

    .sub {
        margin: 0 0 22px;
        color: var(--muted);
        font-size: 15px;
        line-height: 1.6;
    }

    .field { margin-bottom: 17px; }

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

    input.invalid { box-shadow: 0 0 0 3px rgba(205,110,110,.13); }

    .strength {
        height: 5px;
        border-radius: 10px;
        margin-top: 9px;
        background: #e5e8e4;
        overflow: hidden;
    }

    .strength i {
        display: block;
        height: 100%;
        width: 0;
        background: linear-gradient(90deg, #d98b8b, #e0b97e, var(--sage));
        transition: width .25s ease;
    }

    .rules {
        display: grid;
        grid-template-columns: 1fr 1fr;
        margin-top: 8px;
        gap: 5px;
    }

    .rule {
        font-size: 11px;
        color: var(--muted);
    }

    .rule.pass { color: var(--sage-dark); font-weight: 600; }
    .rule.pass::before { content: '✓ '; }

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
        padding: 13px 15px;
        margin-bottom: 18px;
        border-radius: 15px;
        font-size: 13px;
        line-height: 1.5;
    }

    .alert.error { background: #fff0ef; color: var(--bad); }
    .alert.success { background: #edf7ef; color: var(--ok); }

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

    @media (max-width: 560px) {
        .card { padding: 30px; }
        h1 { font-size: 26px; }
    }

</style>
</head>
<body>

<canvas id="three-bg"></canvas>
<canvas id="matter-bg"></canvas>

<div class="card" id="resetCard">

    <div class="brand">
        <div class="brand-mark">🌿</div>
        Haven
    </div>

    <h1>Reset password</h1>

    <p class="sub">
        <?php if ($validToken && !$success): ?>
            Set a new password for <strong><?= h($username) ?></strong>.
        <?php else: ?>
            Choose and confirm a new password below.
        <?php endif; ?>
    </p>

    <?php if ($errors): ?>
        <div class="alert error show" id="alertError">
            <?php foreach ($errors as $e): ?>
                <div><?= h($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success show" id="alertSuccess"><?= h($success) ?></div>
        <div class="hint">
            <a href="login.php">Go to login →</a>
        </div>
    <?php endif; ?>

    <?php if ($validToken && empty($success)): ?>

        <form method="post" id="resetForm" autocomplete="off">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="csrf" value="<?= h($csrfToken) ?>">

            <div class="field">
                <label for="password">New password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Create a strong password"
                    required
                >
                <div class="strength"><i id="strengthBar"></i></div>
                <div class="rules">
                    <span class="rule" data-r="len">8+ characters</span>
                    <span class="rule" data-r="up">Uppercase</span>
                    <span class="rule" data-r="lo">Lowercase</span>
                    <span class="rule" data-r="num">Number</span>
                    <span class="rule" data-r="sym">Symbol</span>
                </div>
            </div>

            <div class="field">
                <label for="confirm">Confirm password</label>
                <input
                    type="password"
                    id="confirm"
                    name="confirm_password"
                    placeholder="Repeat password"
                    required
                >
            </div>

            <button type="submit" class="btn" id="resetBtn">
                Reset password
            </button>
        </form>

    <?php elseif (!$validToken && empty($success)): ?>

        <div class="hint">
            <a href="forgot-password.php">Request a new reset link →</a>
        </div>

    <?php endif; ?>

</div>

<script>

/*
|--------------------------------------------------------------------------
| Lenis
|--------------------------------------------------------------------------
*/

window.addEventListener('load', () => {

    if (window.Lenis) {

        const lenis = new Lenis({ duration: 1.1, smoothWheel: true });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }

        requestAnimationFrame(raf);
    }

});

/*
|--------------------------------------------------------------------------
| GSAP — entrance
|--------------------------------------------------------------------------
*/

window.addEventListener('load', () => {

    if (window.gsap) {

        gsap.from('#resetCard', {
            opacity: 0,
            y: 25,
            duration: .9,
            ease: 'power3.out'
        });

        gsap.from('.brand', {
            opacity: 0,
            y: -12,
            duration: .6,
            delay: .2
        });
    }

});

/*
|--------------------------------------------------------------------------
| Anime.js — alert pop / shake on mismatch
|--------------------------------------------------------------------------
*/

function popAlert(el) {

    if (!el || !window.anime) return;

    anime({
        targets: el,
        opacity: [0, 1],
        translateY: [-8, 0],
        duration: 420,
        easing: 'easeOutQuad'
    });
}

popAlert(document.getElementById('alertError'));
popAlert(document.getElementById('alertSuccess'));

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

/*
|--------------------------------------------------------------------------
| Password strength (client-side mirror of password_is_strong())
|--------------------------------------------------------------------------
*/

const pw   = document.getElementById('password');
const cp   = document.getElementById('confirm');
const bar  = document.getElementById('strengthBar');
const form = document.getElementById('resetForm');

function checkPassword() {

    if (!pw) return;

    const p = pw.value;

    const checks = {
        len: p.length >= 8,
        up:  /[A-Z]/.test(p),
        lo:  /[a-z]/.test(p),
        num: /[0-9]/.test(p),
        sym: /[^A-Za-z0-9]/.test(p)
    };

    Object.keys(checks).forEach(k => {
        const el = document.querySelector('[data-r="' + k + '"]');
        if (el) el.classList.toggle('pass', checks[k]);
    });

    const count = Object.values(checks).filter(Boolean).length;

    if (bar) bar.style.width = (count * 20) + '%';

    return Object.values(checks).every(Boolean);
}

if (pw) {
    pw.addEventListener('input', checkPassword);
    cp.addEventListener('input', checkPassword);
}

if (form) {

    form.addEventListener('submit', event => {

        const strong = checkPassword();
        const match  = pw.value === cp.value && pw.value !== '';

        if (!strong) {
            event.preventDefault();
            shakeField(pw);
            return;
        }

        if (!match) {
            event.preventDefault();
            shakeField(cp);
            return;
        }

        const btn = document.getElementById('resetBtn');
        btn.disabled = true;
        btn.textContent = 'Resetting...';

        if (window.gsap) {
            gsap.to(btn, { scale: .97, duration: .15, yoyo: true, repeat: 1 });
        }
    });
}

/*
|--------------------------------------------------------------------------
| Three.js — ambient particle background
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
| Matter.js — drifting bubbles
|--------------------------------------------------------------------------
*/

function initMatter() {

    if (!window.Matter) return;

    const { Engine, Runner, Bodies, Body, Composite } = Matter;

    const canvas = document.getElementById('matter-bg');
    const ctx = canvas.getContext('2d');

    function resize() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }

    resize();
    window.addEventListener('resize', resize);

    const engine = Engine.create();
    engine.gravity.y = -0.05;

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