<?php
declare(strict_types=1);

/**
 * MFO static smoke test. Does not include or execute application PHP files.
 * Usage: php MFO/tests/smoke.php (from any working directory).
 */
$admin = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'admin';
$files = ['index.php', 'create.php', 'edit.php', 'create_leg.php', 'edit_leg.php', '_common.php'];
$failures = 0;
$contents = [];
foreach ($files as $file) {
    $path = $admin . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing: $file\n");
        ++$failures;
        continue;
    }
    $contents[$file] = file_get_contents($path);
    echo "[OK] Found: $file\n";
}
$checks = [
    ['create.php', "'Type' => 'Event'", 'MFO activity type fixed to Event'],
    ['create.php', "'LegsInOrder' => false", 'MFO legs not ordered'],
    ['create.php', 'voy_mfo_programs', 'Program registry insertion'],
    ['create_leg.php', 'voy_mfo_slots', 'Slot registry insertion'],
    ['edit.php', "mfo_program(", 'Activity ownership check'],
    ['edit_leg.php', "mfo_program(", 'Leg ownership check'],
    ['edit_leg.php', "AVAILABLE", 'Leg edit status restriction'],
    ['_common.php', 'userHasPermission(11)', 'Admin permission 11'],
    ['_common.php', 'mfo_verify()', 'CSRF validation helper'],
];
foreach ($checks as [$file, $needle, $label]) {
    if (!isset($contents[$file]) || strpos($contents[$file], $needle) === false) {
        fwrite(STDERR, "[FAIL] $label\n");
        ++$failures;
    } else {
        echo "[OK] $label\n";
    }
}
echo "\n" . ($failures ? "FAILED ($failures checks)\n" : "PASS (static smoke checks)\n");
exit($failures ? 1 : 0);
