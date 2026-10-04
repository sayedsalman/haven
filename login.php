
<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/auth.php';



header('Content-Type: text/html; charset=UTF-8');



$error = '';

$oldEmail = '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $oldEmail = trim($_POST['email'] ?? '');
    $email = strtolower($oldEmail);
    $password = $_POST['password'] ?? '';



    if ($email === '' || $password === '') {

        $error = 'Please enter your email and password.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        try {



            $stmt = $pdo->prepare("
                SELECT *
                FROM users
                WHERE LOWER(email) = LOWER(?)
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);



            if (
                $user &&
                !empty($user['password_hash']) &&
                password_verify(
                    $password,
                    $user['password_hash']
                )
            ) {


                if (
                    isset($user['is_active']) &&
                    (int)$user['is_active'] !== 1
                ) {

                    $error =
                        'Your account is currently inactive. Please contact support.';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Secure session regeneration
                    |--------------------------------------------------------------------------
                    */

                    session_regenerate_id(true);

                    /*
                    |--------------------------------------------------------------------------
                    | Core session data
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['user_id'] =
                        (int)$user['id'];

                    $_SESSION['role'] =
                        strtolower(
                            trim(
                                (string)(
                                    $user['role'] ?? 'user'
                                )
                            )
                        );

                    $_SESSION['username'] =
                        (string)(
                            $user['username'] ?? ''
                        );

                    $_SESSION['email'] =
                        (string)(
                            $user['email'] ?? ''
                        );

                    $_SESSION['anonymous_name'] =
                        (string)(
                            $user['anonymous_name'] ?? ''
                        );

                    $_SESSION['avatar_color'] =
                        (string)(
                            $user['avatar_color'] ??
                            '#A8C7B5'
                        );

                    $_SESSION['avatar_icon'] =
                        (string)(
                            $user['avatar_icon'] ??
                            'circle'
                        );

                    /*
                    |--------------------------------------------------------------------------
                    | Update last login
                    |--------------------------------------------------------------------------
                    */

                    $update = $pdo->prepare("
                        UPDATE users
                        SET last_login = NOW()
                        WHERE id = ?
                    ");

                    $update->execute([
                        (int)$user['id']
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Role routing
                    |--------------------------------------------------------------------------
                    */

                    $roleRoutes = [

                        'user' =>
                            'dashboard.php',

                        'admin' =>
                            'admin.php',

                        'moderator' =>
                            'moderator.php',

                        'volunteer' =>
                            'volunteer.php'
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Unknown role fallback
                    |--------------------------------------------------------------------------
                    */

                    if (
                        !isset(
                            $roleRoutes[
                                $_SESSION['role']
                            ]
                        )
                    ) {

                        $_SESSION['role'] =
                            'user';
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Redirect
                    |--------------------------------------------------------------------------
                    */

                    header(
                        'Location: ' .
                        $roleRoutes[
                            $_SESSION['role']
                        ]
                    );

                    exit;
                }

            } else {

                /*
                |--------------------------------------------------------------------------
                | Generic authentication error
                |--------------------------------------------------------------------------
                */

                $error =
                    'The email or password you entered is incorrect.';
            }

        } catch (Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Never expose database errors to users
            |--------------------------------------------------------------------------
            */

            error_log(
                '[Haven Login] ' .
                $e->getMessage()
            );

            $error =
                'Something went wrong while signing you in. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    http-equiv="Content-Type"
    content="text/html; charset=UTF-8"
>

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<meta
    name="theme-color"
    content="#F4F1EA"
>

<meta
    name="description"
    content="Haven — a calm and supportive digital space."
>

<title>Haven — Welcome Back</title>
<link rel="icon" href="logo.png" type="image/png">


<!-- =============================================================
     GOOGLE FONT
============================================================== -->

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


<!-- =============================================================
     GSAP
============================================================== -->

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"
    defer
></script>


<!-- =============================================================
     THREE.JS
============================================================== -->

<script
    src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"
    defer
></script>


<style>

/* ===============================================================
   MINDSPHERE
   Calm / Peaceful / Neomorphic
================================================================ */

:root {

    /*
    ---------------------------------------------------------------
    Main colors
    ---------------------------------------------------------------
    */

    --cream:
        #F5F2EB;

    --cream-deep:
        #ECE7DD;

    --paper:
        #F8F6F0;

    --sage:
        #A8C7B5;

    --sage-dark:
        #789E88;

    --sage-light:
        #D8E7DE;

    --lavender:
        #C7C2DD;

    --lavender-light:
        #E6E2EF;

    --peach:
        #EBC6AF;

    --text:
        #34413B;

    --text-soft:
        #6E7A73;

    --text-light:
        #98A19C;

    --white:
        #FFFFFF;

    --danger:
        #C77C7C;

    --danger-bg:
        #F5E2E0;

    /*
    ---------------------------------------------------------------
    Neomorphism shadows
    ---------------------------------------------------------------
    */

    --shadow-light:
        -12px -12px 28px rgba(255,255,255,.92);

    --shadow-dark:
        12px 12px 28px rgba(182,177,165,.34);

    --shadow-soft:
        7px 7px 18px rgba(185,180,170,.26),
        -7px -7px 18px rgba(255,255,255,.88);

    --inset:
        inset 4px 4px 10px rgba(184,180,170,.20),
        inset -4px -4px 10px rgba(255,255,255,.88);

    --radius:
        30px;

    --ease:
        cubic-bezier(.22,.8,.25,1);
}


/* ===============================================================
   RESET
================================================================ */

* {
    box-sizing:
        border-box;
}

html {
    min-height:
        100%;
}

body {

    margin:
        0;

    min-height:
        100vh;

    font-family:
        "DM Sans",
        sans-serif;

    color:
        var(--text);

    background:
        var(--cream);

    overflow-x:
        hidden;

    -webkit-font-smoothing:
        antialiased;

    text-rendering:
        optimizeLegibility;
}

button,
input {
    font:
        inherit;
}


/* ===============================================================
   THREE BACKGROUND
================================================================ */

#threeScene {

    position:
        fixed;

    inset:
        0;

    z-index:
        0;

    pointer-events:
        none;

    opacity:
        .42;
}


/* ===============================================================
   BACKGROUND BLOBS
================================================================ */

.background-shape {

    position:
        fixed;

    border-radius:
        50%;

    pointer-events:
        none;

    z-index:
        1;

    filter:
        blur(55px);

    opacity:
        .45;
}

.shape-sage {

    width:
        360px;

    height:
        360px;

    left:
        -160px;

    top:
        -130px;

    background:
        rgba(168,199,181,.38);
}

.shape-lavender {

    width:
        330px;

    height:
        330px;

    right:
        -150px;

    bottom:
        -130px;

    background:
        rgba(199,194,221,.38);
}

.shape-peach {

    width:
        180px;

    height:
        180px;

    right:
        25%;

    top:
        -90px;

    background:
        rgba(235,198,175,.28);
}


/* ===============================================================
   PAGE
================================================================ */

.page {

    position:
        relative;

    z-index:
        5;

    min-height:
        100vh;

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    padding:
        35px 20px;
}


/* ===============================================================
   CONTAINER
================================================================ */

.container {

    width:
        min(1080px, 100%);

    display:
        grid;

    grid-template-columns:
        1.05fr .95fr;

    gap:
        28px;

    align-items:
        center;
}


/* ===============================================================
   LEFT PEACE PANEL
================================================================ */

.peace-panel {

    position:
        relative;

    min-height:
        650px;

    padding:
        55px;

    border-radius:
        var(--radius);

    background:
        rgba(248,246,240,.70);

    border:
        1px solid rgba(255,255,255,.80);

    box-shadow:
        var(--shadow-light),
        var(--shadow-dark);

    backdrop-filter:
        blur(22px);

    -webkit-backdrop-filter:
        blur(22px);

    overflow:
        hidden;

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        space-between;
}


/* ===============================================================
   BRAND
================================================================ */

.brand {

    display:
        flex;

    align-items:
        center;

    gap:
        13px;

    position:
        relative;

    z-index:
        3;
}

.brand-symbol {

    width:
        50px;

    height:
        50px;

    border-radius:
        17px;

    background:
        var(--paper);

    box-shadow:
        var(--shadow-soft);

    display:
        grid;

    place-items:
        center;

    overflow: hidden;

    color:
        var(--sage-dark);
}

.brand-logo-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.brand-symbol svg {

    width:
        25px;

    height:
        25px;
}

.brand-name {

    font-size:
        20px;

    font-weight:
        700;

    letter-spacing:
        -.3px;
}


/* ===============================================================
   WELCOME TEXT
================================================================ */

.welcome-content {

    position:
        relative;

    z-index:
        3;

    max-width:
        500px;
}

.small-label {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        8px;

    color:
        var(--sage-dark);

    font-size:
        11px;

    font-weight:
        700;

    letter-spacing:
        1.5px;

    text-transform:
        uppercase;
}

.small-label::before {

    content:
        "";

    width:
        22px;

    height:
        1px;

    background:
        var(--sage-dark);
}

.welcome-title {

    margin:
        18px 0 18px;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        clamp(45px, 5vw, 68px);

    line-height:
        1.02;

    font-weight:
        500;

    letter-spacing:
        -2.5px;

    color:
        var(--text);
}

.welcome-title span {

    color:
        var(--sage-dark);
}

.welcome-description {

    margin:
        0;

    max-width:
        430px;

    color:
        var(--text-soft);

    font-size:
        15px;

    line-height:
        1.85;
}


/* ===============================================================
   PEACE QUOTE
================================================================ */

.quote {

    margin-top:
        32px;

    padding:
        17px 19px;

    border-radius:
        18px;

    background:
        rgba(255,255,255,.38);

    box-shadow:
        var(--inset);

    color:
        #748078;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        15px;

    line-height:
        1.6;
}


/* ===============================================================
   BOTANICAL SVG
================================================================ */

.botanical {

    position:
        absolute;

    right:
        35px;

    bottom:
        20px;

    width:
        280px;

    height:
        280px;

    opacity:
        .86;

    z-index:
        2;
}

.botanical svg {

    width:
        100%;

    height:
        100%;

    overflow:
        visible;
}

.flower-petal {

    transform-origin:
        140px 145px;
}

.flower-center {

    transform-origin:
        140px 145px;
}


/* ===============================================================
   LITTLE FLOATING DOTS
================================================================ */

.dot {

    position:
        absolute;

    width:
        8px;

    height:
        8px;

    border-radius:
        50%;

    z-index:
        2;
}

.dot-one {

    right:
        100px;

    top:
        100px;

    background:
        var(--sage);

    opacity:
        .45;
}

.dot-two {

    right:
        220px;

    top:
        170px;

    width:
        5px;

    height:
        5px;

    background:
        var(--lavender);

    opacity:
        .65;
}

.dot-three {

    left:
        80px;

    bottom:
        90px;

    width:
        6px;

    height:
        6px;

    background:
        var(--peach);

    opacity:
        .65;
}


/* ===============================================================
   LOGIN CARD
================================================================ */

.login-card {

    min-height:
        650px;

    padding:
        48px;

    border-radius:
        var(--radius);

    background:
        rgba(248,246,240,.82);

    border:
        1px solid rgba(255,255,255,.88);

    box-shadow:
        var(--shadow-light),
        var(--shadow-dark);

    backdrop-filter:
        blur(28px);

    -webkit-backdrop-filter:
        blur(28px);

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;
}


/* ===============================================================
   LOGIN HEADING
================================================================ */

.login-heading {

    margin-bottom:
        28px;
}

.login-heading h2 {

    margin:
        0 0 8px;

    font-family:
        "Playfair Display",
        serif;

    font-size:
        38px;

    font-weight:
        500;

    letter-spacing:
        -1.3px;
}

.login-heading p {

    margin:
        0;

    color:
        var(--text-soft);

    font-size:
        14px;
}


/* ===============================================================
   ERROR
================================================================ */

.error {

    display:
        flex;

    gap:
        11px;

    align-items:
        flex-start;

    padding:
        13px 15px;

    margin-bottom:
        20px;

    border-radius:
        16px;

    background:
        var(--danger-bg);

    color:
        #9F5E5E;

    font-size:
        13px;

    line-height:
        1.5;

    box-shadow:
        var(--inset);
}

.error svg {

    width:
        18px;

    height:
        18px;

    flex:
        0 0 auto;

    margin-top:
        1px;
}


/* ===============================================================
   FIELD
================================================================ */

.field {

    margin-bottom:
        19px;
}

.field label {

    display:
        block;

    margin:
        0 0 8px;

    color:
        #69756E;

    font-size:
        12px;

    font-weight:
        700;

    letter-spacing:
        .5px;
}

.input-wrap {

    position:
        relative;
}

.input-icon {

    position:
        absolute;

    left:
        16px;

    top:
        50%;

    transform:
        translateY(-50%);

    width:
        18px;

    height:
        18px;

    color:
        #92A298;

    pointer-events:
        none;

    transition:
        .25s ease;
}

.input-icon svg {

    width:
        100%;

    height:
        100%;
}

.input {

    width:
        100%;

    height:
        56px;

    border:
        1px solid rgba(255,255,255,.9);

    outline:
        none;

    border-radius:
        17px;

    background:
        var(--paper);

    color:
        var(--text);

    padding:
        0 48px;

    font-size:
        14px;

    box-shadow:
        var(--inset);

    transition:
        .28s var(--ease);
}

.input::placeholder {

    color:
        #A7AEA9;
}

.input:hover {

    border-color:
        rgba(168,199,181,.35);
}

.input:focus {

    border-color:
        rgba(120,158,136,.48);

    box-shadow:
        var(--inset),
        0 0 0 4px rgba(168,199,181,.13);
}

.input:focus + .focus-line {
    transform:
        scaleX(1);
}


/* ===============================================================
   PASSWORD BUTTON
================================================================ */

.password-button {

    position:
        absolute;

    right:
        13px;

    top:
        50%;

    transform:
        translateY(-50%);

    width:
        34px;

    height:
        34px;

    border:
        0;

    border-radius:
        11px;

    display:
        grid;

    place-items:
        center;

    color:
        #87948C;

    background:
        transparent;

    cursor:
        pointer;

    transition:
        .2s ease;
}

.password-button:hover {

    background:
        rgba(168,199,181,.13);

    color:
        var(--sage-dark);
}

.password-button svg {

    width:
        18px;

    height:
        18px;
}


/* ===============================================================
   FOCUS LINE
================================================================ */

.focus-line {

    position:
        absolute;

    left:
        18px;

    right:
        18px;

    bottom:
        0;

    height:
        2px;

    background:
        linear-gradient(
            90deg,
            var(--sage),
            var(--lavender)
        );

    border-radius:
        10px;

    transform:
        scaleX(0);

    transition:
        .3s var(--ease);

    pointer-events:
        none;
}


/* ===============================================================
   FORM OPTIONS
================================================================ */

.form-options {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        10px;

    margin:
        3px 0 22px;

    font-size:
        12px;
}

.remember {

    display:
        flex;

    align-items:
        center;

    gap:
        7px;

    color:
        var(--text-soft);

    cursor:
        pointer;
}

.remember input {

    width:
        15px;

    height:
        15px;

    accent-color:
        var(--sage-dark);
}

.forgot {

    color:
        var(--sage-dark);

    text-decoration:
        none;

    font-weight:
        700;
}

.forgot:hover {
    text-decoration:
        underline;
}

/* ===============================================================
   LOGIN BUTTON
================================================================ */

.login-button {

    position:
        relative;

    width:
        100%;

    height:
        57px;

    border:
        0;

    border-radius:
        17px;

    color:
        #063B5C; /* Dark blue text */

    background:
        linear-gradient(
            135deg,
            #B9E8F5,
            #87CEEB
        ); /* Sky blue */

    box-shadow:
        7px 7px 16px rgba(80, 150, 180, .25),
        -7px -7px 16px rgba(255,255,255,.85);

    cursor:
        pointer;

    overflow:
        hidden;

    font-size:
        14px;

    font-weight:
        700;

    letter-spacing:
        .2px;

    transition:
        .25s var(--ease);
}

.login-button:hover {

    transform:
        translateY(-2px);

    background:
        linear-gradient(
            135deg,
            #C7EDF7,
            #8DD3EE
        );

    box-shadow:
        9px 10px 20px rgba(80, 150, 180, .30),
        -8px -8px 18px rgba(255,255,255,.90);
}

.login-button:active {

    transform:
        translateY(1px);

    box-shadow:
        var(--inset);
}

.login-button::before {

    content:
        "";

    position:
        absolute;

    top:
        0;

    left:
        -100%;

    width:
        60%;

    height:
        100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.35),
            transparent
        );

    transform:
        skewX(-20deg);
}

.login-button:hover::before {

    animation:
        shine 1s ease;
}

@keyframes shine {

    from {
        left:
            -100%;
    }

    to {
        left:
            150%;
    }
}

.login-button.loading {

    pointer-events:
        none;

    opacity:
        .8;
}

.loader {

    display:
        inline-block;

    width:
        17px;

    height:
        17px;

    border:
        2px solid rgba(6,59,92,.25);

    border-top-color:
        #063B5C;

    border-radius:
        50%;

    animation:
        spin .7s linear infinite;

    vertical-align:
        middle;

    margin-right:
        7px;
}

@keyframes spin {

    to {
        transform:
            rotate(360deg);
    }
}


/* ===============================================================
   REGISTER
================================================================ */

.register {

    margin-top:
        23px;

    text-align:
        center;

    color:
        var(--text-soft);

    font-size:
        13px;
}

.register a {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    margin-top:
        8px;

    padding:
        9px 15px;

    border-radius:
        12px;

    color:
        var(--sage-dark);

    text-decoration:
        none;

    font-weight:
        700;

    background:
        rgba(255,255,255,.34);

    box-shadow:
        var(--inset);

    transition:
        .25s ease;
}

.register a:hover {

    transform:
        translateY(-1px);

    background:
        rgba(255,255,255,.55);
}

.register svg {

    width:
        15px;

    height:
        15px;
}


/* ===============================================================
   SECURITY NOTE
================================================================ */

.security-note {

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    gap:
        6px;

    margin-top:
        25px;

    color:
        #9AA29D;

    font-size:
        10px;

    letter-spacing:
        .25px;
}

.security-note svg {

    width:
        13px;

    height:
        13px;
}


/* ===============================================================
   MOBILE
================================================================ */

@media (max-width: 900px) {

    .container {

        grid-template-columns:
            1fr;
    }

    .peace-panel {

        min-height:
            430px;
    }

    .login-card {

        min-height:
            auto;
    }

    .botanical {

        width:
            220px;

        height:
            220px;
    }
}

@media (max-width: 600px) {

    .page {

        padding:
            15px;
    }

    .peace-panel {

        display:
            none;
    }

    .login-card {

        padding:
            32px 23px;

        border-radius:
            25px;
    }

    .login-heading h2 {

        font-size:
            34px;
    }
}


/* ===============================================================
   REDUCED MOTION
================================================================ */

@media (prefers-reduced-motion: reduce) {

    *,
    *::before,
    *::after {

        animation-duration:
            .01ms !important;

        animation-iteration-count:
            1 !important;

        transition-duration:
            .01ms !important;
    }

    #threeScene {

        display:
            none;
    }
}

</style>

</head>


<body>


<!-- =============================================================
     THREE.JS
================================================================ -->

<div id="threeScene"></div>


<!-- =============================================================
     BACKGROUND
================================================================ -->

<div class="background-shape shape-sage"></div>

<div class="background-shape shape-lavender"></div>

<div class="background-shape shape-peach"></div>


<!-- =============================================================
     MAIN
================================================================ -->

<main class="page">

<div class="container">


<!-- =============================================================
     PEACE / WELCOME SIDE
================================================================ -->

<section class="peace-panel">


    <!-- BRAND -->

    <div class="brand">

        <div class="brand-symbol">

            <img src="logo.png" alt="Haven" class="brand-logo-img">

        </div>

        <div class="brand-name">
            Haven
        </div>

    </div>


    <!-- WELCOME -->

    <div class="welcome-content">

        <div class="small-label">
            A quiet place to begin
        </div>


        <h1 class="welcome-title">

            Welcome<br>

            <span>
                back to you.
            </span>

        </h1>


        <p class="welcome-description">

            Take a breath. There is no rush here.
            Haven is a gentle space to connect,
            reflect, support one another, and take
            the next small step.

        </p>


        <div class="quote">

            “Sometimes the smallest step in the
            right direction becomes the biggest
            step of your life.”

        </div>

    </div>


    <!-- =========================================================
         BOTANICAL ILLUSTRATION
    ========================================================== -->

    <div class="botanical">

        <svg
            viewBox="0 0 280 280"
            xmlns="http://www.w3.org/2000/svg"
        >

            <!-- soft circle -->

            <circle
                cx="140"
                cy="145"
                r="96"
                fill="#FFFFFF"
                opacity=".20"
            />

            <circle
                cx="140"
                cy="145"
                r="75"
                fill="none"
                stroke="#A8C7B5"
                stroke-width="1"
                stroke-dasharray="3 9"
                opacity=".42"
            />


            <!-- stem -->

            <path
                d="M140 232
                   C138 204 141 181 140 145"
                stroke="#789E88"
                stroke-width="3"
                stroke-linecap="round"
                fill="none"
                opacity=".7"
            />


            <!-- leaf -->

            <path
                d="M139 205
                   C117 191 102 193 92 207
                   C110 215 126 214 139 205Z"
                fill="#A8C7B5"
                opacity=".7"
            />

            <path
                d="M141 193
                   C160 178 178 181 188 193
                   C172 202 155 201 141 193Z"
                fill="#B9D2C2"
                opacity=".62"
            />


            <!-- flower petals -->

            <g
                class="flower-petal"
                fill="#D9CFE5"
                opacity=".82"
            >

                <ellipse
                    cx="140"
                    cy="113"
                    rx="22"
                    ry="43"
                />

                <ellipse
                    cx="140"
                    cy="177"
                    rx="22"
                    ry="43"
                    transform="rotate(180 140 145)"
                />

                <ellipse
                    cx="108"
                    cy="145"
                    rx="22"
                    ry="43"
                    transform="rotate(-90 108 145)"
                />

                <ellipse
                    cx="172"
                    cy="145"
                    rx="22"
                    ry="43"
                    transform="rotate(90 172 145)"
                />

            </g>


            <!-- secondary petals -->

            <g
                fill="#E8DCCF"
                opacity=".82"
            >

                <ellipse
                    cx="118"
                    cy="123"
                    rx="17"
                    ry="32"
                    transform="rotate(-45 118 123)"
                />

                <ellipse
                    cx="162"
                    cy="123"
                    rx="17"
                    ry="32"
                    transform="rotate(45 162 123)"
                />

                <ellipse
                    cx="118"
                    cy="167"
                    rx="17"
                    ry="32"
                    transform="rotate(45 118 167)"
                />

                <ellipse
                    cx="162"
                    cy="167"
                    rx="17"
                    ry="32"
                    transform="rotate(-45 162 167)"
                />

            </g>


            <!-- center -->

            <circle
                class="flower-center"
                cx="140"
                cy="145"
                r="18"
                fill="#D8B79D"
                opacity=".72"
            />

            <circle
                cx="140"
                cy="145"
                r="7"
                fill="#F2DCCB"
            />

        </svg>

    </div>


    <div class="dot dot-one"></div>
    <div class="dot dot-two"></div>
    <div class="dot dot-three"></div>

</section>


<!-- =============================================================
     LOGIN
================================================================ -->

<section class="login-card">


    <div class="login-heading">

        <h2>
            Good to see you.
        </h2>

        <p>
            Sign in and continue at your own pace.
        </p>

    </div>


    <!-- ERROR -->

    <?php if ($error !== ''): ?>

        <div
            class="error"
            id="errorMessage"
        >

            <!-- Warning SVG -->

            <svg
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >

                <path
                    d="M12 3L21 20H3L12 3Z"
                    fill="currentColor"
                    opacity=".12"
                />

                <path
                    d="M12 3L21 20H3L12 3Z"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linejoin="round"
                />

                <path
                    d="M12 9V13"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                />

                <circle
                    cx="12"
                    cy="16.5"
                    r=".8"
                    fill="currentColor"
                />

            </svg>

            <span>
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- FORM -->

    <form
        method="POST"
        id="loginForm"
        autocomplete="on"
    >


        <!-- EMAIL -->

        <div class="field">

            <label for="email">
                Email address
            </label>

            <div class="input-wrap">

                <span class="input-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <rect
                            x="3"
                            y="5"
                            width="18"
                            height="14"
                            rx="3"
                            stroke="currentColor"
                            stroke-width="1.5"
                        />

                        <path
                            d="M4 7L12 13L20 7"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                    </svg>

                </span>


                <input
                    class="input"
                    id="email"
                    name="email"
                    type="email"
                    value="<?= htmlspecialchars(
                        $oldEmail,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    placeholder="your@email.com"
                    autocomplete="email"
                    required
                >

                <span class="focus-line"></span>

            </div>

        </div>


        <!-- PASSWORD -->

        <div class="field">

            <label for="password">
                Password
            </label>

            <div class="input-wrap">

                <span class="input-icon">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <rect
                            x="5"
                            y="10"
                            width="14"
                            height="10"
                            rx="2.5"
                            stroke="currentColor"
                            stroke-width="1.5"
                        />

                        <path
                            d="M8 10V7.5C8 5.29 9.79 3.5 12 3.5C14.21 3.5 16 5.29 16 7.5V10"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                        />

                        <circle
                            cx="12"
                            cy="15"
                            r="1"
                            fill="currentColor"
                        />

                    </svg>

                </span>


                <input
                    class="input"
                    id="password"
                    name="password"
                    type="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >


                <button
                    type="button"
                    class="password-button"
                    id="passwordToggle"
                    aria-label="Show password"
                >

                    <svg
                        id="eyeIcon"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <path
                            d="M2.5 12C4.6 7.9 8 5.5 12 5.5C16 5.5 19.4 7.9 21.5 12C19.4 16.1 16 18.5 12 18.5C8 18.5 4.6 16.1 2.5 12Z"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linejoin="round"
                        />

                        <circle
                            cx="12"
                            cy="12"
                            r="3"
                            stroke="currentColor"
                            stroke-width="1.5"
                        />

                    </svg>

                </button>


                <span class="focus-line"></span>

            </div>

        </div>


        <!-- OPTIONS -->

        <div class="form-options">

            <label class="remember">

                <input
                    type="checkbox"
                    name="remember"
                >

                <span>
                    Remember me
                </span>

            </label>


            <a
                href="forgot-password.php"
                class="forgot"
            >
                Forgot password?
            </a>

        </div>


        <!-- LOGIN -->

<button
    type="submit"
    class="login-button"
    id="loginButton"
    style="background-color: #87CEEB; color: #003B73; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 600; cursor: pointer;"
>
    <span id="loginText">
        Continue gently
    </span>
</button>

    </form>


    <!-- REGISTER -->

    <div class="register">

        <div>
            New to Haven?
        </div>

        <a href="register.php">

            Create an account

            <svg
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >

                <path
                    d="M5 12H19"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                />

                <path
                    d="M13 6L19 12L13 18"
                    stroke="currentColor"
                    stroke-width="1.7"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

            </svg>

        </a>

    </div>


    <!-- SECURITY -->

    <div class="security-note">

        <svg
            viewBox="0 0 24 24"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
        >

            <rect
                x="5"
                y="10"
                width="14"
                height="10"
                rx="2.5"
                stroke="currentColor"
                stroke-width="1.5"
            />

            <path
                d="M8 10V7.5C8 5.29 9.79 3.5 12 3.5C14.21 3.5 16 5.29 16 7.5V10"
                stroke="currentColor"
                stroke-width="1.5"
            />

        </svg>

        Your account information is securely protected.

    </div>

</section>

</div>

</main>


<script>

/* ===============================================================
  Haven LOGIN JAVASCRIPT
================================================================ */

document.addEventListener(
    'DOMContentLoaded',
    function () {


    /* ===========================================================
       GSAP ENTRANCE
    ============================================================ */

    if (typeof gsap !== 'undefined') {

        gsap.set(
            '.peace-panel, .login-card',
            {
                opacity: 0,
                y: 25
            }
        );

        gsap.to(
            '.peace-panel',
            {
                opacity: 1,
                y: 0,
                duration: 1.05,
                ease: 'power3.out'
            }
        );

        gsap.to(
            '.login-card',
            {
                opacity: 1,
                y: 0,
                duration: 1.05,
                delay: .13,
                ease: 'power3.out'
            }
        );


        gsap.from(
            '.brand',
            {
                opacity: 0,
                y: 12,
                duration: .65,
                delay: .3,
                ease: 'power2.out'
            }
        );


        gsap.from(
            '.small-label, .welcome-title, .welcome-description, .quote',
            {
                opacity: 0,
                y: 16,
                duration: .75,
                stagger: .12,
                delay: .42,
                ease: 'power2.out'
            }
        );


        gsap.from(
    '.login-heading, .field, .form-options, .register, .security-note',
    {
        opacity: 0,
        y: 13,
        duration: .6,
        stagger: .08,
        delay: .4,
        ease: 'power2.out'
    }

        );


        /* Flower breathing */

        gsap.to(
            '.flower-petal',
            {
                scale: 1.035,
                duration: 2.7,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            }
        );


        gsap.to(
            '.flower-center',
            {
                scale: 1.06,
                duration: 2.2,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            }
        );


        /* Background blobs */

        gsap.to(
            '.shape-sage',
            {
                x: 35,
                y: 25,
                duration: 7,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            }
        );

        gsap.to(
            '.shape-lavender',
            {
                x: -30,
                y: -25,
                duration: 8,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            }
        );


        /* Small dots */

        gsap.to(
            '.dot-one',
            {
                y: -14,
                duration: 3,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            }
        );

        gsap.to(
            '.dot-two',
            {
                y: 12,
                duration: 4,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut'
            }
        );

    }


    /* ===========================================================
       PASSWORD VISIBILITY
    ============================================================ */

    const password =
        document.getElementById(
            'password'
        );

    const toggle =
        document.getElementById(
            'passwordToggle'
        );

    const eyeIcon =
        document.getElementById(
            'eyeIcon'
        );


    if (password && toggle) {

        toggle.addEventListener(
            'click',
            function () {

                const showing =
                    password.type === 'text';


                password.type =
                    showing
                        ? 'password'
                        : 'text';


                toggle.setAttribute(
                    'aria-label',
                    showing
                        ? 'Show password'
                        : 'Hide password'
                );


                if (eyeIcon) {

                    if (!showing) {

                        eyeIcon.innerHTML = `
                            <path
                                d="M3 3L21 21"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />

                            <path
                                d="M10.6 10.6C10.2 11 10 11.5 10 12C10 13.1 10.9 14 12 14C12.5 14 13 13.8 13.4 13.4"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />

                            <path
                                d="M9.9 5.7C10.6 5.55 11.3 5.5 12 5.5C16 5.5 19.4 7.9 21.5 12C20.6 13.8 19.4 15.3 18 16.4"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />

                            <path
                                d="M6.1 7.3C4.65 8.4 3.45 9.95 2.5 12C4.6 16.1 8 18.5 12 18.5C13.4 18.5 14.7 18.2 15.9 17.7"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        `;

                    } else {

                        eyeIcon.innerHTML = `
                            <path
                                d="M2.5 12C4.6 7.9 8 5.5 12 5.5C16 5.5 19.4 7.9 21.5 12C19.4 16.1 16 18.5 12 18.5C8 18.5 4.6 16.1 2.5 12Z"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linejoin="round"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="3"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                        `;
                    }
                }

            }
        );
    }


    /* ===========================================================
       FORM SUBMIT
    ============================================================ */

    const form =
        document.getElementById(
            'loginForm'
        );

    const button =
        document.getElementById(
            'loginButton'
        );

    const buttonText =
        document.getElementById(
            'loginText'
        );


    if (form) {

        form.addEventListener(
            'submit',
            function (event) {

                const email =
                    document.getElementById(
                        'email'
                    );

                const pass =
                    document.getElementById(
                        'password'
                    );


                if (
                    !email.value.trim() ||
                    !pass.value
                ) {

                    event.preventDefault();

                    if (
                        typeof gsap !==
                        'undefined'
                    ) {

                        gsap.to(
                            '.login-card',
                            {
                                x: -6,
                                duration: .07,
                                repeat: 5,
                                yoyo: true,
                                ease: 'power1.inOut'
                            }
                        );
                    }

                    return;
                }


                /*
                ---------------------------------------------------
                Loading state
                ---------------------------------------------------
                */

                if (button) {

                    button.classList.add(
                        'loading'
                    );

                    button.disabled =
                        true;
                }


                if (buttonText) {

                    buttonText.innerHTML = `
                        <span class="loader"></span>
                        One moment...
                    `;
                }

            }
        );
    }


    /* ===========================================================
       ERROR ANIMATION
    ============================================================ */

    const error =
        document.getElementById(
            'errorMessage'
        );

    if (
        error &&
        typeof gsap !== 'undefined'
    ) {

        gsap.from(
            error,
            {
                opacity: 0,
                y: -8,
                duration: .45,
                ease: 'power2.out'
            }
        );

        gsap.to(
            error,
            {
                x: -4,
                duration: .06,
                repeat: 3,
                yoyo: true,
                delay: .45
            }
        );
    }


    /* ===========================================================
       THREE.JS
       Very subtle particles only.
       The page remains calm even if Three.js fails.
    ============================================================ */

    if (
        typeof THREE !== 'undefined'
    ) {

        const host =
            document.getElementById(
                'threeScene'
            );

        if (host) {

            try {

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

                camera.position.z =
                    7;


                const renderer =
                    new THREE.WebGLRenderer({
                        alpha: true,
                        antialias: true
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


                host.appendChild(
                    renderer.domElement
                );


                /*
                ---------------------------------------------------
                Particles
                ---------------------------------------------------
                */

                const count =
                    window.innerWidth < 600
                        ? 100
                        : 190;


                const positions =
                    new Float32Array(
                        count * 3
                    );


                for (
                    let i = 0;
                    i < count;
                    i++
                ) {

                    positions[
                        i * 3
                    ] =
                        (Math.random() - .5)
                        * 15;


                    positions[
                        i * 3 + 1
                    ] =
                        (Math.random() - .5)
                        * 10;


                    positions[
                        i * 3 + 2
                    ] =
                        (Math.random() - .5)
                        * 8;
                }


                const geometry =
                    new THREE.BufferGeometry();


                geometry.setAttribute(
                    'position',
                    new THREE.BufferAttribute(
                        positions,
                        3
                    )
                );


                const material =
                    new THREE.PointsMaterial({

                        color:
                            0xA8BFAF,

                        size:
                            .035,

                        transparent:
                            true,

                        opacity:
                            .35
                    });


                const particles =
                    new THREE.Points(
                        geometry,
                        material
                    );


                scene.add(
                    particles
                );


                /*
                ---------------------------------------------------
                Very subtle rings
                ---------------------------------------------------
                */

                const ringGeometry =
                    new THREE.RingGeometry(
                        2.2,
                        2.205,
                        96
                    );


                const ringMaterial =
                    new THREE.MeshBasicMaterial({

                        color:
                            0xC7C2DD,

                        transparent:
                            true,

                        opacity:
                            .08,

                        side:
                            THREE.DoubleSide
                    });


                const ring =
                    new THREE.Mesh(
                        ringGeometry,
                        ringMaterial
                    );


                ring.position.set(
                    -3.7,
                    2.4,
                    -3
                );


                scene.add(
                    ring
                );


                /*
                ---------------------------------------------------
                Mouse movement
                ---------------------------------------------------
                */

                let mouseX = 0;
                let mouseY = 0;


                window.addEventListener(
                    'mousemove',
                    function (event) {

                        mouseX =
                            (
                                event.clientX /
                                window.innerWidth
                                - .5
                            );

                        mouseY =
                            (
                                event.clientY /
                                window.innerHeight
                                - .5
                            );
                    }
                );


                /*
                ---------------------------------------------------
                Render
                ---------------------------------------------------
                */

                function render() {

                    requestAnimationFrame(
                        render
                    );


                    particles.rotation.y +=
                        .00015;

                    particles.rotation.x +=
                        .00003;


                    ring.rotation.z +=
                        .00015;


                    camera.position.x +=
                        (
                            mouseX * .18 -
                            camera.position.x
                        ) * .008;


                    camera.position.y +=
                        (
                            -mouseY * .12 -
                            camera.position.y
                        ) * .008;


                    camera.lookAt(
                        scene.position
                    );


                    renderer.render(
                        scene,
                        camera
                    );
                }


                render();


                /*
                ---------------------------------------------------
                Resize
                ---------------------------------------------------
                */

                window.addEventListener(
                    'resize',
                    function () {

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

            } catch (e) {

                /*
                 * Three.js is decorative.
                 * Never allow it to break login.
                 */

                console.warn(
                    'Haven background unavailable.'
                );
            }
        }
    }

});

</script>


</body>

</html>