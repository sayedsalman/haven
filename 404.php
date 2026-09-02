<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isLoggedIn = !empty($_SESSION['user_id']);

$homeUrl = 'index.php';
$communityUrl = 'community.php';
$dashboardUrl = 'dashboard.php';
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
        name="description"
        content="The page you're looking for could not be found on Haven."
    >

    <meta name="theme-color" content="#f7f4ed">

    <title>Lost Your Way? · Haven</title>

    <link
        rel="icon"
        type="image/png"
        href="assets/images/favicon.png"
    >

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
    ></script>

    <style>

        :root {
            --cream: #f7f4ed;
            --cream-deep: #eee9df;
            --white: rgba(255,255,255,.78);

            --sage: #879d8b;
            --sage-dark: #5f7765;
            --sage-soft: #dce7dd;

            --peach: #e8b99f;
            --peach-soft: #f4ddd0;

            --ink: #27332c;
            --muted: #788178;

            --border: rgba(95,119,101,.13);

            --shadow:
                0 25px 70px rgba(65,76,67,.10),
                0 5px 20px rgba(65,76,67,.05);

            --soft-shadow:
                12px 12px 30px rgba(90,101,91,.08),
                -10px -10px 30px rgba(255,255,255,.75);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            overflow-x: hidden;

            background:
                radial-gradient(
                    circle at 15% 15%,
                    rgba(232,185,159,.20),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 85% 20%,
                    rgba(135,157,139,.18),
                    transparent 30%
                ),
                radial-gradient(
                    circle at 50% 100%,
                    rgba(220,231,221,.45),
                    transparent 35%
                ),
                var(--cream);

            color: var(--ink);

            font-family:
                "DM Sans",
                system-ui,
                sans-serif;
        }

        /* =========================
           Ambient background
        ========================== */

        .ambient {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 0;
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(1px);
            opacity: .55;
        }

        .orb-one {
            width: 280px;
            height: 280px;

            left: -100px;
            top: 18%;

            background:
                radial-gradient(
                    circle,
                    rgba(232,185,159,.30),
                    transparent 68%
                );
        }

        .orb-two {
            width: 350px;
            height: 350px;

            right: -130px;
            top: 10%;

            background:
                radial-gradient(
                    circle,
                    rgba(135,157,139,.25),
                    transparent 68%
                );
        }

        .orb-three {
            width: 300px;
            height: 300px;

            left: 42%;
            bottom: -160px;

            background:
                radial-gradient(
                    circle,
                    rgba(220,231,221,.60),
                    transparent 68%
                );
        }

        /* =========================
           Navigation
        ========================== */

        .topbar {
            position: relative;
            z-index: 20;

            width: min(1180px, calc(100% - 40px));
            margin: 22px auto 0;

            display: flex;
            align-items: center;
            justify-content: space-between;

            padding: 12px 14px 12px 20px;

            border:
                1px solid rgba(255,255,255,.70);

            background:
                rgba(255,255,255,.48);

            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);

            border-radius: 22px;

            box-shadow:
                0 10px 40px rgba(70,80,72,.06);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 11px;

            text-decoration: none;
            color: var(--ink);
        }

        .brand-mark {
            width: 42px;
            height: 42px;

            border-radius: 14px;

            display: grid;
            place-items: center;

            background:
                linear-gradient(
                    145deg,
                    #f5dfd2,
                    #dce8dd
                );

            box-shadow:
                inset 2px 2px 7px rgba(255,255,255,.8),
                inset -2px -2px 7px rgba(101,120,104,.10);
        }

        .brand-mark svg {
            width: 23px;
            height: 23px;
        }

        .brand-name {
            font-family: "Playfair Display", serif;
            font-size: 23px;
            font-weight: 600;
            letter-spacing: -.3px;
        }

        .top-home {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            text-decoration: none;

            color: var(--sage-dark);

            font-size: 14px;
            font-weight: 600;

            padding: 11px 17px;

            border-radius: 14px;

            transition:
                transform .25s ease,
                background .25s ease;
        }

        .top-home:hover {
            background: rgba(255,255,255,.7);
            transform: translateY(-2px);
        }

        /* =========================
           Main
        ========================== */

        .page {
            position: relative;
            z-index: 2;

            min-height:
                calc(100vh - 100px);

            width: min(1180px, calc(100% - 40px));

            margin: auto;

            display: grid;
            place-items: center;

            padding: 70px 0 80px;
        }

        .content {
            width: min(820px, 100%);
            text-align: center;
        }

        /* =========================
           Illustration
        ========================== */

        .illustration {
            position: relative;

            width: 210px;
            height: 210px;

            margin: 0 auto 30px;
        }

        .halo {
            position: absolute;
            inset: 0;

            border-radius: 50%;

            background:
                radial-gradient(
                    circle,
                    rgba(220,231,221,.75),
                    rgba(220,231,221,.18) 58%,
                    transparent 70%
                );
        }

        .circle {
            position: absolute;

            width: 142px;
            height: 142px;

            left: 50%;
            top: 50%;

            transform: translate(-50%,-50%);

            border-radius: 50%;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.9),
                    rgba(232,239,231,.7)
                );

            border:
                1px solid rgba(255,255,255,.9);

            box-shadow:
                var(--soft-shadow);

            display: grid;
            place-items: center;
        }

        .circle-inner {
            width: 92px;
            height: 92px;

            border-radius: 50%;

            display: grid;
            place-items: center;

            background:
                linear-gradient(
                    145deg,
                    var(--peach-soft),
                    var(--sage-soft)
                );

            box-shadow:
                inset 5px 5px 12px rgba(90,100,91,.06),
                inset -5px -5px 12px rgba(255,255,255,.75);
        }

        .circle-inner svg {
            width: 48px;
            height: 48px;

            stroke: var(--sage-dark);
        }

        .leaf {
            position: absolute;

            width: 17px;
            height: 28px;

            border-radius: 100% 0 100% 0;

            background: var(--sage);

            opacity: .65;
        }

        .leaf-one {
            left: 25px;
            top: 57px;
            transform: rotate(-30deg);
        }

        .leaf-two {
            right: 25px;
            top: 100px;
            transform: rotate(145deg);
        }

        .dot {
            position: absolute;

            width: 9px;
            height: 9px;

            border-radius: 50%;

            background: var(--peach);
        }

        .dot-one {
            top: 38px;
            right: 55px;
        }

        .dot-two {
            left: 52px;
            bottom: 35px;

            width: 6px;
            height: 6px;

            background: var(--sage);
        }

        /* =========================
           Typography
        ========================== */

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;

            margin-bottom: 18px;

            padding: 8px 13px;

            border-radius: 999px;

            background: rgba(255,255,255,.55);

            border:
                1px solid rgba(255,255,255,.8);

            color: var(--sage-dark);

            font-size: 12px;
            font-weight: 700;

            letter-spacing: .7px;
            text-transform: uppercase;
        }

        .eyebrow-dot {
            width: 6px;
            height: 6px;

            border-radius: 50%;

            background: var(--sage);
        }

        h1 {
            font-family: "Playfair Display", serif;

            font-size:
                clamp(54px, 9vw, 92px);

            line-height: .95;

            letter-spacing: -3px;

            font-weight: 600;

            margin-bottom: 18px;

            color: var(--ink);
        }

        h1 span {
            background:
                linear-gradient(
                    100deg,
                    var(--sage-dark),
                    #819989,
                    #c58f78
                );

            -webkit-background-clip: text;
            background-clip: text;

            color: transparent;
        }

        .subtitle {
            max-width: 600px;

            margin: 0 auto;

            color: var(--muted);

            font-size:
                clamp(15px, 2vw, 18px);

            line-height: 1.8;
        }

        /* =========================
           Actions
        ========================== */

        .actions {
            display: flex;

            align-items: center;
            justify-content: center;

            flex-wrap: wrap;

            gap: 12px;

            margin-top: 34px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;

            min-height: 48px;

            padding: 0 20px;

            border-radius: 15px;

            text-decoration: none;

            font-size: 14px;
            font-weight: 700;

            transition:
                transform .25s ease,
                box-shadow .25s ease,
                background .25s ease;
        }

        .btn:hover {
            transform: translateY(-3px);
        }

        .btn-primary {
            color: white;

            background:
                linear-gradient(
                    135deg,
                    var(--sage-dark),
                    #7f9885
                );

            box-shadow:
                0 12px 25px rgba(95,119,101,.20);
        }

        .btn-primary:hover {
            box-shadow:
                0 17px 32px rgba(95,119,101,.25);
        }

        .btn-secondary {
            color: var(--sage-dark);

            background:
                rgba(255,255,255,.68);

            border:
                1px solid rgba(255,255,255,.85);

            box-shadow:
                0 10px 25px rgba(70,80,72,.06);
        }

        /* =========================
           Helpful message
        ========================== */

        .comfort-card {
            width: min(560px, 100%);

            margin: 46px auto 0;

            padding: 19px 22px;

            border-radius: 20px;

            background:
                rgba(255,255,255,.47);

            border:
                1px solid rgba(255,255,255,.76);

            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);

            box-shadow:
                0 15px 40px rgba(65,76,67,.05);

            color: var(--muted);

            font-size: 13px;
            line-height: 1.7;
        }

        .comfort-card strong {
            color: var(--ink);
        }

        /* =========================
           Footer
        ========================== */

        footer {
            position: relative;
            z-index: 2;

            text-align: center;

            padding: 0 20px 28px;

            color: #9aa29b;

            font-size: 12px;
        }

        footer span {
            color: var(--sage-dark);
        }

        /* =========================
           Responsive
        ========================== */

        @media (max-width: 600px) {

            .topbar {
                width: calc(100% - 24px);
                margin-top: 12px;

                border-radius: 18px;

                padding:
                    9px 10px
                    9px 14px;
            }

            .brand-name {
                font-size: 20px;
            }

            .brand-mark {
                width: 38px;
                height: 38px;
                border-radius: 12px;
            }

            .top-home {
                font-size: 12px;
                padding: 9px 12px;
            }

            .page {
                width: calc(100% - 28px);

                min-height:
                    calc(100vh - 90px);

                padding:
                    45px 0 60px;
            }

            .illustration {
                width: 175px;
                height: 175px;
            }

            .circle {
                width: 120px;
                height: 120px;
            }

            .circle-inner {
                width: 78px;
                height: 78px;
            }

            .circle-inner svg {
                width: 40px;
                height: 40px;
            }

            h1 {
                letter-spacing: -2px;
            }

            .subtitle {
                font-size: 14px;
                line-height: 1.7;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: min(280px, 100%);
            }

            .comfort-card {
                margin-top: 34px;
                padding: 17px;
            }
        }

        /* =========================
           Reduced motion
        ========================== */

        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: .01ms !important;
            }
        }

    </style>
