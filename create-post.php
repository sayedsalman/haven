<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $mood = $_POST['mood'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $location = trim($_POST['location'] ?? '');

    $media_url = null; $media_type = null;
    if (!empty($_FILES['media']['name'])) {
        $allowed = ['image' => ['jpg','jpeg','png','gif','webp'], 'video' => ['mp4','webm','mov'], 'audio' => ['mp3','wav','ogg','m4a']];
        $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        foreach ($allowed as $type => $exts) {
            if (in_array($ext, $exts, true)) { $media_type = $type; break; }
        }
        if (!$media_type) {
            $error = 'Unsupported file type. Please upload an image, video, or audio file.';
        } elseif ($_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The file could not be uploaded. It may be too large for this server.';
        } else {
            $uploadDir = __DIR__ . '/uploads/community/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $fname = uniqid('post_') . '.' . $ext;
            if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $fname)) {
                $media_url = 'uploads/community/' . $fname;
            } else {
                $error = 'The file could not be saved. Please try again.';
            }
        }
    }

    if (empty($content)) {
        $error = $error ?: "Please write something before posting.";
    } elseif ($error) {
        // media error already set above
    } else {
        if ($title === '') {
            $title = mb_substr(trim(preg_replace('/\s+/', ' ', $content)), 0, 60);
        }
        // Insert post
        $stmt = $pdo->prepare("INSERT INTO posts (user_id, title, content, mood, category, tags, location, media_url, media_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_ai')");
        $stmt->execute([$user_id, $title, $content, $mood, $category, $tags, $location, $media_url, $media_type]);
        $post_id = $pdo->lastInsertId();

        // On-device TFLite emotion scores (optional; never blocks posting)
        try {
            require_once __DIR__ . '/includes/EmotionML.php';
            EmotionML::store($pdo, (int)$post_id, (int)$user_id, $_POST['ml_scores'] ?? null, $content);
        } catch (Throwable $e) { error_log('[Haven] ML store: ' . $e->getMessage()); }

        // Run MindShield + MindGuide synchronously so the post gets a final
        // status (published/flagged) immediately, instead of relying on a
        // queued job that nothing currently processes.
        $ai_ran = false;
        $ai_manager_path = __DIR__ . '/ai/AIManager.php';
        if (file_exists($ai_manager_path)) {
            require_once $ai_manager_path;
            if (class_exists('AIManager')) {
                try {
                    $manager = new AIManager();
                    $manager->analyzePost($post_id);
                    $ai_ran = true;
                } catch (Throwable $e) {
                    error_log('[Haven] AIManager::analyzePost failed for post ' . $post_id . ': ' . $e->getMessage());
                }
            }
        }
        if (!$ai_ran) {
            // AI pipeline unavailable/failed — publish anyway so the post
            // isn't invisible forever, and queue a retry for a background
            // worker to pick up later.
            $pdo->prepare("UPDATE posts SET status = 'published' WHERE id = ? AND status = 'pending_ai'")->execute([$post_id]);
            try {
                $pdo->prepare("INSERT INTO jobs (job_class, payload, available_at) VALUES ('AnalyzePostJob', ?, NOW())")
                    ->execute([json_encode(['post_id' => $post_id])]);
            } catch (Throwable $e) { /* jobs table optional; ignore if absent */ }
        }

        // Redirect to post
        header("Location: post.php?id=$post_id");
        exit;
    }
}

$pageTitle = 'Create Post';
include 'includes/header.php';
?>
<div class="glass-card p-4">
    <h2>Share Your Thoughts</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label>Title (optional)</label>
            <input type="text" name="title" class="form-control">
        </div>
        <div class="mb-3">
            <label>Content</label>
            <textarea name="content" rows="6" class="form-control" required data-emotion-ml></textarea>
        </div>
        <div class="mb-3">
            <label>Attach a photo, video, or audio clip (optional)</label>
            <input type="file" name="media" id="mediaFileInput" class="form-control" accept="image/*,video/*,audio/*">
            <div id="mediaFilePreview" class="mt-2"></div>
        </div>
        <div class="mb-3">
            <label>Mood</label>
            <select name="mood" class="form-select">
                <option value="">Select mood</option>
                <option value="happy">😊 Happy</option>
                <option value="okay">🙂 Okay</option>
                <option value="sad">😔 Sad</option>
                <option value="stressed">😰 Stressed</option>
                <option value="angry">😡 Angry</option>
                <option value="tired">😴 Tired</option>
            </select>
        </div>
        <div class="mb-3">
            <label>Category</label>
            <input type="text" name="category" class="form-control" placeholder="e.g., Education, Relationships">
        </div>
        <div class="mb-3">
            <label>Tags (comma separated)</label>
            <input type="text" name="tags" class="form-control" placeholder="exam, stress, anxiety">
        </div>
        <div class="mb-3">
            <label>Location (optional)</label>
            <input type="text" name="location" class="form-control" placeholder="City or country">
        </div>
        <button type="submit" class="btn btn-primary">Publish</button>
        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<script>
document.getElementById('mediaFileInput').addEventListener('change', function() {
    const f = this.files[0];
    const box = document.getElementById('mediaFilePreview');
    if (!f) { box.innerHTML = ''; return; }
    const url = URL.createObjectURL(f);
    if (f.type.startsWith('image/')) {
        box.innerHTML = `<img src="${url}" style="max-width:100%;max-height:220px;border-radius:12px;">`;
    } else if (f.type.startsWith('video/')) {
        box.innerHTML = `<video src="${url}" controls style="max-width:100%;max-height:220px;border-radius:12px;"></video>`;
    } else if (f.type.startsWith('audio/')) {
        box.innerHTML = `<audio src="${url}" controls style="width:100%;"></audio>`;
    } else {
        box.innerHTML = `<span class="text-muted small">Attached: ${f.name}</span>`;
    }
});
</script>
<script src="assets/js/haven-emotion.js?v=3"></script>
<?php include 'includes/footer.php'; ?>