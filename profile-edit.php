<?php
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();
if (!$me) { header('Location: logout.php'); exit; }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name'] ?? '');
    $anon_name   = trim($_POST['anonymous_name'] ?? '');
    $bio         = trim($_POST['bio'] ?? '');
    $city        = trim($_POST['city'] ?? '');
    $country     = trim($_POST['country'] ?? '');
    $occupation  = trim($_POST['occupation'] ?? '');
    $education   = trim($_POST['education'] ?? '');
    $gender      = trim($_POST['gender'] ?? '');
    $language    = trim($_POST['language'] ?? 'en');
    $avatar_type = ($_POST['avatar_type'] ?? 'color') === 'icon' ? 'icon' : 'color';
    $avatar_icon = trim($_POST['avatar_icon'] ?? '');
    $avatar_color = trim($_POST['avatar_color'] ?? '#5e7564');

    if (mb_strlen($anon_name) < 2) $errors[] = 'Display name must be at least 2 characters.';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $avatar_color)) $avatar_color = '#5e7564';
    if ($avatar_type === 'icon' && $avatar_icon === '') $avatar_type = 'color';

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, anonymous_name=?, bio=?, city=?, country=?, occupation=?, education=?, gender=?, language=?, avatar_type=?, avatar_icon=?, avatar_color=? WHERE id=?");
        $stmt->execute([$full_name, $anon_name, $bio, $city, $country, $occupation, $education, $gender, $language, $avatar_type, $avatar_icon, $avatar_color, $user_id]);

        // Keep the legacy `profiles` table in sync — getAnonymousName() and
        // several pages (feed, search, index, profile) read anonymous_name
        // and avatar_color from here rather than from `users`.
        $stmt = $pdo->prepare("INSERT INTO profiles (user_id, anonymous_name, avatar_color) VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE anonymous_name = VALUES(anonymous_name), avatar_color = VALUES(avatar_color)");
        $stmt->execute([$user_id, $anon_name, $avatar_color]);

        $success = true;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $me = $stmt->fetch();
    }
}

