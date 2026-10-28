<?php
/** Consultant doctors: directory, applications, profile, availability, appointments, reviews. */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$pdo = hx_pdo();

function dr_slots_for(PDO $pdo, int $doc, string $date): array {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) return [];
    if ($dt < new DateTime('today') || $dt > new DateTime('+60 days')) return [];
    $s = $pdo->prepare('SELECT * FROM doctor_slots WHERE doctor_id = ? AND weekday = ? AND is_active = 1 ORDER BY start_time');
    $s->execute([$doc, (int)$dt->format('w')]);
    $taken = $pdo->prepare("SELECT starts_at FROM appointments WHERE doctor_id = ? AND DATE(starts_at) = ? AND status IN ('requested','confirmed')");
    $taken->execute([$doc, $date]);
    $busy = array_map(fn($x) => substr($x, 11, 5), $taken->fetchAll(PDO::FETCH_COLUMN));
    $out = []; $now = new DateTime();
    foreach ($s->fetchAll() as $sl) {
        $t = new DateTime("$date " . $sl['start_time']); $end = new DateTime("$date " . $sl['end_time']);
        while ($t < $end) {
            $hm = $t->format('H:i');
            if ($t > $now && !in_array($hm, $busy, true)) $out[] = ['time' => $hm, 'mode' => $sl['mode'], 'mins' => (int)$sl['slot_minutes']];
            $t->modify('+' . max(10, (int)$sl['slot_minutes']) . ' minutes');
        }
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $a = (string)($_GET['a'] ?? 'list');
    if ($a === 'slots') {
        hx_need_login();
        hx_out(['ok' => true, 'slots' => dr_slots_for($pdo, (int)($_GET['doctor'] ?? 0), (string)($_GET['date'] ?? ''))]);
    }
    if ($a === 'list') {
        $q = hx_clean((string)($_GET['q'] ?? ''), 60); $sp = hx_clean((string)($_GET['specialty'] ?? ''), 60);
        $sql = "SELECT d.*, u.id uid, (SELECT ROUND(AVG(stars),1) FROM doctor_reviews r WHERE r.doctor_id = d.user_id) rating,
                       (SELECT COUNT(*) FROM doctor_reviews r2 WHERE r2.doctor_id = d.user_id) reviews
                FROM doctor_profiles d JOIN users u ON u.id = d.user_id WHERE d.status = 'approved' AND u.is_active = 1";
        $p = [];
        if ($q !== '') { $sql .= ' AND (d.display_name LIKE ? OR d.specialty LIKE ? OR d.chamber_address LIKE ?)'; $l = '%' . addcslashes($q, '%_') . '%'; array_push($p, $l, $l, $l); }
        if ($sp !== '') { $sql .= ' AND d.specialty = ?'; $p[] = $sp; }
        $s = $pdo->prepare($sql . ' ORDER BY rating DESC, d.experience_years DESC LIMIT 60'); $s->execute($p);
        hx_out(['ok' => true, 'doctors' => array_map(fn($r) => [
            'id' => (int)$r['uid'], 'name' => $r['display_name'], 'specialty' => $r['specialty'], 'qual' => $r['qualifications'], 'years' => $r['experience_years'],
            'fee' => $r['fee'], 'online' => (bool)$r['online_consult'], 'chamber' => $r['chamber_name'], 'address' => $r['chamber_address'],
            'lat' => $r['lat'] !== null ? (float)$r['lat'] : null, 'lng' => $r['lng'] !== null ? (float)$r['lng'] : null,
            'rating' => $r['rating'] !== null ? (float)$r['rating'] : null, 'reviews' => (int)$r['reviews']], $s->fetchAll())]);
    }
    if ($a === 'appointments') {
        $me = hx_need_login(); $asDoc = hx_role($me) === 'doctor';
        $s = $pdo->prepare('SELECT * FROM appointments WHERE ' . ($asDoc ? 'doctor_id' : 'patient_id') . ' = ? ORDER BY starts_at DESC LIMIT 60');
        $s->execute([$me]);
        hx_out(['ok' => true, 'items' => array_map(function ($r) use ($asDoc, $me) {
            $other = hx_user_brief((int)($asDoc ? $r['patient_id'] : $r['doctor_id']));
            return ['id' => (int)$r['id'], 'with' => $other, 'starts' => date('c', strtotime($r['starts_at'])), 'mode' => $r['mode'], 'status' => $r['status'],
                    'reason' => $asDoc ? hx_dec($r['reason'], 'appt') : null, 'note' => $asDoc ? hx_dec($r['doctor_note'], 'appt') : (in_array($r['status'], ['completed']) ? hx_dec($r['doctor_note'], 'appt') : null)];
        }, $s->fetchAll())]);
    }
    hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
}

$me = hx_post_guard(); $d = hx_input(); $a = (string)($d['a'] ?? '');

