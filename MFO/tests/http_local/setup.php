<?php
declare(strict_types=1);
/** Build a disposable local web root with the REAL MFO create.php. */
$root = __DIR__ . '/runtime/public_html';
$dirs = ['/admin/mfo','/admin/includes','/lib','/proxy'];
foreach ($dirs as $dir) if (!is_dir($root.$dir)) mkdir($root.$dir,0777,true);
$source = dirname(__DIR__,2).'/admin/create.php';
if (!is_file($source)) throw new RuntimeException('Missing real MFO/admin/create.php');
copy($source,$root.'/admin/mfo/create.php');
file_put_contents($root.'/lib/functions.php', <<<'PHP'
<?php
function validateAdminSession(): void {}
function userHasPermission(int $permission): bool { return $permission === 11; }
PHP
);
file_put_contents($root.'/proxy/api.php', <<<'PHP'
<?php
namespace Proxy\Api;
final class Api {
 public static function __constructStatic(): void {}
 public static function sendSync(...$args): never { throw new \RuntimeException('Blocked: VaBase API is disabled in local HTTP fixture'); }
}
PHP
);
file_put_contents($root.'/config.php', "<?php\ndefine('website_base_url','/');\n");
foreach (['nav','sidebar','footer'] as $name) file_put_contents($root.'/admin/includes/'.$name.'.php',"<!-- isolated $name -->");
echo "Fixture ready: $root\n";
echo "Start with: php -S 127.0.0.1:8097 -t MFO/tests/http_local/runtime/public_html\n";
