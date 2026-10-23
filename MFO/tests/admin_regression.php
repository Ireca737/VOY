<?php
/**
 * Targeted regression checks for MFO form rendering and server-side guards.
 * Static assertions only: no VaBase API, HTTP server, or database.
 */
$base = dirname(__DIR__) . '/admin/';
$cases = [
 ['create_leg.php', 'New leg must not read an undefined existing $leg', fn($s) => !str_contains($s, '$leg->description')],
 ['create_leg.php', 'New leg preserves submitted description', fn($s) => str_contains($s, "$_POST['description']??''")],
 ['create_leg.php', 'Reconciliation block enforced on POST', fn($s) => str_contains($s, "if (!empty($_SESSION['mfo_leg_block_'.$id]))")],
 ['create_leg.php', 'MFO ownership validated', fn($s) => str_contains($s, 'mfo_program($conn,$id)')],
 ['edit_leg.php', 'Booked leg cannot POST update', fn($s) => str_contains($s, "if (!$editable) $error=")],
 ['edit_leg.php', 'Editor hidden when locked', fn($s) => str_contains($s, 'if (!$editable):')],
 ['create.php', 'Activity creation requires CSRF', fn($s) => str_contains($s, 'hash_equals($expectedToken, $submittedToken)')],
 ['edit.php', 'Activity editing requires CSRF', fn($s) => str_contains($s, 'mfo_verify()')],
];
$failed=0;
foreach($cases as [$file,$label,$test]){
 $source=file_get_contents($base.$file);
 $ok=$test($source);
 echo ($ok?'[OK] ':'[FAIL] ').$label.PHP_EOL;
 if(!$ok)$failed++;
}
echo $failed?"FAIL ($failed)\n":"PASS (8 targeted regression checks)\n";
exit($failed?1:0);