</head>

<body>

<div class="ambient">
    <div class="orb orb-one"></div>
    <div class="orb orb-two"></div>
    <div class="orb orb-three"></div>
</div>


<header class="topbar">

    <a
        href="<?= htmlspecialchars($homeUrl) ?>"
        class="brand"
    >

        <div class="brand-mark">

            <svg
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path
                    d="M12 21s-7-4.35-9.5-9.1C.8 8.55
                       2.55 5 6.2 5c2.05 0 3.55 1.12
                       4.55 2.5C11.75 6.12 13.25 5
                       15.3 5c3.65 0 5.4 3.55
                       3.7 6.9C19 16.65 12 21 12 21Z"
                />
            </svg>

        </div>

        <span class="brand-name">Haven</span>

    </a>


    <a
        href="<?= htmlspecialchars($homeUrl) ?>"
        class="top-home"
    >
        <span>←</span>
        Return home
    </a>

</header>


<main class="page">

    <section class="content">

        <div class="illustration">

            <div class="halo"></div>

            <div class="leaf leaf-one"></div>
            <div class="leaf leaf-two"></div>

            <div class="dot dot-one"></div>
            <div class="dot dot-two"></div>

            <div class="circle">

                <div class="circle-inner">

                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="M12 20V10"/>
                        <path d="M12 10C8 10 5 7.8 5 4"/>
                        <path d="M12 10C16 10 19 7.8 19 4"/>
                        <path d="M12 20C8.5 20 6 18 5 15"/>
                        <path d="M12 20C15.5 20 18 18 19 15"/>
                    </svg>

                </div>

            </div>

        </div>


        <div class="eyebrow">

            <span class="eyebrow-dot"></span>

            A gentle detour

        </div>


        <h1>
            <span>404</span>
        </h1>


        <p class="subtitle">
            The page you're looking for seems to have wandered
            somewhere else. That's okay — sometimes taking a
            different path is exactly what we need.
        </p>


        <div class="actions">

            <a
                href="<?= htmlspecialchars($homeUrl) ?>"
                class="btn btn-primary"
            >
                <span>⌂</span>
                Back to Haven
            </a>


            <a
                href="<?= htmlspecialchars($communityUrl) ?>"
                class="btn btn-secondary"
            >
                Explore Community
                <span>→</span>
            </a>


            <?php if ($isLoggedIn): ?>

                <a
                    href="<?= htmlspecialchars($dashboardUrl) ?>"
                    class="btn btn-secondary"
                >
                    My Haven
                </a>

            <?php endif; ?>

        </div>


        <div class="comfort-card">

            <strong>You haven't lost your way.</strong>

            <br>

            If you arrived here from a broken link, you can
            simply return to Haven and continue from there.

        </div>

    </section>

