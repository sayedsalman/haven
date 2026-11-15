<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Haven – Mental Health Check-In</title>
    <link rel="icon" type="image/png" href="https://salman.rfnhsc.com/logo.png">
    <style>
        :root {
            --bg: #F5F8F5;
            --soft-green: #A8C3B0;
            --deep-green: #315C4A;
            --cream: #FFF9EF;
            --lavender: #DCD9EE;
            --peach: #F2D0C0;
            --text: #26352F;
            --text-light: #5A6E65;
            --card-bg: rgba(255, 255, 255, 0.7);
            --shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
            --radius: 24px;
            --transition: 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .app-container {
            max-width: 700px;
            width: 100%;
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            position: relative;
            overflow: hidden;
            transition: all var(--transition);
        }

        .section {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        .section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .logo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--deep-green);
            letter-spacing: 2px;
        }
        .logo span {
            color: var(--soft-green);
        }

        /* Hero */
        .hero {
            text-align: center;
        }
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--deep-green);
        }
        .hero p {
            font-size: 1.1rem;
            color: var(--text-light);
            margin-bottom: 1.5rem;
        }
        .feature-list {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .feature-item {
            background: white;
            padding: 0.75rem 1.25rem;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn {
            display: inline-block;
            background: var(--deep-green);
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(49, 92, 74, 0.3);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(49, 92, 74, 0.4);
        }
        .btn.secondary {
            background: transparent;
            color: var(--deep-green);
            border: 2px solid var(--deep-green);
            box-shadow: none;
            margin-right: 0.5rem;
        }
        .btn.small {
            padding: 0.5rem 1.25rem;
            font-size: 0.9rem;
        }
        .disclaimer {
            margin-top: 1.5rem;
            font-size: 0.75rem;
            color: var(--text-light);
            font-style: italic;
        }

        /* Assessment Selection */
        .assessment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .assessment-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .assessment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--soft-green);
        }
        .assessment-card .icon {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        .assessment-card h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }
        .assessment-card p {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        /* Questionnaire */
        .progress-container {
            margin-bottom: 1.5rem;
        }
        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: var(--deep-green);
            border-radius: 10px;
            transition: width 0.4s ease;
        }
        .question-text {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            color: var(--text);
        }
        .answer-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .answer-card {
            background: white;
            border: 2px solid transparent;
            border-radius: 16px;
            padding: 1rem 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .answer-card:hover {
            border-color: var(--soft-green);
            transform: translateX(5px);
        }
        .answer-card.selected {
            border-color: var(--deep-green);
            background: #f0f7f2;
            box-shadow: 0 4px 15px rgba(49, 92, 74, 0.15);
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }

        /* Pause overlay */
        .pause-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(245, 248, 245, 0.97);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            flex-direction: column;
            animation: fadeIn 0.3s ease;
        }
        .pause-overlay.active {
            display: flex;
        }
        .breathing-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: radial-gradient(circle, var(--soft-green), var(--deep-green));
            animation: breathe 10s infinite ease-in-out;
            box-shadow: 0 0 40px rgba(168, 195, 176, 0.6);
            margin: 2rem 0;
        }
        @keyframes breathe {
            0%, 100% { transform: scale(1); }
            40% { transform: scale(1.3); }
            60% { transform: scale(1.3); }
        }
        .breath-text {
            font-size: 1.5rem;
            color: var(--deep-green);
            font-weight: 600;
        }
        .breath-timer {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 1rem;
        }

        /* Results */
        .result-overview {
            text-align: center;
            margin-bottom: 2rem;
        }
        .result-score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--deep-green);
        }
        .result-category {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--deep-green);
            margin-bottom: 0.5rem;
        }
        .result-message {
            color: var(--text-light);
            max-width: 500px;
            margin: 0 auto;
        }
        .domain-results {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            margin: 2rem 0;
        }
        .domain-item {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .domain-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        .domain-name {
            font-weight: 600;
        }
        .domain-level {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        .domain-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        .domain-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
        }
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        .action-card {
            background: white;
            border-radius: 16px;
            padding: 1.25rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            text-align: center;
        }
        .action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .action-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .action-desc {
            font-size: 0.85rem;
            color: var(--text-light);
        }

        /* 7-day plan */
        .week-plan {
            margin-top: 2rem;
        }
        .day-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: white;
            border-radius: 12px;
            margin-bottom: 0.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        }
        .day-checkbox {
            width: 22px;
            height: 22px;
            border: 2px solid var(--soft-green);
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .day-checkbox.completed {
            background: var(--deep-green);
            border-color: var(--deep-green);
            color: white;
        }
        .day-checkbox.completed::after {
            content: "✓";
            font-size: 14px;
        }

        /* Crisis screen */
        .crisis-screen {
            text-align: center;
            padding: 2rem;
            background: #fff5f5;
            border-radius: var(--radius);
            border: 1px solid #f8d7da;
        }
        .crisis-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .crisis-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #721c24;
            margin-bottom: 1rem;
        }
        .crisis-text {
            color: #721c24;
            margin-bottom: 1.5rem;
        }
        .crisis-btn {
            background: #dc3545;
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            margin: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .app-container { padding: 1.5rem; }
            .hero h1 { font-size: 2rem; }
            .assessment-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <div class="header">
            <div class="logo">Haven<span>.</span></div>
        </div>

        <!-- HERO SECTION -->
        <div id="hero" class="section active">
            <div class="hero">
                <h1>How are you really feeling?</h1>
                <p>Take a few quiet moments to check in with yourself. There are no right or wrong answers.</p>
                <div class="feature-list">
                    <div class="feature-item">🌱 5–10 minutes</div>
                    <div class="feature-item">🔒 Private & confidential</div>
                    <div class="feature-item">🧠 Science-informed</div>
                    <div class="feature-item">💚 No judgment, ever</div>
                </div>
                <button class="btn" onclick="showSection('selection')">Begin My Check-In →</button>
                <p class="disclaimer">This assessment is for self-reflection and does not replace professional diagnosis or treatment.</p>
            </div>
        </div>

        <!-- ASSESSMENT SELECTION -->
        <div id="selection" class="section">
            <h2 style="text-align:center; margin-bottom:1rem;">What would you like to understand today?</h2>
            <div class="assessment-grid">
                <div class="assessment-card" onclick="startAssessment('mood')">
                    <div class="icon">🌧️</div>
                    <h3>Mood Check</h3>
                    <p>Explore feelings of sadness and low mood</p>
                </div>
                <div class="assessment-card" onclick="startAssessment('anxiety')">
                    <div class="icon">🌪️</div>
                    <h3>Anxiety Check</h3>
                    <p>Understand worry, nervousness and tension</p>
                </div>
                <div class="assessment-card" onclick="startAssessment('stress')">
                    <div class="icon">🔥</div>
                    <h3>Stress Check</h3>
                    <p>Explore your current stress level</p>
                </div>
                <div class="assessment-card" onclick="startAssessment('sleep')">
                    <div class="icon">😴</div>
                    <h3>Sleep & Rest</h3>
                    <p>Understand your sleep and recovery</p>
                </div>
                <div class="assessment-card" onclick="startAssessment('burnout')">
                    <div class="icon">⚡</div>
                    <h3>Burnout Check</h3>
                    <p>Explore emotional exhaustion</p>
                </div>
                <div class="assessment-card" onclick="startAssessment('wellbeing')">
                    <div class="icon">💭</div>
                    <h3>Emotional Wellbeing</h3>
                    <p>A broader overall wellbeing check</p>
                </div>
                <div class="assessment-card" onclick="startAssessment('combined')" style="grid-column: span 2;">
                    <div class="icon">✨</div>
                    <h3>Complete Haven Check-In</h3>
                    <p>Combine several short assessments</p>
                </div>
            </div>
            <div style="text-align:center; margin-top:2rem;">
                <button class="btn secondary small" onclick="showSection('hero')">← Back</button>
            </div>
        </div>

        <!-- QUESTIONNAIRE -->
        <div id="questionnaire" class="section">
            <div class="progress-container">
                <div class="progress-info">
                    <span id="question-counter">Question 1 / 5</span>
                    <span id="assessment-name"></span>
                </div>
                <div class="progress-bar">
                    <div id="progress-fill" class="progress-fill" style="width: 0%;"></div>
                </div>
            </div>
            <p class="question-text" id="question-text"></p>
            <div class="answer-options" id="answer-options"></div>
            <div class="nav-buttons">
                <button class="btn secondary small" id="back-btn" onclick="previousQuestion()">← Back</button>
                <div>
                    <button class="btn secondary small" onclick="openPause()">🌿 Need a moment?</button>
                    <button class="btn small" id="continue-btn" onclick="nextQuestion()" disabled>Continue →</button>
                </div>
            </div>
        </div>

        <!-- RESULTS -->
        <div id="results" class="section">
            <div class="result-overview">
                <h2>🌱 Your Haven Check-In</h2>
                <div id="result-score-circle" class="result-score-circle">--</div>
                <div id="result-category" class="result-category"></div>
                <div id="result-message" class="result-message"></div>
            </div>
            <div id="domain-results" class="domain-results"></div>
            <h3 style="margin-bottom:1rem;">💚 What might help</h3>
            <div class="actions-grid">
                <div class="action-card">
                    <div class="action-icon">🌿</div>
                    <div class="action-title">5-minute reset</div>
                    <div class="action-desc">Try a guided breathing exercise</div>
                </div>
                <div class="action-card">
                    <div class="action-icon">📝</div>
                    <div class="action-title">Write it out</div>
                    <div class="action-desc">"What's been weighing on my mind lately?"</div>
                </div>
                <div class="action-card">
                    <div class="action-icon">🌙</div>
                    <div class="action-title">Improve your evening</div>
                    <div class="action-desc">Create a gentle sleep routine</div>
                </div>
                <div class="action-card">
                    <div class="action-icon">🤝</div>
                    <div class="action-title">Talk to someone</div>
                    <div class="action-desc">Reach out to a trusted person</div>
                </div>
            </div>
            <h3 style="margin-bottom:1rem;">📅 Your Next 7 Days</h3>
            <div class="week-plan" id="week-plan"></div>
            <div style="text-align:center; margin-top:2rem;">
                <button class="btn secondary small" onclick="showSection('selection')">← Take another check-in</button>
                <button class="btn small" onclick="showSection('hero')">Done</button>
            </div>
        </div>

        <!-- CRISIS SCREEN -->
        <div id="crisis" class="section">
            <div class="crisis-screen">
                <div class="crisis-icon">💚</div>
                <div class="crisis-title">We're glad you told us</div>
                <div class="crisis-text">You don't have to handle this alone. Your feelings are valid, and help is available.</div>
                <a href="tel:988" class="crisis-btn">📞 Call Suicide & Crisis Lifeline (988)</a>
                <br>
                <a href="sms:988" class="crisis-btn">💬 Text 988</a>
                <br>
                <button class="btn secondary small" onclick="showSection('hero')" style="margin-top:1rem;">Return to Haven</button>
            </div>
        </div>
    </div>

    <!-- PAUSE OVERLAY -->
    <div id="pause-overlay" class="pause-overlay">
        <div class="breath-text" id="breath-text">Breathe in</div>
        <div class="breathing-circle"></div>
        <div class="breath-timer" id="breath-timer">30 seconds remaining</div>
        <button class="btn secondary small" onclick="closePause()">Return to assessment</button>
    </div>

    <script>
        // ===================== ASSESSMENT DATA =====================
        const assessments = {
            mood: {
                name: 'Mood Check',
                questions: [
                    { text: 'Over the past two weeks, how often have you felt little interest or pleasure in doing things?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you felt down, depressed, or hopeless?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you had trouble falling or staying asleep, or sleeping too much?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you felt tired or had little energy?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you felt bad about yourself — or that you are a failure or have let yourself or your family down?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you had thoughts that you would be better off dead or of hurting yourself in some way?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'], safety: true }
                ]
            },
            anxiety: {
                name: 'Anxiety Check',
                questions: [
                    { text: 'Over the past two weeks, how often have you felt nervous, anxious, or on edge?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you not been able to stop or control worrying?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you worried too much about different things?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you had trouble relaxing?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you been so restless that it is hard to sit still?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you become easily annoyed or irritable?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] }
                ]
            },
            stress: {
                name: 'Stress Check',
                questions: [
                    { text: 'In the last month, how often have you felt that you were unable to control the important things in your life?', options: ['Never', 'Almost never', 'Sometimes', 'Fairly often'] },
                    { text: 'In the last month, how often have you felt confident about your ability to handle your personal problems? (reverse scored)', options: ['Very often', 'Fairly often', 'Sometimes', 'Almost never'] },
                    { text: 'In the last month, how often have you felt that things were going your way? (reverse scored)', options: ['Very often', 'Fairly often', 'Sometimes', 'Almost never'] },
                    { text: 'In the last month, how often have you felt difficulties were piling up so high that you could not overcome them?', options: ['Never', 'Almost never', 'Sometimes', 'Fairly often'] }
                ]
            },
            sleep: {
                name: 'Sleep & Rest',
                questions: [
                    { text: 'Over the past two weeks, how often have you had difficulty falling asleep?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you woken up during the night?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you woken up too early and couldn\'t get back to sleep?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you felt unrested after sleep?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] }
                ]
            },
            burnout: {
                name: 'Burnout Check',
                questions: [
                    { text: 'Over the past month, how often have you felt emotionally drained?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past month, how often have you felt used up at the end of the day?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past month, how often have you felt fatigued when you get up in the morning?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past month, how often have you felt frustrated by your work or daily responsibilities?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] }
                ]
            },
            wellbeing: {
                name: 'Emotional Wellbeing',
                questions: [
                    { text: 'Over the past two weeks, I have felt cheerful and in good spirits', options: ['All of the time', 'Most of the time', 'Some of the time', 'None of the time'] },
                    { text: 'Over the past two weeks, I have felt calm and relaxed', options: ['All of the time', 'Most of the time', 'Some of the time', 'None of the time'] },
                    { text: 'Over the past two weeks, I have felt active and vigorous', options: ['All of the time', 'Most of the time', 'Some of the time', 'None of the time'] },
                    { text: 'Over the past two weeks, I woke up feeling fresh and rested', options: ['All of the time', 'Most of the time', 'Some of the time', 'None of the time'] },
                    { text: 'Over the past two weeks, my daily life has been filled with things that interest me', options: ['All of the time', 'Most of the time', 'Some of the time', 'None of the time'] }
                ]
            },
            combined: {
                name: 'Complete Haven Check-In',
                questions: [
                    { text: 'Over the past two weeks, how often have you felt little interest or pleasure in doing things?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you felt nervous, anxious, or on edge?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'In the last month, how often have you felt that you were unable to control the important things in your life?', options: ['Never', 'Almost never', 'Sometimes', 'Fairly often'] },
                    { text: 'Over the past two weeks, how often have you had difficulty falling asleep?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you felt tired or had little energy?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'] },
                    { text: 'Over the past two weeks, how often have you had thoughts that you would be better off dead or of hurting yourself in some way?', options: ['Never', 'Sometimes', 'Often', 'Nearly every day'], safety: true }
                ]
            }
        };

        // ===================== STATE =====================
        let currentAssessmentKey = null;
        let currentQuestionIndex = 0;
        let answers = []; // array of selected option index (0-3)
        let selectedOption = null;

        // ===================== UI HELPERS =====================
        function showSection(sectionId) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            // Show target
            document.getElementById(sectionId).classList.add('active');
            // Scroll to top of container
            document.querySelector('.app-container').scrollIntoView({ behavior: 'smooth' });
        }

        function getScoreCategory(avgScore, isWellbeing = false) {
            // avgScore: 0-3 where higher = more concern, except wellbeing where higher = better
            if (isWellbeing) {
                // Invert for wellbeing (higher avg = better)
                if (avgScore >= 2.5) return { category: 'Thriving', level: 'Low concern', message: 'Your emotional wellbeing appears strong. Keep nurturing the things that bring you joy.' };
                if (avgScore >= 1.5) return { category: 'Doing Okay', level: 'Mild concern', message: 'You have some positive feelings but there may be room for more self-care.' };
                if (avgScore >= 0.5) return { category: 'Struggling a bit', level: 'Moderate concern', message: 'Your emotional wellbeing seems lower than ideal. Consider adding small daily practices that bring you comfort.' };
                return { category: 'Having a hard time', level: 'Significant concern', message: 'Your responses suggest you may be going through a difficult period. Please be gentle with yourself and consider reaching out for support.' };
            } else {
                if (avgScore < 0.5) return { category: 'Low concern', level: 'Minimal symptoms', message: 'Your responses suggest you are not experiencing significant difficulties in this area right now.' };
                if (avgScore < 1.0) return { category: 'Mild concern', level: 'Some symptoms', message: 'Some areas may deserve a little more attention. Consider using Haven\'s wellbeing tools or talking with someone you trust.' };
                if (avgScore < 2.0) return { category: 'Moderate concern', level: 'Noticeable symptoms', message: 'Your responses suggest you may be experiencing noticeable difficulties. It could be helpful to explore coping strategies or talk to a professional.' };
                if (avgScore < 2.5) return { category: 'High concern', level: 'Significant symptoms', message: 'Your responses suggest you are experiencing significant emotional difficulties. Consider speaking with a qualified mental-health professional.' };
                return { category: 'Very high concern', level: 'Severe symptoms', message: 'Your responses indicate you may be struggling intensely. Please reach out to a mental-health professional or a trusted person today.' };
            }
        }

        function getColorForScore(avgScore, isWellbeing = false) {
            if (isWellbeing) {
                if (avgScore >= 2.5) return '#4CAF50';
                if (avgScore >= 1.5) return '#8BC34A';
                if (avgScore >= 0.5) return '#FFC107';
                return '#F44336';
            } else {
                if (avgScore < 0.5) return '#4CAF50';
                if (avgScore < 1.0) return '#8BC34A';
                if (avgScore < 2.0) return '#FFC107';
                if (avgScore < 2.5) return '#FF9800';
                return '#F44336';
            }
        }

        // ===================== ASSESSMENT FLOW =====================
        function startAssessment(key) {
            currentAssessmentKey = key;
            currentQuestionIndex = 0;
            answers = [];
            selectedOption = null;
            showSection('questionnaire');
            renderQuestion();
        }

        function renderQuestion() {
            const assessment = assessments[currentAssessmentKey];
            const questions = assessment.questions;
            const q = questions[currentQuestionIndex];
            document.getElementById('assessment-name').textContent = assessment.name;
            document.getElementById('question-counter').textContent = `Question ${currentQuestionIndex + 1} / ${questions.length}`;
            document.getElementById('question-text').textContent = q.text;
            document.getElementById('progress-fill').style.width = `${(currentQuestionIndex / questions.length) * 100}%`;
            
            // Build answer options
            const optionsContainer = document.getElementById('answer-options');
            optionsContainer.innerHTML = '';
            selectedOption = answers[currentQuestionIndex] !== undefined ? answers[currentQuestionIndex] : null;
            
            q.options.forEach((opt, idx) => {
                const card = document.createElement('div');
                card.className = 'answer-card' + (selectedOption === idx ? ' selected' : '');
                card.textContent = opt;
                card.onclick = () => selectOption(idx);
                optionsContainer.appendChild(card);
            });
            
            // Update continue button state
            document.getElementById('continue-btn').disabled = (selectedOption === null);
            
            // Update back button visibility
            document.getElementById('back-btn').style.visibility = currentQuestionIndex === 0 ? 'hidden' : 'visible';
        }

        function selectOption(idx) {
            selectedOption = idx;
            answers[currentQuestionIndex] = idx;
            // Update UI
            const cards = document.querySelectorAll('.answer-card');
            cards.forEach((card, i) => {
                card.classList.toggle('selected', i === idx);
            });
            document.getElementById('continue-btn').disabled = false;
        }

        function nextQuestion() {
            if (selectedOption === null) return;
            const assessment = assessments[currentAssessmentKey];
            if (currentQuestionIndex < assessment.questions.length - 1) {
                currentQuestionIndex++;
                renderQuestion();
            } else {
                finishAssessment();
            }
        }

        function previousQuestion() {
            if (currentQuestionIndex > 0) {
                currentQuestionIndex--;
                renderQuestion();
            }
        }

        function finishAssessment() {
            const assessment = assessments[currentAssessmentKey];
            // Check for safety question (score >=2 on safety item)
            let safetyTriggered = false;
            assessment.questions.forEach((q, idx) => {
                if (q.safety && answers[idx] !== undefined && answers[idx] >= 2) {
                    safetyTriggered = true;
                }
            });
            if (safetyTriggered) {
                showSection('crisis');
                return;
            }
            // Calculate average score (0-3)
            const sum = answers.reduce((acc, val) => acc + val, 0);
            const avg = sum / answers.length;
            const isWellbeing = currentAssessmentKey === 'wellbeing';
            const result = getScoreCategory(avg, isWellbeing);
            const color = getColorForScore(avg, isWellbeing);
            
            // Display overview
            document.getElementById('result-score-circle').textContent = avg.toFixed(1);
            document.getElementById('result-score-circle').style.color = color;
            document.getElementById('result-category').textContent = result.category;
            document.getElementById('result-message').textContent = result.message;
            
            // Domain results: show a single bar for this assessment
            const domainContainer = document.getElementById('domain-results');
            domainContainer.innerHTML = '';
            const domainItem = document.createElement('div');
            domainItem.className = 'domain-item';
            domainItem.innerHTML = `
                <div class="domain-header">
                    <span class="domain-name">${assessment.name}</span>
                    <span class="domain-level">${result.level}</span>
                </div>
                <div class="domain-bar">
                    <div class="domain-fill" style="width: ${(avg/3)*100}%; background: ${color};"></div>
                </div>
            `;
            domainContainer.appendChild(domainItem);
            
            // Build 7-day plan
            const weekPlan = document.getElementById('week-plan');
            const days = [
                { day: 'MON', text: '5 min breathing', icon: '🌿' },
                { day: 'TUE', text: 'Journal check-in', icon: '📝' },
                { day: 'WED', text: 'Take a short walk', icon: '🚶' },
                { day: 'THU', text: 'Talk to someone', icon: '💬' },
                { day: 'FRI', text: 'Screen-free evening', icon: '🌙' },
                { day: 'SAT', text: 'Do something you enjoy', icon: '🎵' },
                { day: 'SUN', text: 'Check in with yourself', icon: '❤️' }
            ];
            weekPlan.innerHTML = '';
            days.forEach(day => {
                const div = document.createElement('div');
                div.className = 'day-item';
                div.innerHTML = `
                    <div class="day-checkbox" onclick="toggleDay(this)"></div>
                    <span>${day.day}  ${day.icon}  ${day.text}</span>
                `;
                weekPlan.appendChild(div);
            });
            
            showSection('results');
        }

        function toggleDay(el) {
            el.classList.toggle('completed');
        }

        // ===================== PAUSE / BREATHING =====================
        let pauseTimer;
        let secondsLeft = 30;

        function openPause() {
            document.getElementById('pause-overlay').classList.add('active');
            secondsLeft = 30;
            document.getElementById('breath-timer').textContent = `${secondsLeft} seconds remaining`;
            clearInterval(pauseTimer);
            pauseTimer = setInterval(() => {
                secondsLeft--;
                if (secondsLeft <= 0) {
                    clearInterval(pauseTimer);
                    closePause();
                } else {
                    document.getElementById('breath-timer').textContent = `${secondsLeft} seconds remaining`;
                }
            }, 1000);
            // Start breathing animation
            const circle = document.querySelector('.breathing-circle');
            circle.style.animation = 'none';
            void circle.offsetWidth; // trigger reflow
            circle.style.animation = 'breathe 10s infinite ease-in-out';
            updateBreathText();
        }

        function updateBreathText() {
            const textEl = document.getElementById('breath-text');
            // Just alternate static text; animation is CSS driven
            setTimeout(() => textEl.textContent = 'Breathe in', 0);
            setTimeout(() => textEl.textContent = 'Breathe out', 4000);
        }

        function closePause() {
            clearInterval(pauseTimer);
            document.getElementById('pause-overlay').classList.remove('active');
            // Resume questionnaire (state is preserved)
        }

        // Initial render
        showSection('hero');
    </script>
</body>
</html>