if ($a === 'apply') {
    if (hx_role($me) !== 'user') hx_out(['ok' => false, 'error' => 'Your account type can’t apply as a doctor.'], 403);
    $ex = $pdo->prepare("SELECT status FROM doctor_profiles WHERE user_id = ?"); $ex->execute([$me]);
    if ($st = $ex->fetchColumn()) hx_out(['ok' => false, 'error' => $st === 'pending' ? 'Your application is already under review.' : 'You already have an application on file.'], 409);
    $name = hx_clean((string)($_POST['display_name'] ?? ''), 120); $spec = hx_clean((string)($_POST['specialty'] ?? ''), 120);
    $qual = hx_clean((string)($_POST['qualifications'] ?? ''), 255); $reg = hx_clean((string)($_POST['reg_no'] ?? ''), 60);
    if ($name === '' || $spec === '' || $reg === '' || $qual === '') hx_out(['ok' => false, 'error' => 'Name, specialty, qualifications and medical registration number are required.'], 422);
    if (empty($_FILES['license']) || $_FILES['license']['error'] === UPLOAD_ERR_NO_FILE) hx_out(['ok' => false, 'error' => 'Please upload a photo of your medical registration / licence.'], 422);
    $r = hx_save_image($_FILES['license'], 'private:license', 1800);
    if (!$r['ok']) hx_out(['ok' => false, 'error' => $r['error']], 422);
    $pdo->prepare("INSERT INTO doctor_profiles (user_id, status, display_name, specialty, qualifications, reg_no, license_doc, created_at) VALUES (?, 'pending', ?, ?, ?, ?, ?, NOW())")
        ->execute([$me, $name, $spec, $qual, $reg, $r['path']]);
    foreach ($pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll(PDO::FETCH_COLUMN) as $adm)
        hx_notify((int)$adm, 'consultation', "New doctor application: $name ($spec). Please verify the licence.", 'admin_doctors.php');
    hx_out(['ok' => true, 'message' => 'Application sent. An admin will verify your licence before your profile goes live.']);
}

// Everything below requires an approved doctor
function dr_need_doctor(int $me): array {
    $s = hx_pdo()->prepare("SELECT * FROM doctor_profiles WHERE user_id = ? AND status = 'approved'"); $s->execute([$me]);
    $p = $s->fetch();
    if (!$p || hx_role($me) !== 'doctor') hx_out(['ok' => false, 'error' => 'Only approved doctors can do this.'], 403);
    return $p;
}

if ($a === 'save_profile') {
    dr_need_doctor($me);
    [$lat, $lng] = hx_coords($d['lat'] ?? null, $d['lng'] ?? null, false);
    $pdo->prepare('UPDATE doctor_profiles SET display_name=?, specialty=?, qualifications=?, experience_years=?, bio=?, languages=?, fee=?, online_consult=?, chamber_name=?, chamber_address=?, lat=?, lng=? WHERE user_id=?')
        ->execute([hx_clean((string)($d['display_name'] ?? ''), 120) ?: 'Doctor', hx_clean((string)($d['specialty'] ?? ''), 120), hx_clean((string)($d['qualifications'] ?? ''), 255),
                   max(0, min(70, (int)($d['experience_years'] ?? 0))), hx_clean((string)($d['bio'] ?? ''), 2000), hx_clean((string)($d['languages'] ?? ''), 160),
                   max(0, min(1000000, (int)($d['fee'] ?? 0))), !empty($d['online_consult']) ? 1 : 0, hx_clean((string)($d['chamber_name'] ?? ''), 160),
                   hx_clean((string)($d['chamber_address'] ?? ''), 255), $lat, $lng, $me]);
    hx_out(['ok' => true]);
}

if ($a === 'save_slots') {
    dr_need_doctor($me);
    $slots = is_array($d['slots'] ?? null) ? array_slice($d['slots'], 0, 40) : [];
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM doctor_slots WHERE doctor_id = ?')->execute([$me]);
    $ins = $pdo->prepare('INSERT INTO doctor_slots (doctor_id, weekday, start_time, end_time, slot_minutes, mode) VALUES (?,?,?,?,?,?)');
    foreach ($slots as $s) {
        $wd = (int)($s['weekday'] ?? -1);
        if ($wd < 0 || $wd > 6 || !preg_match('/^\d{2}:\d{2}$/', (string)($s['start'] ?? '')) || !preg_match('/^\d{2}:\d{2}$/', (string)($s['end'] ?? '')) || $s['start'] >= $s['end']) continue;
        $ins->execute([$me, $wd, $s['start'] . ':00', $s['end'] . ':00', max(10, min(120, (int)($s['mins'] ?? 30))), ($s['mode'] ?? '') === 'online' ? 'online' : 'chamber']);
    }
    $pdo->commit();
    hx_out(['ok' => true]);
}