</main>


<footer>

    Haven · A space to pause, breathe and connect

    <br>

    <span>You belong here.</span>

</footer>


<script>

document.addEventListener("DOMContentLoaded", () => {

    if (
        typeof gsap === "undefined"
    ) {
        return;
    }


    const tl = gsap.timeline({
        defaults: {
            ease: "power3.out"
        }
    });


    tl.from(".topbar", {
        y: -25,
        opacity: 0,
        duration: .7
    })

    .from(".illustration", {
        scale: .82,
        opacity: 0,
        duration: 1
    }, "-=.3")

    .from(".eyebrow", {
        y: 15,
        opacity: 0,
        duration: .5
    }, "-=.55")

    .from("h1", {
        y: 20,
        opacity: 0,
        duration: .6
    }, "-=.3")

    .from(".subtitle", {
        y: 15,
        opacity: 0,
        duration: .55
    }, "-=.35")

    .from(".actions", {
        y: 15,
        opacity: 0,
        duration: .55
    }, "-=.3")

    .from(".comfort-card", {
        y: 12,
        opacity: 0,
        duration: .5
    }, "-=.25");


    /* Gentle breathing animation */

    gsap.to(".circle-inner", {
        scale: 1.055,
        duration: 2.8,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut"
    });


    /* Floating ambient elements */

    gsap.to(".orb-one", {
        x: 35,
        y: -25,
        duration: 7,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut"
    });


    gsap.to(".orb-two", {
        x: -30,
        y: 30,
        duration: 8,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut"
    });


    gsap.to(".leaf-one", {
        rotation: -22,
        duration: 2.8,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut"
    });


    gsap.to(".leaf-two", {
        rotation: 137,
        duration: 3.2,
        repeat: -1,
        yoyo: true,
        ease: "sine.inOut"
    });


    /* Button micro interaction */

    document
        .querySelectorAll(".btn, .top-home")
        .forEach(button => {

            button.addEventListener(
                "mouseenter",
                () => {

                    gsap.to(button, {
                        y: -3,
                        duration: .2,
                        overwrite: true
                    });

                }
            );

            button.addEventListener(
                "mouseleave",
                () => {

                    gsap.to(button, {
                        y: 0,
                        duration: .2,
                        overwrite: true
                    });

                }
            );

        });

});

</script>

</body>
</html>
