<?php
/**
 * Turns a pasted Google Maps link (including maps.app.goo.gl short links) into coordinates.
 * SSRF-safe: only Google Maps hosts are ever contacted, max 4 redirects, no other schemes.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
$me = hx_post_guard();
if (!hx_rate($me, 'maps_resolve', 30, 3600)) hx_out(['ok' => false, 'error' => 'Too many lookups, try again later.'], 429);
$url = trim((string)(hx_input()['url'] ?? ''));
function mr_allowed(string $u): bool {
    $p = parse_url($u);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) return false;
    $h = strtolower($p['host']);
    return in_array($h, ['maps.app.goo.gl', 'goo.gl', 'www.google.com', 'google.com', 'maps.google.com'], true)
        && ($h !== 'www.google.com' && $h !== 'google.com' || strncmp($p['path'] ?? '', '/maps', 5) === 0)
        && ($h !== 'goo.gl' || strncmp($p['path'] ?? '', '/maps', 5) === 0);
}
function mr_parse(string $u): ?array {
    $u = urldecode($u);
    foreach (['/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', '/@(-?\d+\.\d+),(-?\d+\.\d+)/', '/[?&](?:q|query|ll|center)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/'] as $re)
        if (preg_match($re, $u, $m)) return [(float)$m[1], (float)$m[2]];
    return null;
}
if (!mr_allowed($url)) hx_out(['ok' => false, 'error' => 'Paste a Google Maps link (google.com/maps or maps.app.goo.gl).'], 422);
$cur = $url; $name = null;
for ($i = 0; $i < 5; $i++) {
    if ($c = mr_parse($cur)) {
        if (preg_match('~/maps/place/([^/@]+)~', urldecode($cur), $m)) $name = trim(str_replace('+', ' ', $m[1]));
        hx_out(['ok' => true, 'lat' => $c[0], 'lng' => $c[1], 'name' => $name]);
    }
    if (!function_exists('curl_init')) break;
    $ch = curl_init($cur);
    curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_HEADER => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 6, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 HavenMapResolver']);
    $h = curl_exec($ch); curl_close($ch);
    if (!$h || !preg_match('/^location:\s*(\S+)/im', $h, $m)) break;
    $next = $m[1];
    if (!mr_allowed($next)) break;
    $cur = $next;
}
hx_out(['ok' => false, 'error' => 'Could not read a location from that link. Try the “Share → Copy link” option in Google Maps, or drop a pin on the map.'], 422);
