<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Verify current password
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $message = '<div class="alert alert-danger">Current password is incorrect.</div>';
    } elseif (strlen($new) < 6) {
        $message = '<div class="alert alert-danger">New password must be at least 6 characters.</div>';
    } elseif ($new !== $confirm) {
        $message = '<div class="alert alert-danger">Passwords do not match.</div>';
    } else {
        $new_hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$new_hash, $user_id]);
        $message = '<div class="alert alert-success">Password updated successfully!</div>';
    }
}

$pageTitle = 'Settings';
include 'includes/header.php';
?>
<div class="glass-card p-4">
    <h4>⚙ Security Settings</h4>
    <?php if ($message) echo $message; ?>
    <h5>Change Password</h5>
    <form method="POST">
        <div class="mb-3">
            <label>Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>New Password</label>
            <input type="password" name="new_password" class="form-control" required minlength="6">
        </div>
        <div class="mb-3">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
        </div>
        <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
        <a href="dashboard.php" class="btn btn-secondary">Back</a>
    </form>
    <hr>
    <h5>Privacy</h5>
    <p>Your anonymous name: <strong><?= escape(getAnonymousName($user_id, $pdo)) ?></strong></p>
    <p>Email: <?= escape($user['email']) ?></p>
    <a href="profile.php" class="btn btn-outline-primary">Edit Profile</a>
</div>
<?php include 'includes/footer.php'; ?>