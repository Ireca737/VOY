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
} catch(Throwable $e){echo '[FAIL] '.$e->getMessage().PHP_EOL;$fail++;}
@unlink($cookie);
echo $fail?"FAIL ($fail)\n":"PASS (5 HTTP checks)\n";
exit($fail?1:0);
