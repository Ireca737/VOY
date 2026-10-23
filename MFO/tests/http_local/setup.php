<?php
declare(strict_types=1);
/** Build a disposable local web root with the REAL MFO create.php. */
$root = __DIR__ . '/runtime/public_html';
$dirs = ['/admin/mfo','/admin/includes','/lib','/proxy','/admin/voy/secure_config'];
foreach ($dirs as $dir) if (!is_dir($root.$dir)) mkdir($root.$dir,0777,true);
$source = dirname(__DIR__,2).'/admin/create.php';
if (!is_file($source)) throw new RuntimeException('Missing real MFO/admin/create.php');
// Only the disposable fixture is patched; the repository's create.php is unchanged.
$page=file_get_contents($source);
$needle="if (!isset(\$conn) || !(\$conn instanceof mysqli))";
if (!str_contains($page,$needle)) throw new RuntimeException('Unexpected create.php DB guard; fixture not built');
$page=str_replace($needle,"if (!isset(\$conn) || !(\$conn instanceof \\MfoHttpFixture\\FakeDb))",$page);
file_put_contents($root.'/admin/mfo/create.php',$page);
file_put_contents($root.'/lib/functions.php', <<<'PHP'
<?php
function validateAdminSession(): void {}
function userHasPermission(int $permission): bool { return $permission === 11; }
PHP
);
file_put_contents($root.'/proxy/api.php', <<<'PHP'
<?php
namespace Proxy\Api;
final class Reply {
 public function getStatusCode(): int { return 200; }
 public function getBody(): string { return '900001'; }
}
final class Api {
 public static function __constructStatic(): void {}
 public static function sendSync(string $method,string $path,$data): Reply {
  if ($method !== 'POST' || $path !== 'v1/activity') throw new \RuntimeException('Unexpected mock API call');
  if (($data['Type']??'')!=='Event' || ($data['LegsInOrder']??true)!==false) throw new \RuntimeException('Invalid MFO flags');
  return new Reply();
 }
}
PHP
);
file_put_contents($root.'/admin/mfo/mock_db.php', <<<'PHP'
<?php
namespace MfoHttpFixture;
class FakeResult { public function free(): void {} }
class FakeStatement {
 public function bind_param(string $type, &$id): void { if ($type!=='i'||$id!==900001) throw new \RuntimeException('Wrong Activity ID'); }
 public function execute(): bool { return !file_exists(__DIR__.'/fail_registry.flag'); }
 public function close(): void {}
}
class FakeDb {
 public function query(string $sql): FakeResult { if ($sql!=='SELECT id FROM voy_mfo_programs LIMIT 0') throw new \RuntimeException('Unexpected query'); return new FakeResult(); }
 public function prepare(string $sql): FakeStatement { if (!str_contains($sql,'INSERT INTO voy_mfo_programs')) throw new \RuntimeException('Unexpected INSERT'); return new FakeStatement(); }
}
PHP
);
file_put_contents($root.'/admin/voy/secure_config/conn_voy.php', <<<'PHP'
<?php
require_once __DIR__.'/../../mfo/mock_db.php';
$conn=new \MfoHttpFixture\FakeDb();
PHP
);
file_put_contents($root.'/config.php', "<?php\ndefine('website_base_url','/');\n");
foreach (['nav','sidebar','footer'] as $name) file_put_contents($root.'/admin/includes/'.$name.'.php',"<!-- isolated $name -->");
echo "Fixture ready: $root\n";
echo "Start with: php -S 127.0.0.1:8097 -t MFO/tests/http_local/runtime/public_html\n";
