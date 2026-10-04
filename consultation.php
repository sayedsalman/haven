<?php
// ============================================================
// consultation.php — 1:1 support requests between a user and a
// volunteer. AJAX endpoints live in this file (action=... POST/GET)
// so the chat can work without leaving the page or touching
// volunteer.php (which is role-gated to volunteers).
// ============================================================
include 'includes/config.php';
include 'includes/database.php';
include 'includes/auth.php';
requireLogin();
include 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$csrf = $_SESSION['haven_csrf'] ?? ($_SESSION['haven_csrf'] = bin2hex(random_bytes(32)));

function jr_c($a, $s = 200) {
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}

// ------------------------------------------------------------
// AJAX endpoints
// ------------------------------------------------------------
if (isset($_GET['ajax']) || isset($_POST['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'new_request') {
        $msg = trim($_POST['message'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        if (!in_array($priority, ['low', 'medium', 'high', 'emergency'], true)) $priority = 'medium';
        $post_id = !empty($_POST['post_id']) ? intval($_POST['post_id']) : null;
        if ($post_id) {
            $chk = $pdo->prepare("SELECT id FROM posts WHERE id = ?");
            $chk->execute([$post_id]);
            if (!$chk->fetch()) $post_id = null;
        }
        if ($msg === '') jr_c(['success' => false, 'error' => 'Please describe what support you need.']);
        $stmt = $pdo->prepare("INSERT INTO consultation_requests (user_id, post_id, message, status, priority) VALUES (?, ?, ?, 'queued', ?)");
        $stmt->execute([$user_id, $post_id, $msg, $priority]);
        $newId = $pdo->lastInsertId();
        createNotification($user_id, 'consultation', 'Your consultation request has been submitted. A volunteer will reach out soon.', 'consultation.php?tab=active', $pdo);
        jr_c(['success' => true, 'id' => $newId]);
    }

    if ($action === 'send_message') {
        $case_id = intval($_POST['case_id'] ?? 0);
        $message = trim($_POST['message'] ?? '');
        if ($message === '') jr_c(['success' => false, 'error' => 'Message required']);
        $stmt = $pdo->prepare("SELECT user_id, volunteer_id, status FROM consultation_requests WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
        if (!$case || (int)$case['user_id'] !== (int)$user_id) jr_c(['success' => false, 'error' => 'Unauthorized']);
        if ($case['status'] === 'closed') jr_c(['success' => false, 'error' => 'This consultation has been closed.']);
        $ins = $pdo->prepare("INSERT INTO consultation_messages (request_id, sender_id, message) VALUES (?, ?, ?)");
        $ins->execute([$case_id, $user_id, $message]);
        if ($case['volunteer_id']) {
            createNotification($case['volunteer_id'], 'consultation_message', 'New message from a user you are supporting.', 'volunteer.php?tab=cases&case_id=' . $case_id, $pdo);
        }
        jr_c(['success' => true, 'message_id' => $pdo->lastInsertId()]);
    }

    if ($action === 'get_messages') {
        $case_id = intval($_GET['case_id'] ?? 0);
        $last_id = intval($_GET['last_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id, user_id, volunteer_id, status FROM consultation_requests WHERE id = ?");
        $stmt->execute([$case_id]);
        $case = $stmt->fetch();
        if (!$case || (int)$case['user_id'] !== (int)$user_id) jr_c(['success' => false, 'error' => 'Unauthorized']);
        $stmt = $pdo->prepare("SELECT cm.*, u.role FROM consultation_messages cm JOIN users u ON cm.sender_id = u.id WHERE cm.request_id = ? AND cm.id > ? ORDER BY cm.created_at ASC");
        $stmt->execute([$case_id, $last_id]);
        $messages = $stmt->fetchAll();
        jr_c(['success' => true, 'messages' => $messages, 'status' => $case['status']]);
    }

    if ($action === 'submit_feedback') {
        $case_id = intval($_POST['case_id'] ?? 0);
        $rating = max(1, min(5, intval($_POST['rating'] ?? 0)));
        $comment = trim($_POST['comment'] ?? '');
        $stmt = $pdo->prepare("UPDATE consultation_requests SET feedback_rating = ?, feedback_comment = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$rating, $comment, $case_id, $user_id]);
        jr_c(['success' => true]);
    }

    jr_c(['success' => false, 'error' => 'Unknown action.'], 400);
}

$tab = $_GET['tab'] ?? 'new';

$stmt = $pdo->prepare("SELECT cr.*, u.anonymous_name AS volunteer_name, u.avatar_color AS volunteer_color
                        FROM consultation_requests cr
                        LEFT JOIN users u ON cr.volunteer_id = u.id
                        WHERE cr.user_id = ? ORDER BY cr.created_at DESC");
$stmt->execute([$user_id]);
$all_requests = $stmt->fetchAll();

$active_list = array_values(array_filter($all_requests, fn($r) => in_array($r['status'], ['queued', 'active'], true)));
$history_list = array_values(array_filter($all_requests, fn($r) => $r['status'] === 'closed'));

$open_case_id = isset($_GET['id']) ? intval($_GET['id']) : ($active_list[0]['id'] ?? null);
$open_case = null;
if ($open_case_id) {
    foreach ($all_requests as $r) { if ((int)$r['id'] === $open_case_id) { $open_case = $r; break; } }
}

$pageTitle = 'Consultation';
include 'includes/header.php';
?>
<style>
.consult-wrap { max-width: 980px; margin: 0 auto; }
.consult-tabs { display: flex; gap: 6px; margin-bottom: 20px; background: rgba(255,255,255,.5); padding: 6px; border-radius: 16px; width: fit-content; }
.consult-tabs a { padding: 8px 18px; border-radius: 12px; font-weight: 600; font-size: .9rem; color: var(--muted); text-decoration: none; }
.consult-tabs a.active { background: var(--sage-dark); color: #fff; }
.priority-pill { font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .03em; }
.priority-low { background: var(--sage-soft); color: var(--sage-dark); }
.priority-medium { background: #f5ded2; color: #8a5a35; }
.priority-high { background: #f5d0c8; color: #a8402f; }
.priority-emergency { background: #c96a63; color: #fff; }
.status-pill { font-size: .68rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
.status-queued { background: var(--cream-2); color: var(--muted); }
.status-active { background: var(--sage-soft); color: var(--sage-dark); }
.status-closed { background: #e4e4e4; color: #666; }
.case-row { display: flex; align-items: center; gap: 14px; padding: 16px; border-radius: 18px; cursor: pointer; transition: .2s; }
.case-row:hover { background: rgba(135,157,139,.08); }
.case-row.selected { background: rgba(135,157,139,.14); }
.case-avatar { width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; color: #fff; font-weight: 700; flex-shrink: 0; }
.chat-shell { display: grid; grid-template-columns: 300px 1fr; gap: 16px; align-items: start; }
@media (max-width: 800px) { .chat-shell { grid-template-columns: 1fr; } .case-list-panel.has-open { display: none; } }
.chat-panel { border-radius: 24px; overflow: hidden; display: flex; flex-direction: column; height: 560px; }
.chat-header { padding: 16px 18px; border-bottom: 1px solid var(--line); display: flex; align-items: center; gap: 12px; }
.chat-messages { flex: 1; overflow-y: auto; padding: 18px; display: flex; flex-direction: column; gap: 10px; background: rgba(255,255,255,.25); }
.bubble-row { display: flex; }
.bubble-row.mine { justify-content: flex-end; }
.bubble { max-width: 75%; padding: 10px 14px; border-radius: 16px; font-size: .88rem; line-height: 1.5; }
.bubble.them { background: #fff; border: 1px solid var(--line); border-bottom-left-radius: 4px; }
.bubble.mine { background: var(--sage-dark); color: #fff; border-bottom-right-radius: 4px; }
.bubble .btime { display: block; font-size: .65rem; opacity: .6; margin-top: 4px; }
.chat-input-row { display: flex; gap: 8px; padding: 12px; border-top: 1px solid var(--line); }
.chat-input-row input { flex: 1; border-radius: 14px; border: 1px solid var(--line); padding: 10px 14px; background: rgba(255,255,255,.7); }
.empty-chat { display: flex; align-items: center; justify-content: center; height: 100%; color: var(--muted); font-size: .9rem; text-align: center; padding: 30px; }
.rate-stars { font-size: 1.6rem; cursor: pointer; letter-spacing: 4px; }
.rate-stars span { opacity: .3; transition: .15s; }
.rate-stars span.on { opacity: 1; }
</style>

<div class="consult-wrap">
    <h3 class="mb-1"><i class="bi bi-heart-pulse"></i> Consultation</h3>
    <p class="text-muted mb-3">Talk privately with a trained Haven volunteer, at your own pace.</p>

    <div class="consult-tabs">
        <a href="?tab=new" class="<?= $tab == 'new' ? 'active' : '' ?>">New Request</a>
        <a href="?tab=active" class="<?= $tab == 'active' ? 'active' : '' ?>">Active (<?= count($active_list) ?>)</a>
        <a href="?tab=history" class="<?= $tab == 'history' ? 'active' : '' ?>">History</a>
    </div>

    <?php if ($tab === 'new'): ?>
        <div class="glass-card p-4" style="max-width:640px;">
            <h5 class="mb-3">Describe what's going on</h5>
            <?php if (!empty($_GET['post_id'])): ?>
                <div class="alert alert-warning small">We noticed this may relate to a difficult moment. A volunteer will read this before reaching out — you're not alone in this.</div>
            <?php endif; ?>
            <form id="newRequestForm">
                <div class="mb-3">
                    <textarea name="message" class="form-control" rows="5" placeholder="Share as much or as little as feels okay. A volunteer will read this before reaching out." required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">How urgent does this feel?</label>
                    <select name="priority" class="form-select">
                        <option value="low">Low — just want to talk</option>
                        <option value="medium" <?= empty($_GET['post_id']) ? 'selected' : '' ?>>Medium — could use support soon</option>
                        <option value="high" <?= !empty($_GET['post_id']) ? 'selected' : '' ?>>High — struggling right now</option>
                        <option value="emergency">Emergency — I need help urgently</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">Related post ID (optional)</label>
                    <input type="number" name="post_id" class="form-control" value="<?= (int)($_GET['post_id'] ?? 0) ?: '' ?>" placeholder="Leave blank if this isn't about a specific post">
                </div>
                <button type="submit" class="btn btn-primary w-100">Submit request</button>
            </form>
            <div id="newRequestMsg" class="mt-3"></div>
            <div class="alert alert-danger mt-3 mb-0 small">
                <i class="bi bi-exclamation-triangle"></i> If you are in immediate danger or crisis, please contact your local emergency number right away — this is not a monitored 24/7 emergency line.
            </div>
        </div>

    <?php elseif ($tab === 'active'): ?>
        <?php if (empty($active_list)): ?>
            <div class="glass-card p-4 text-center text-muted">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                No active consultations. <a href="?tab=new">Start a new request</a>.
            </div>
        <?php else: ?>
            <div class="chat-shell">
                <div class="glass-card p-2 case-list-panel <?= $open_case ? 'has-open' : '' ?>">
                    <?php foreach ($active_list as $r): $color = $r['volunteer_color'] ?: '#879d8b'; ?>
                        <a href="?tab=active&id=<?= $r['id'] ?>" class="case-row text-decoration-none text-dark <?= ($open_case && $open_case['id'] == $r['id']) ? 'selected' : '' ?>">
                            <div class="case-avatar" style="background:<?= escape($color) ?>"><i class="bi bi-<?= $r['volunteer_id'] ? 'person-check' : 'hourglass-split' ?>"></i></div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex align-items-center gap-2">
                                    <strong style="font-size:.85rem;"><?= $r['volunteer_id'] ? escape($r['volunteer_name'] ?: 'Volunteer') : 'Waiting…' ?></strong>
                                    <span class="priority-pill priority-<?= $r['priority'] ?>"><?= $r['priority'] ?></span>
                                </div>
                                <small class="text-muted text-truncate d-block"><?= escape(mb_substr($r['message'], 0, 40)) ?></small>
                            </div>
                            <span class="status-pill status-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="glass-card chat-panel">
                    <?php if (!$open_case): ?>
                        <div class="empty-chat">Select a conversation on the left to open it.</div>
                    <?php else: ?>
                        <div class="chat-header">
                            <div class="case-avatar" style="background:<?= escape($open_case['volunteer_color'] ?: '#879d8b') ?>"><i class="bi bi-person-check"></i></div>
                            <div>
                                <strong><?= $open_case['volunteer_id'] ? escape($open_case['volunteer_name'] ?: 'Volunteer') : 'Waiting for a volunteer' ?></strong>
                                <div><span class="status-pill status-<?= $open_case['status'] ?>"><?= ucfirst($open_case['status']) ?></span></div>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages" data-case-id="<?= $open_case['id'] ?>" data-last-id="0"></div>
                        <?php if ($open_case['volunteer_id']): ?>
                        <form class="chat-input-row" id="chatSendForm">
                            <input type="text" id="chatInput" placeholder="Type a message…" autocomplete="off" required>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>
                        </form>
                        <?php else: ?>
                        <div class="p-3 text-center text-muted small">
                            <div class="spinner-border spinner-border-sm text-secondary mb-2" role="status"></div><br>
                            Finding an available volunteer for you… you'll be notified the moment someone picks up your case.
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($tab === 'history'): ?>
        <?php if (empty($history_list)): ?>
            <div class="glass-card p-4 text-center text-muted">No completed consultations yet.</div>
        <?php else: ?>
            <?php foreach ($history_list as $r): ?>
                <div class="glass-card p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= escape($r['volunteer_name'] ?: 'Volunteer') ?></strong>
                            <span class="text-muted small"> · closed <?= date('M j, Y', strtotime($r['closed_at'] ?: $r['created_at'])) ?></span>
                        </div>
                        <span class="status-pill status-closed">Closed</span>
                    </div>
                    <p class="mb-2 mt-2 small"><?= nl2br(escape(mb_substr($r['message'], 0, 200))) ?></p>
                    <?php if ($r['feedback_rating']): ?>
                        <div class="small text-warning">
                            <?php for ($i = 1; $i <= 5; $i++): ?><i class="bi bi-star<?= $i <= $r['feedback_rating'] ? '-fill' : '' ?>"></i><?php endfor; ?>
                            <?php if ($r['feedback_comment']): ?><span class="text-muted ms-2">"<?= escape($r['feedback_comment']) ?>"</span><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <button class="btn btn-sm btn-outline-primary" onclick="openRateModal(<?= $r['id'] ?>)"><i class="bi bi-star"></i> Rate this consultation</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Rate modal -->
<div class="modal fade" id="rateModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content p-3">
            <div class="modal-header border-0"><h5 class="modal-title">Rate this consultation</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body text-center">
                <div class="rate-stars mb-3" id="rateStars">
                    <span data-v="1">★</span><span data-v="2">★</span><span data-v="3">★</span><span data-v="4">★</span><span data-v="5">★</span>
                </div>
                <textarea id="rateComment" class="form-control" rows="3" placeholder="Optional feedback for the Haven team…"></textarea>
            </div>
            <div class="modal-footer border-0">
                <button class="btn btn-primary w-100" onclick="submitRating()">Submit rating</button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
let rateCaseId = null, rateValue = 0;

document.getElementById('newRequestForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target); fd.append('action', 'new_request');
    const box = document.getElementById('newRequestMsg');
    try {
        const r = await fetch('consultation.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) {
            box.innerHTML = '<div class="alert alert-success">Request submitted — a volunteer will reach out.</div>';
            setTimeout(() => location.href = '?tab=active', 900);
        } else {
            box.innerHTML = '<div class="alert alert-danger">' + (d.error || 'Something went wrong.') + '</div>';
        }
    } catch (err) { box.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>'; }
});

const chatBox = document.getElementById('chatMessages');
function renderBubbles(messages) {
    if (!chatBox) return;
    messages.forEach(m => {
        const mine = m.role !== 'volunteer' && m.role !== 'admin' ? true : false;
        const isMine = String(m.sender_id) === String(<?= json_encode($user_id) ?>);
        const row = document.createElement('div');
        row.className = 'bubble-row ' + (isMine ? 'mine' : 'them');
        row.innerHTML = '<div class="bubble ' + (isMine ? 'mine' : 'them') + '">' +
            escapeHtmlC(m.message) + '<span class="btime">' + timeAgoC(m.created_at) + '</span></div>';
        chatBox.appendChild(row);
    });
    chatBox.scrollTop = chatBox.scrollHeight;
}
function escapeHtmlC(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
function timeAgoC(ts) {
    const s = Math.floor((Date.now() - new Date(ts.replace(' ', 'T'))) / 1000);
    if (s < 60) return 'now'; if (s < 3600) return Math.floor(s/60)+'m'; if (s < 86400) return Math.floor(s/3600)+'h';
    return Math.floor(s/86400)+'d';
}
async function pollMessages() {
    if (!chatBox) return;
    const caseId = chatBox.dataset.caseId, lastId = chatBox.dataset.lastId;
    try {
        const r = await fetch(`consultation.php?ajax=1&action=get_messages&case_id=${caseId}&last_id=${lastId}`);
        const d = await r.json();
        if (d.success && d.messages.length) {
            renderBubbles(d.messages);
            chatBox.dataset.lastId = d.messages[d.messages.length - 1].id;
        }
    } catch (e) {}
}
if (chatBox) { pollMessages(); setInterval(pollMessages, 4000); }

document.getElementById('chatSendForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = document.getElementById('chatInput');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    const fd = new FormData(); fd.append('action', 'send_message'); fd.append('csrf', CSRF);
    fd.append('case_id', chatBox.dataset.caseId); fd.append('message', msg);
    try {
        const r = await fetch('consultation.php', { method: 'POST', body: fd });
        const d = await r.json();
        if (d.success) pollMessages();
    } catch (err) {}
});

function openRateModal(caseId) {
    rateCaseId = caseId; rateValue = 0;
    document.querySelectorAll('#rateStars span').forEach(s => s.classList.remove('on'));
    document.getElementById('rateComment').value = '';
    new bootstrap.Modal(document.getElementById('rateModal')).show();
}
document.querySelectorAll('#rateStars span').forEach(s => {
    s.addEventListener('click', () => {
        rateValue = parseInt(s.dataset.v);
        document.querySelectorAll('#rateStars span').forEach(x => x.classList.toggle('on', parseInt(x.dataset.v) <= rateValue));
    });
});
async function submitRating() {
    if (!rateValue) { alert('Please select a star rating.'); return; }
    const fd = new FormData();
    fd.append('action', 'submit_feedback'); fd.append('csrf', CSRF);
    fd.append('case_id', rateCaseId); fd.append('rating', rateValue);
    fd.append('comment', document.getElementById('rateComment').value);
    const r = await fetch('consultation.php', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.success) location.reload();
}
</script>

<?php include 'includes/footer.php'; ?>
