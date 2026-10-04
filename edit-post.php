<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$post_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$post_id) {
    header('Location: dashboard.php?tab=posts');
    exit;
}

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? AND user_id = ?");
$stmt->execute([$post_id, $user_id]);
$post = $stmt->fetch();
if (!$post) {
    header('Location: dashboard.php?tab=posts');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $mood = $_POST['mood'] ?? '';
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $location = trim($_POST['location'] ?? '');

    if (empty($title) || empty($content)) {
        $error = "Title and content are required.";
    } else {
        $stmt = $pdo->prepare("UPDATE posts SET title=?, content=?, mood=?, category=?, tags=?, location=? WHERE id=?");
        $stmt->execute([$title, $content, $mood, $category, $tags, $location, $post_id]);
        header("Location: post.php?id=$post_id");
        exit;
    }
}

$pageTitle = 'Edit Post';
include 'includes/header.php';
?>
<div class="glass-card p-4">
    <h2>Edit Post</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label>Title</label>
            <input type="text" name="title" class="form-control" value="<?= escape($post['title']) ?>" required>
        </div>
        <div class="mb-3">
            <label>Content</label>
            <textarea name="content" rows="6" class="form-control" required><?= escape($post['content']) ?></textarea>
        </div>
        <div class="mb-3">
            <label>Mood</label>
            <select name="mood" class="form-select">
                <option value="">Select mood</option>
                <?php
                $mood_options = ['happy'=>'😊 Happy','okay'=>'🙂 Okay','sad'=>'😔 Sad','stressed'=>'😰 Stressed','angry'=>'😡 Angry','tired'=>'😴 Tired'];
                foreach ($mood_options as $key => $label):
                    $selected = ($post['mood'] == $key) ? 'selected' : '';
                ?>
                    <option value="<?= $key ?>" <?= $selected ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Category</label>
            <input type="text" name="category" class="form-control" value="<?= escape($post['category']) ?>" placeholder="e.g., Education">
        </div>
        <div class="mb-3">
            <label>Tags (comma separated)</label>
            <input type="text" name="tags" class="form-control" value="<?= escape($post['tags']) ?>" placeholder="exam, stress">
        </div>
        <div class="mb-3">
            <label>Location</label>
            <input type="text" name="location" class="form-control" value="<?= escape($post['location']) ?>" placeholder="City or country">
        </div>
        <button type="submit" class="btn btn-primary">Update Post</button>
        <a href="post.php?id=<?= $post_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php include 'includes/footer.php'; ?>