$presets = havenAvatarPresets();
$pageTitle = 'Edit Profile';
include 'includes/header.php';
?>
<style>
.pe-wrap { max-width: 640px; margin: 0 auto; }
.pe-avatar-preview { width: 90px; height: 90px; border-radius: 26px; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 2.2rem; margin: 0 auto 14px; box-shadow: 0 10px 30px rgba(64,77,67,.15); }
.pe-preset-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin-bottom: 14px; }
.pe-preset { border: 2px solid transparent; border-radius: 14px; padding: 10px 0; text-align: center; font-size: 1.4rem; cursor: pointer; }
.pe-preset.selected { border-color: var(--sage-dark); background: var(--sage-soft); }
.pe-color-row { display: flex; gap: 8px; align-items: center; margin-bottom: 14px; }
.pe-toggle { display: flex; gap: 8px; margin-bottom: 14px; }
.pe-toggle button { flex: 1; padding: 8px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,.5); font-weight: 600; font-size: .85rem; }
.pe-toggle button.active { background: var(--sage-dark); color: #fff; border-color: var(--sage-dark); }
</style>

<div class="pe-wrap">
    <h3 class="mb-3"><i class="bi bi-person-gear"></i> Edit Profile</h3>

    <?php if ($success): ?><div class="alert alert-success">Profile updated.</div><?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= escape($e) ?></div><?php endforeach; ?>

    <div class="glass-card p-4 mb-4 text-center">
        <div class="pe-avatar-preview" id="avatarPreview" style="background:<?= escape($me['avatar_color'] ?: '#5e7564') ?>">
            <?= ($me['avatar_type'] === 'icon' && $me['avatar_icon']) ? escape($me['avatar_icon']) : mb_strtoupper(mb_substr($me['anonymous_name'] ?: 'H', 0, 1)) ?>
        </div>
        <p class="text-muted small mb-0">This is how you'll appear on posts, comments and reactions.</p>
    </div>

    <form method="POST" class="glass-card p-4">
        <input type="hidden" name="avatar_type" id="avatarTypeInput" value="<?= escape($me['avatar_type'] ?: 'color') ?>">
        <input type="hidden" name="avatar_icon" id="avatarIconInput" value="<?= escape($me['avatar_icon'] ?: '') ?>">

        <h6 class="mb-2">Avatar</h6>
        <div class="pe-toggle">
            <button type="button" id="modeColorBtn" class="<?= $me['avatar_type'] !== 'icon' ? 'active' : '' ?>">Color only</button>
            <button type="button" id="modeIconBtn" class="<?= $me['avatar_type'] === 'icon' ? 'active' : '' ?>">Icon avatar</button>
        </div>
        <div class="pe-preset-grid" id="presetGrid">
            <?php foreach ($presets as $p): ?>
                <div class="pe-preset <?= ($me['avatar_type'] === 'icon' && $me['avatar_icon'] === $p['icon']) ? 'selected' : '' ?>"
                     data-icon="<?= escape($p['icon']) ?>" data-color="<?= escape($p['color']) ?>"><?= $p['icon'] ?></div>
            <?php endforeach; ?>
        </div>
        <div class="pe-color-row">
            <label class="small mb-0">Custom color:</label>
            <input type="color" name="avatar_color" id="avatarColorInput" value="<?= escape($me['avatar_color'] ?: '#5e7564') ?>">
        </div>

        <hr>
        <h6 class="mb-2">About you</h6>
        <div class="row g-3 mb-2">
            <div class="col-md-6">
                <label class="form-label small">Display name (shown publicly)</label>
                <div class="d-flex gap-2">
                    <input type="text" name="anonymous_name" id="anonNameEditInput" class="form-control" value="<?= escape($me['anonymous_name']) ?>" required minlength="2">
                    <button type="button" id="regenNameBtn" class="btn btn-outline-secondary" title="Suggest a new pseudonym">🎲</button>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Full name (private, admin only)</label>
                <input type="text" name="full_name" class="form-control" value="<?= escape($me['full_name']) ?>">
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label small">Bio</label>
            <textarea name="bio" class="form-control" rows="3" maxlength="300"><?= escape($me['bio']) ?></textarea>
        </div>
        <div class="row g-3 mb-2">
            <div class="col-md-6"><label class="form-label small">City</label><input type="text" name="city" class="form-control" value="<?= escape($me['city']) ?>"></div>
            <div class="col-md-6"><label class="form-label small">Country</label><input type="text" name="country" class="form-control" value="<?= escape($me['country']) ?>"></div>
        </div>
        <div class="row g-3 mb-2">
            <div class="col-md-6"><label class="form-label small">Occupation</label><input type="text" name="occupation" class="form-control" value="<?= escape($me['occupation']) ?>"></div>
            <div class="col-md-6"><label class="form-label small">Education</label><input type="text" name="education" class="form-control" value="<?= escape($me['education']) ?>"></div>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label small">Gender</label>
                <select name="gender" class="form-select">
                    <option value="" <?= !$me['gender'] ? 'selected' : '' ?>>Prefer not to say</option>
                    <option value="female" <?= $me['gender']=='female'?'selected':'' ?>>Female</option>
                    <option value="male" <?= $me['gender']=='male'?'selected':'' ?>>Male</option>
                    <option value="other" <?= $me['gender']=='other'?'selected':'' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small">Language</label>
                <select name="language" class="form-select">
                    <option value="en" <?= $me['language']=='en'?'selected':'' ?>>English</option>
                    <option value="bn" <?= $me['language']=='bn'?'selected':'' ?>>বাংলা</option>
                    <option value="es" <?= $me['language']=='es'?'selected':'' ?>>Español</option>
                </select>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Save changes</button>
            <a href="profile.php?id=<?= $user_id ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
    <div class="text-center mt-3">
        <a href="settings.php" class="small text-muted">Account &amp; security settings →</a>
    </div>
</div>

<script>
const preview = document.getElementById('avatarPreview');
const typeInput = document.getElementById('avatarTypeInput');
const iconInput = document.getElementById('avatarIconInput');
const colorInput = document.getElementById('avatarColorInput');
const modeColorBtn = document.getElementById('modeColorBtn');
const modeIconBtn = document.getElementById('modeIconBtn');
const grid = document.getElementById('presetGrid');

function updatePreview() {
    preview.style.background = colorInput.value;
    if (typeInput.value === 'icon' && iconInput.value) {
        preview.textContent = iconInput.value;
    } else {
        preview.textContent = <?= json_encode(mb_strtoupper(mb_substr($me['anonymous_name'] ?: 'H', 0, 1))) ?>;
    }
}
modeColorBtn.addEventListener('click', () => {
    typeInput.value = 'color'; iconInput.value = '';
    modeColorBtn.classList.add('active'); modeIconBtn.classList.remove('active');
    grid.querySelectorAll('.pe-preset').forEach(p => p.classList.remove('selected'));
    updatePreview();
});
modeIconBtn.addEventListener('click', () => {
    typeInput.value = 'icon';
    modeIconBtn.classList.add('active'); modeColorBtn.classList.remove('active');
});
grid.querySelectorAll('.pe-preset').forEach(p => {
    p.addEventListener('click', () => {
        grid.querySelectorAll('.pe-preset').forEach(x => x.classList.remove('selected'));
        p.classList.add('selected');
        typeInput.value = 'icon';
        iconInput.value = p.dataset.icon;
        colorInput.value = p.dataset.color;
        modeIconBtn.classList.add('active'); modeColorBtn.classList.remove('active');
        updatePreview();
    });
});
colorInput.addEventListener('input', updatePreview);
document.getElementById('regenNameBtn')?.addEventListener('click', async function() {
    this.disabled = true;
    try {
        const r = await fetch('api/suggest-name.php');
        const d = await r.json();
        if (d.success) document.getElementById('anonNameEditInput').value = d.name;
    } catch (e) {}
    this.disabled = false;
});
</script>
<?php include 'includes/footer.php'; ?>
