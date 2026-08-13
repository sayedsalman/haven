<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid credentials.";
    }
}
include 'includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="glass-card p-4">
            <h2 class="text-center" style=justify>Login</h2>
            <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
            <form method="POST">
                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <p class="mt-3 text-center"><a href="forgot-password.php">Forgot password?</a> · <a href="register.php">Register</a></p>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