if ($a === 'book') {
    if (!hx_rate($me, 'book', 6, 86400)) hx_out(['ok' => false, 'error' => 'Booking limit reached for today.'], 429);
    $doc = (int)($d['doctor'] ?? 0); $date = (string)($d['date'] ?? ''); $time = (string)($d['time'] ?? '');
    if ($doc === $me) hx_out(['ok' => false, 'error' => 'You can’t book yourself.'], 422);
    $ok = $pdo->prepare("SELECT 1 FROM doctor_profiles WHERE user_id = ? AND status = 'approved'"); $ok->execute([$doc]);
    if (!$ok->fetchColumn() || hx_blocked($me, $doc)) hx_out(['ok' => false, 'error' => 'This doctor is not available.'], 404);
    $match = null;
    foreach (dr_slots_for($pdo, $doc, $date) as $sl) if ($sl['time'] === $time) $match = $sl;
    if (!$match) hx_out(['ok' => false, 'error' => 'That time is no longer available. Please pick another.'], 409);
    $start = new DateTime("$date $time"); $end = (clone $start)->modify('+' . $match['mins'] . ' minutes');
    $pdo->beginTransaction();
    $c = $pdo->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND starts_at = ? AND status IN ('requested','confirmed') FOR UPDATE"); $c->execute([$doc, $start->format('Y-m-d H:i:s')]);
    if ($c->fetchColumn()) { $pdo->rollBack(); hx_out(['ok' => false, 'error' => 'Someone just took that time. Please pick another.'], 409); }
    $mode = ($d['mode'] ?? '') === 'online' && $match['mode'] === 'online' ? 'online' : $match['mode'];
    $pdo->prepare("INSERT INTO appointments (doctor_id, patient_id, starts_at, ends_at, mode, status, reason, created_at) VALUES (?,?,?,?,?, 'requested', ?, NOW())")
        ->execute([$doc, $me, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $mode, hx_enc(hx_clean((string)($d['reason'] ?? ''), 500), 'appt')]);
    $pdo->commit();
    hx_notify($doc, 'appointment', 'New appointment request for ' . $start->format('D j M, g:i A') . '.', 'doctor_panel.php');
    hx_out(['ok' => true, 'message' => 'Request sent. You will be notified when the doctor responds.']);
}

if ($a === 'appt_status') {
    $id = (int)($d['id'] ?? 0); $to = (string)($d['status'] ?? '');
    $s = $pdo->prepare('SELECT * FROM appointments WHERE id = ?'); $s->execute([$id]); $ap = $s->fetch();
    if (!$ap) hx_out(['ok' => false, 'error' => 'Not found.'], 404);
    $isDoc = (int)$ap['doctor_id'] === $me; $isPat = (int)$ap['patient_id'] === $me;
    if (!$isDoc && !$isPat) hx_out(['ok' => false, 'error' => 'Not found.'], 404);
    if ($isDoc && in_array($to, ['confirmed', 'declined', 'completed'], true) && in_array($ap['status'], ['requested', 'confirmed'], true)) {
        $note = hx_clean((string)($d['note'] ?? ''), 1000);
        $pdo->prepare('UPDATE appointments SET status = ?, doctor_note = COALESCE(?, doctor_note) WHERE id = ?')->execute([$to, $note !== '' ? hx_enc($note, 'appt') : null, $id]);
        hx_notify((int)$ap['patient_id'], 'appointment', 'Your appointment on ' . date('D j M, g:i A', strtotime($ap['starts_at'])) . ' was ' . $to . '.', 'doctors.php?tab=mine');
    } elseif ($isPat && $to === 'cancelled' && in_array($ap['status'], ['requested', 'confirmed'], true)) {
        $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        hx_notify((int)$ap['doctor_id'], 'appointment', 'A patient cancelled the appointment on ' . date('D j M, g:i A', strtotime($ap['starts_at'])) . '.', 'doctor_panel.php');
    } else hx_out(['ok' => false, 'error' => 'That change isn’t allowed.'], 403);
    hx_out(['ok' => true]);
}

if ($a === 'review') {
    $doc = (int)($d['doctor'] ?? 0); $stars = (int)($d['stars'] ?? 0);
    if ($stars < 1 || $stars > 5) hx_out(['ok' => false, 'error' => 'Choose 1–5 stars.'], 422);
    $h = $pdo->prepare("SELECT 1 FROM appointments WHERE doctor_id = ? AND patient_id = ? AND status = 'completed' LIMIT 1"); $h->execute([$doc, $me]);
    if (!$h->fetchColumn()) hx_out(['ok' => false, 'error' => 'You can review a doctor after a completed appointment.'], 403);
    $c = hx_clean((string)($d['comment'] ?? ''), 500);
    if ($c !== '' && hx_toxic($c)) hx_out(['ok' => false, 'error' => 'Please keep reviews respectful.'], 422);
    $pdo->prepare('INSERT INTO doctor_reviews (doctor_id, patient_id, stars, comment, created_at) VALUES (?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE stars = VALUES(stars), comment = VALUES(comment)')->execute([$doc, $me, $stars, $c ?: null]);
    hx_out(['ok' => true]);
}
hx_out(['ok' => false, 'error' => 'Unknown action.'], 400);
