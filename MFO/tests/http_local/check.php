<?php
declare(strict_types=1);
/** HTTP checks against an already-running isolated PHP server. */
$url='http://127.0.0.1:8097/admin/mfo/create.php';
$cookie=tempnam(sys_get_temp_dir(),'mfo_http_');
function request(string $url, string $cookie, ?array $post=null): array {
 $headers=[]; $opts=['http'=>['method'=>$post===null?'GET':'POST','ignore_errors'=>true,'header'=>"Cookie: ".($GLOBALS['sessionCookie']??'')."\r\nContent-Type: application/x-www-form-urlencoded\r\n",'content'=>$post===null?'':http_build_query($post),'timeout'=>5]];
 $body=@file_get_contents($url,false,stream_context_create($opts));
 if ($body===false) throw new RuntimeException('Server not reachable: run php -S first');
 $headers=$http_response_header??[];
 foreach($headers as $h) if (preg_match('/^Set-Cookie:\s*(PHPSESSID=[^;]+)/i',$h,$m)) $GLOBALS['sessionCookie']=$m[1];
 preg_match('/^HTTP\/\S+\s+(\d+)/',$headers[0]??'',$m);
 return [(int)($m[1]??0),$body];
}
$fail=0;
function check(bool $ok,string $label): void {global $fail;echo ($ok?'[OK] ':'[FAIL] ').$label.PHP_EOL;if(!$ok)$fail++;}
try {
 [$status,$body]=request($url,$cookie);
 check($status===200,'GET returns HTTP 200');
 check(str_contains($body,'Nuovo programma MFO'),'Real create form rendered');
 check((bool)preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$body,$m),'CSRF token generated');
 $token=$m[1]??'';
 [$status,$body]=request($url,$cookie,['csrf_token'=>'invalid','title'=>'Test','start'=>'2026-11-01T10:00','description'=>'{}']);
 check($status===200 && str_contains($body,'Sessione del modulo scaduta'),'Invalid CSRF rejected');
 [$status,$body]=request($url,$cookie,['csrf_token'=>$token,'title'=>'Test','start'=>'2026-11-01T10:00','description'=>'{}']);
 check($status===200 && str_contains($body,'documento EditorJS JSON'),'Invalid EditorJS document rejected');
 // Valid create: fake VaBase returns 900001 and fake registry accepts it.
 [$status,$body]=request($url,$cookie);
 preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$body,$m);
 $valid=['csrf_token'=>$m[1]??'','title'=>'MFO HTTP fixture','start'=>'2026-11-01T10:00','end'=>'2026-11-30T10:00','active'=>'1','description'=>json_encode(['blocks'=>[['type'=>'paragraph','data'=>['text'=>'Test isolato']]]])];
 [$status,$body]=request($url,$cookie,$valid);
 check($status===302,'Valid creation redirects after mock registry INSERT');
 // A failed registry must display reconciliation warning and prevent a second form submission.
 $flag=__DIR__.'/runtime/public_html/admin/mfo/fail_registry.flag';
 file_put_contents($flag,'1');
 try {
  [$status,$body]=request($url,$cookie);
  preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$body,$m);
  $valid['csrf_token']=$m[1]??'';
  [$status,$body]=request($url,$cookie,$valid);
  check($status===200 && str_contains($body,'registrazione MFO non confermata'),'Failed registry reports reconciliation warning');
  check(!str_contains($body,'<form method="post">'),'Failed registry hides create form');
 } finally { @unlink($flag); }
 // Simulate a timeout after the VaBase request was dispatched.
 $apiFlag=__DIR__.'/runtime/public_html/proxy/ambiguous_api.flag';
 file_put_contents($apiFlag,'1');
 try {
  [$status,$body]=request($url,$cookie);
  preg_match('/name="csrf_token" value="([a-f0-9]{64})"/',$body,$m);
  $valid['csrf_token']=$m[1]??'';
  [$status,$body]=request($url,$cookie,$valid);
  check($status===200 && str_contains($body,'esito della creazione VaBase non verificabile'),'Ambiguous API outcome requires reconciliation');
  check(!str_contains($body,'<form method="post">'),'Ambiguous API outcome hides form');
  [$status,$body]=request($url,$cookie,$valid);
  check($status===200 && str_contains($body,'Creazione bloccata'),'Repeat POST rejected after ambiguous outcome');
 } finally { @unlink($apiFlag); }
} catch(Throwable $e){echo '[FAIL] '.$e->getMessage().PHP_EOL;$fail++;}
@unlink($cookie);
echo $fail?"FAIL ($fail)\n":"PASS (11 HTTP checks)\n";
exit($fail?1:0);
