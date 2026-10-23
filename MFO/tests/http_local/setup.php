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
$indexSource=dirname(__DIR__,2).'/admin/index.php';
$index=file_get_contents($indexSource);
if ($index===false || !str_contains($index,$needle)) throw new RuntimeException('Unexpected MFO index source');
file_put_contents($root.'/admin/mfo/index.php',str_replace($needle,"if (!isset(\$conn) || !(\$conn instanceof \\MfoHttpFixture\\FakeDb))",$index));
$commonSource=dirname(__DIR__,2).'/admin/_common.php';
$common=file_get_contents($commonSource);
if ($common===false || !str_contains($common,'function mfo_program(mysqli $db, int $id)')) throw new RuntimeException('Unexpected common helper source');
$common=str_replace('instanceof mysqli','instanceof \\MfoHttpFixture\\FakeDb',$common);
$common=str_replace('mfo_program(mysqli $db, int $id)','mfo_program(\\MfoHttpFixture\\FakeDb $db, int $id)',$common);
file_put_contents($root.'/admin/mfo/_common.php',$common);
$editSource=dirname(__DIR__,2).'/admin/edit.php';
if (!is_file($editSource)) throw new RuntimeException('Missing MFO edit.php');
copy($editSource,$root.'/admin/mfo/edit.php');
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
 public function __construct(private string $body) {}
 public function getStatusCode(): int { return 200; }
 public function getBody(): string { return $this->body; }
}
final class Api {
 public static function __constructStatic(): void {}
 public static function sendSync(string $method,string $path,$data): Reply {
  $file=__DIR__.'/../admin/mfo/mock_store.json';
  if ($method==='GET' && preg_match('~^v1/activity/(\\d+)$~',$path,$m)) {
   $rows=is_file($file)?json_decode(file_get_contents($file),true):[];
   foreach ($rows?:[] as $row) if ((int)$row['activity_id']===(int)$m[1]) {
    $a=$row['activity'];
    $a['description']=$row['description']??'{"blocks":[{"type":"paragraph","data":{"text":"Mock"}}]}';
    $a['startDate']=$row['startDate']??'2026-11-01T10:00';
    $a['activityLegs']=[];
    return new Reply(json_encode($a));
   }
   throw new \\RuntimeException('Mock activity not found');
  }
  if ($method==='GET' && $path==='v1/activities') {
   $rows=is_file($file) ? json_decode(file_get_contents($file),true) : [];
   return new Reply(json_encode(array_map(fn($r)=>$r['activity'],$rows?:[])));
  }
  if ($method !== 'POST' || $path !== 'v1/activity') throw new \RuntimeException('Unexpected mock API call');
  if (file_exists(__DIR__.'/ambiguous_api.flag')) throw new \RuntimeException('Simulated VaBase timeout after dispatch');
  if (($data['Type']??'')!=='Event' || ($data['LegsInOrder']??true)!==false) throw new \RuntimeException('Invalid MFO flags');
  file_put_contents(__DIR__.'/../admin/mfo/mock_pending.json',json_encode($data),LOCK_EX);
  return new Reply('900001');
 }
}
PHP
);
file_put_contents($root.'/admin/mfo/mock_db.php', <<<'PHP'
<?php
namespace MfoHttpFixture;
class FakeResult {
 private int $offset=0;
 public function __construct(private array $rows=[]) {}
 public function fetch_assoc(): ?array { return $this->rows[$this->offset++] ?? null; }
 public function free(): void {}
}
class FakeStatement {
 private int $id=0;
 public int $num_rows=0;
 public function __construct(private bool $lookup=false) {}
 public function store_result(): void { if ($this->lookup) { $file=__DIR__.'/mock_store.json'; $rows=is_file($file)?json_decode(file_get_contents($file),true):[]; foreach($rows?:[] as $row) if ((int)$row['activity_id']===$this->id) { $this->num_rows=1; break; } } }
 public function bind_param(string $type, &$id): void { if ($type!=='i'||$id!==900001) throw new \RuntimeException('Wrong Activity ID'); $this->id=$id; }
 public function execute(): bool {
  if (file_exists(__DIR__.'/fail_registry.flag')) return false;
  $pending=__DIR__.'/mock_pending.json';
  if (!is_file($pending)) throw new \RuntimeException('No pending mock activity');
  $data=json_decode(file_get_contents($pending),true);
  $store=__DIR__.'/mock_store.json';
  $rows=is_file($store)?json_decode(file_get_contents($store),true):[];
  $rows[]=['id'=>count($rows)+1,'activity_id'=>$this->id,'created_at'=>gmdate('c'),
   'description'=>$data['Description'],'startDate'=>$data['StartDate'],
   'activity'=>['id'=>$this->id,'title'=>$data['Title'],'type'=>$data['Type'],'active'=>$data['Active'],'endDate'=>$data['EndDate']]];
  file_put_contents($store,json_encode($rows),LOCK_EX);
  @unlink($pending);
  return true;
 }
 public function close(): void {}
}
class FakeDb {
 public function query(string $sql): FakeResult {
  if ($sql==='SELECT id FROM voy_mfo_programs LIMIT 0') return new FakeResult();
  if ($sql==='SELECT id, activity_id, created_at FROM voy_mfo_programs ORDER BY id DESC') {
   $file=__DIR__.'/mock_store.json';
   $rows=is_file($file)?json_decode(file_get_contents($file),true):[];
   return new FakeResult(array_reverse($rows?:[]));
  }
  throw new \RuntimeException('Unexpected query: '.$sql);
 }
 public function prepare(string $sql): FakeStatement {
  if ($sql==='SELECT id FROM voy_mfo_programs WHERE activity_id=? LIMIT 1') return new FakeStatement(true);
  if (!str_contains($sql,'INSERT INTO voy_mfo_programs')) throw new \RuntimeException('Unexpected INSERT');
  return new FakeStatement();
 }
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
