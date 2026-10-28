<?php
/** Permission-checked delivery of private files (DM photos, prescriptions, doctor licences). */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$me = hx_uid();
if (!$me) { http_response_code(401); exit('Login required'); }
$pdo = hx_pdo();
$k = (string)($_GET['k'] ?? ''); $id = (int)($_GET['id'] ?? 0);
$path = null;

if ($k === 'dm') {
    $s = $pdo->prepare("SELECT m.media_path FROM dm_messages m JOIN dm_threads t ON t.id = m.thread_id
                        WHERE m.id = ? AND m.kind = 'image' AND m.is_hidden = 0 AND m.deleted_at IS NULL AND (t.user_a = ? OR t.user_b = ?)");
    $s->execute([$id, $me, $me]);
    $path = $s->fetchColumn();
} elseif ($k === 'rx') {
    $s = $pdo->prepare("SELECT file_path, patient_id, doctor_id FROM prescriptions WHERE id = ? AND deleted_at IS NULL");
    $s->execute([$id]);
    if ($r = $s->fetch()) {
        $ok = (int)$r['patient_id'] === $me || (int)$r['doctor_id'] === $me;
        if (!$ok && hx_role($me) === 'doctor') {
            $c = $pdo->prepare('SELECT 1 FROM prescription_shares WHERE prescription_id = ? AND doctor_id = ? AND revoked_at IS NULL');
            $c->execute([$id, $me]); $ok = (bool)$c->fetchColumn();
        }
        if ($ok) $path = $r['file_path'];
    }
} elseif ($k === 'license') {
    if ($id === $me || hx_role($me) === 'admin') {
        $s = $pdo->prepare('SELECT license_doc FROM doctor_profiles WHERE user_id = ?');
        $s->execute([$id]); $path = $s->fetchColumn();
    }
}
if (!$path) { http_response_code(404); exit('Not found'); }
hx_stream_private($path);
