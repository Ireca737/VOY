<?php
/** Shared MFO administration helpers; deploy to /admin/mfo/_common.php. */
require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../proxy/api.php';
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
\Proxy\Api\Api::__constructStatic();
validateAdminSession();
if (!userHasPermission(11)) { header('Location: '.website_base_url.'admin/access_denied.php'); exit; }
require_once __DIR__ . '/../voy/secure_config/conn_voy.php';
if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Connessione VOY assente');
function mfo_e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mfo_program(mysqli $db, int $id): bool {
  $s=$db->prepare('SELECT id FROM voy_mfo_programs WHERE activity_id=? LIMIT 1');
  if (!$s) throw new RuntimeException('Query MFO non disponibile');
  $s->bind_param('i',$id); $s->execute(); $s->store_result(); $found=$s->num_rows===1; $s->close(); return $found;
}
function mfo_token(): string { if (empty($_SESSION['mfo_csrf'])) $_SESSION['mfo_csrf']=bin2hex(random_bytes(32)); return $_SESSION['mfo_csrf']; }
function mfo_verify(): bool { return isset($_POST['csrf_token']) && hash_equals(mfo_token(),(string)$_POST['csrf_token']); }
function mfo_leg_activity(int $legId): ?object {
  $r=\Proxy\Api\Api::sendSync('GET','v1/activity/leg/'.$legId,null);
  return $r->getStatusCode()===200 ? json_decode($r->getBody()) : null;
}
