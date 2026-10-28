<?php
/** Prescription vault: save OCR'd old prescriptions, share with a doctor, doctors issue e-prescriptions. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$pdo = hx_pdo();

function rx_clean_meds($raw): array {
    $arr = is_string($raw) ? json_decode($raw, true) : $raw;
    $out = [];
    foreach (is_array($arr) ? array_slice($arr, 0, 30) : [] as $m) {
        if (!is_array($m)) continue;
        $name = hx_clean((string)($m['name'] ?? ''), 120);
        if ($name === '') continue;
        $out[] = ['name' => $name, 'dose' => hx_clean((string)($m['dose'] ?? ''), 60), 'frequency' => hx_clean((string)($m['frequency'] ?? ''), 80),
                  'duration' => hx_clean((string)($m['duration'] ?? ''), 60), 'notes' => hx_clean((string)($m['notes'] ?? ''), 200)];
    }
    return $out;
}
function rx_row(array $r, bool $full): array {
    $o = ['id' => (int)$r['id'], 'title' => $r['title'], 'source' => $r['source'], 'issued_on' => $r['issued_on'], 'doctor' => $r['doctor_name_text'],
          'has_file' => !empty($r['file_path']), 'file' => !empty($r['file_path']) ? 'api/file.php?k=rx&id=' . (int)$r['id'] : null,
          'created' => date('c', strtotime($r['created_at'])), 'follow_up' => $r['follow_up'],
          'meds' => json_decode(hx_dec($r['medicines'], 'rx') ?: '[]', true) ?: []];
    if ($full) { $o['text'] = hx_dec($r['ocr_text'], 'rx'); $o['advice'] = hx_dec($r['advice'], 'rx'); $o['engine'] = $r['ocr_engine']; }
    return $o;
}
function rx_can_view(PDO $pdo, int $me, array $r): bool {
    if ((int)$r['patient_id'] === $me || (int)$r['doctor_id'] === $me) return true;
    if (hx_role($me) !== 'doctor') return false;
    $c = $pdo->prepare('SELECT 1 FROM prescription_shares WHERE prescription_id = ? AND doctor_id = ? AND revoked_at IS NULL');
    $c->execute([$r['id'], $me]);
    return (bool)$c->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $me = hx_need_login(); $a = (string)($_GET['a'] ?? 'list');
    if ($a === 'list') {
        $s = $pdo->prepare('SELECT * FROM prescriptions WHERE patient_id = ? AND deleted_at IS NULL ORDER BY COALESCE(issued_on, DATE(created_at)) DESC, id DESC LIMIT 100');
        $s->execute([$me]);
        $items = array_map(function ($r) use ($pdo) {
            $o = rx_row($r, false);
            $sh = $pdo->prepare('SELECT doctor_id FROM prescription_shares WHERE prescription_id = ? AND revoked_at IS NULL'); $sh->execute([$r['id']]);
            $o['shared_with'] = array_map('hx_user_brief', $sh->fetchAll(PDO::FETCH_COLUMN));
            return $o;
        }, $s->fetchAll());
        hx_out(['ok' => true, 'items' => $items]);
    }
    if ($a === 'get') {
        $s = $pdo->prepare('SELECT * FROM prescriptions WHERE id = ? AND deleted_at IS NULL'); $s->execute([(int)($_GET['id'] ?? 0)]);
        $r = $s->fetch();
        if (!$r || !rx_can_view($pdo, $me, $r)) hx_out(['ok' => false, 'error' => 'Not found.'], 404);
        hx_out(['ok' => true, 'item' => rx_row($r, true), 'patient' => hx_user_brief((int)$r['patient_id'])]);
    }
    if ($a === 'shared_with_me') {                                   // doctor's inbox
        if (hx_role($me) !== 'doctor') hx_out(['ok' => false, 'error' => 'Doctors only.'], 403);
        $s = $pdo->prepare('SELECT p.* FROM prescriptions p JOIN prescription_shares sh ON sh.prescription_id = p.id WHERE sh.doctor_id = ? AND sh.revoked_at IS NULL AND p.deleted_at IS NULL ORDER BY sh.shared_at DESC LIMIT 60');
        $s->execute([$me]);
        hx_out(['ok' => true, 'items' => array_map(fn($r) => rx_row($r, true) + ['patient' => hx_user_brief((int)$r['patient_id'])], $s->fetchAll())]);
    }
    hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}

$me = hx_post_guard(); $d = hx_input(); $a = (string)($d['a'] ?? '');

if ($a === 'save') {
    if (!hx_rate($me, 'rx_save', 30, 86400)) hx_out(['ok' => false, 'error' => 'Daily upload limit reached.'], 429);
    $title = hx_clean((string)($_POST['title'] ?? ''), 160) ?: 'Prescription';
    $on = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST['issued_on'] ?? '')) ? $_POST['issued_on'] : null;
    $path = null;
    if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $r = hx_save_image($_FILES['image'], 'private:rx', 2000, 10485760);
        if (!$r['ok']) hx_out(['ok' => false, 'error' => $r['error']], 422);
        $path = $r['path'];
    }
    $meds = rx_clean_meds($_POST['medicines'] ?? '[]');
    $text = hx_clean((string)($_POST['ocr_text'] ?? ''), 20000);
    if (!$path && $text === '' && !$meds) hx_out(['ok' => false, 'error' => 'Add a photo or some text first.'], 422);
    $pdo->prepare("INSERT INTO prescriptions (patient_id, source, title, issued_on, doctor_name_text, file_path, ocr_text, ocr_engine, medicines, advice, created_at) VALUES (?, 'uploaded', ?, ?, ?, ?, ?, ?, ?, ?, NOW())")
        ->execute([$me, $title, $on, hx_clean((string)($_POST['doctor'] ?? ''), 160) ?: null, $path, hx_enc($text, 'rx'),
                   in_array($_POST['engine'] ?? '', ['tesseract', 'ai', 'manual'], true) ? $_POST['engine'] : 'manual', hx_enc(json_encode($meds, JSON_UNESCAPED_UNICODE), 'rx'),
                   hx_enc(hx_clean((string)($_POST['advice'] ?? ''), 4000), 'rx')]);
    hx_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}

if ($a === 'delete') {
    $id = (int)($d['id'] ?? 0);
    $s = $pdo->prepare('SELECT file_path FROM prescriptions WHERE id = ? AND patient_id = ? AND deleted_at IS NULL'); $s->execute([$id, $me]);
    if ($r = $s->fetch()) {
        $pdo->prepare('UPDATE prescriptions SET deleted_at = NOW(), ocr_text = NULL, medicines = NULL, advice = NULL, file_path = NULL WHERE id = ?')->execute([$id]);
        $pdo->prepare('UPDATE prescription_shares SET revoked_at = NOW() WHERE prescription_id = ? AND revoked_at IS NULL')->execute([$id]);
        if ($r['file_path'] && strncmp($r['file_path'], 'uploads/private/rx/', 19) === 0) @unlink(hx_root() . '/' . $r['file_path']);
    }
    hx_out(['ok' => true]);
}

if ($a === 'share' || $a === 'revoke') {
    $id = (int)($d['id'] ?? 0); $doc = (int)($d['doctor'] ?? 0);
    $own = $pdo->prepare('SELECT 1 FROM prescriptions WHERE id = ? AND patient_id = ? AND deleted_at IS NULL'); $own->execute([$id, $me]);
    if (!$own->fetchColumn()) hx_out(['ok' => false, 'error' => 'Not found.'], 404);
    if ($a === 'share') {
        $ok = $pdo->prepare("SELECT 1 FROM doctor_profiles WHERE user_id = ? AND status = 'approved'"); $ok->execute([$doc]);
        if (!$ok->fetchColumn() || hx_blocked($me, $doc)) hx_out(['ok' => false, 'error' => 'Doctor not available.'], 404);
        $pdo->prepare('INSERT INTO prescription_shares (prescription_id, doctor_id, shared_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE shared_at = NOW(), revoked_at = NULL')->execute([$id, $doc]);
        hx_notify($doc, 'consultation', 'A patient shared a prescription with you.', 'doctor_panel.php?tab=shared');
    } else {
        $pdo->prepare('UPDATE prescription_shares SET revoked_at = NOW() WHERE prescription_id = ? AND doctor_id = ?')->execute([$id, $doc]);
    }
    hx_out(['ok' => true]);
}

if ($a === 'issue') {                                               // approved doctor → patient who has an appointment
    $ds = $pdo->prepare("SELECT display_name FROM doctor_profiles WHERE user_id = ? AND status = 'approved'"); $ds->execute([$me]);
    $dname = $ds->fetchColumn();
    if (!$dname || hx_role($me) !== 'doctor') hx_out(['ok' => false, 'error' => 'Only approved doctors can issue prescriptions.'], 403);
    $pat = (int)($d['patient'] ?? 0);
    $ap = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND patient_id = ? AND status IN ('confirmed','completed') ORDER BY starts_at DESC LIMIT 1"); $ap->execute([$me, $pat]);
    $apId = $ap->fetchColumn();
    if (!$apId) hx_out(['ok' => false, 'error' => 'You can only write a prescription for a patient with a confirmed appointment.'], 403);
    $meds = rx_clean_meds($d['medicines'] ?? []);
    if (!$meds) hx_out(['ok' => false, 'error' => 'Add at least one medicine.'], 422);
    $fu = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($d['follow_up'] ?? '')) ? $d['follow_up'] : null;
    $pdo->prepare("INSERT INTO prescriptions (patient_id, doctor_id, appointment_id, source, title, issued_on, doctor_name_text, medicines, advice, follow_up, created_at) VALUES (?,?,?, 'issued', ?, CURDATE(), ?, ?, ?, ?, NOW())")
        ->execute([$pat, $me, $apId, hx_clean((string)($d['title'] ?? ''), 160) ?: 'Prescription from ' . $dname, $dname, hx_enc(json_encode($meds, JSON_UNESCAPED_UNICODE), 'rx'), hx_enc(hx_clean((string)($d['advice'] ?? ''), 4000), 'rx'), $fu]);
    hx_notify($pat, 'consultation', "$dname wrote you a prescription.", 'prescriptions.php');
    hx_out(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
}
hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
