<?php
/**
 * MFO editor integration fixture: offline POST contract test.
 * Run: php MFO/tests/editor_form_contract.php
 * No VaBase bootstrap, database, or network calls.
 */
$root = dirname(__DIR__) . '/admin';
$pages = ['create.php', 'edit.php', 'create_leg.php', 'edit_leg.php'];
$failed = 0;
foreach ($pages as $page) {
    $src = file_get_contents($root . '/' . $page);
    $checks = [
        'editor holder' => str_contains($src, 'id="editorJs"'),
        'hidden JSON field' => str_contains($src, 'id="mfoDescription"'),
        'form binding' => str_contains($src, "field.closest('form')"),
        'JSON serialization' => str_contains($src, 'JSON.stringify(result)'),
        'submit intercept' => str_contains($src, "form.addEventListener('submit'"),
        'submit continuation' => str_contains($src, 'form.requestSubmit()'),
        'server JSON decoding' => str_contains($src, 'json_decode('),
        'nonempty block validation' => str_contains($src, '->blocks'),
    ];
    foreach ($checks as $label => $ok) {
        echo ($ok ? '[OK] ' : '[FAIL] ') . $page . ': ' . $label . PHP_EOL;
        if (!$ok) $failed++;
    }
}
echo $failed ? "FAIL ($failed checks)\n" : "PASS (32 source contract checks)\n";
exit($failed ? 1 : 0);
