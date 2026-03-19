<?php
// /login/facebook/callback.php
declare(strict_types=1); session_start();

/* ==== CONFIG ==== */
const FB_APP_ID     = 'YOUR_APP_ID';
const FB_APP_SECRET = 'YOUR_APP_SECRET';
const FB_REDIRECT   = 'https://vogo.family/login/facebook/callback.php';   // exact in Meta
const FRONT_REDIRECT= 'https://vogo.family/auth/complete';                  // final URL
const VOGO_ENDPOINT = 'https://vogo.family/wp-json/vogo/v1/social/login';  // backend route

/* ==== HTTP ====/ */
function get_json(string $url): array {
  $c=curl_init($url);
  curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>20, CURLOPT_HTTPHEADER=>['Accept: application/json']]);
  $r=curl_exec($c); if($r===false) throw new Exception('HTTP '.curl_error($c));
  $s=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
  $j=json_decode($r,true)?:[]; if($s>=400) throw new Exception('HTTP '.$s.': '.($j['error']['message']??'unknown'));
  return $j;
}
function post_json(string $url, array $data): array {
  $c=curl_init($url);
  curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>20,
    CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
  $r=curl_exec($c); if($r===false) throw new Exception('HTTP '.curl_error($c));
  $s=curl_getinfo($c,CURLINFO_HTTP_CODE); curl_close($c);
  $j=json_decode($r,true)?:[]; if($s>=400 || (isset($j['success']) && !$j['success'])) throw new Exception($j['error']??$j['message']??('HTTP '.$s));
  return $j;
}
function redirect(string $u){ header('Location: '.$u); exit; }

/* ==== OAUTH ERRORS ==== */
if(isset($_GET['error'])) redirect(FRONT_REDIRECT.'?provider=facebook&status=error&reason='.urlencode($_GET['error_description']??$_GET['error']));
if(!isset($_GET['state'],$_SESSION['fb_oauth_state']) || $_GET['state']!==$_SESSION['fb_oauth_state'])
  redirect(FRONT_REDIRECT.'?provider=facebook&status=error&reason=bad_state');
$code=$_GET['code']??''; if(!$code) redirect(FRONT_REDIRECT.'?provider=facebook&status=error&reason=no_code');

try{
  /* exchange code → access_token */
  $tok=get_json('https://graph.facebook.com/v18.0/oauth/access_token?client_id='
    .rawurlencode(FB_APP_ID).'&redirect_uri='.rawurlencode(FB_REDIRECT)
    .'&client_secret='.rawurlencode(FB_APP_SECRET).'&code='.rawurlencode($code));
  $at=$tok['access_token']??''; if(!$at) throw new Exception('no_access_token');

  /* get user profile */
  $me=get_json('https://graph.facebook.com/v18.0/me?fields=id,name,email,picture.type(normal)&access_token='.rawurlencode($at));
  $fbid=$me['id']??''; if(!$fbid) throw new Exception('no_user');
  $name=$me['name']??''; $email=$me['email']??''; $avatar=$me['picture']['data']['url']??'';

  /* handoff to VOGO → mint JWT */
  $res=post_json(VOGO_ENDPOINT,[
    'provider'=>'facebook','provider_sub'=>'fb_'.$fbid,'email'=>$email,'name'=>$name,'avatar'=>$avatar,
    'access_token'=>$at,'meta'=>['source'=>'web','sdk'=>'custom-callback']
  ]);
  $jwt=$res['jwt']??''; $uid=(int)($res['user_id']??0); if(!$jwt||!$uid) throw new Exception('missing_token');

  /* cookies */
  setcookie('vogo_jwt',$jwt,['expires'=>time()+28800,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
  setcookie('vogo_user',json_encode(['id'=>$uid,'login'=>$res['user_login']??'','email'=>$res['email']??'','provider'=>'facebook'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
    ['expires'=>time()+28800,'path'=>'/','secure'=>true,'httponly'=>false,'samesite'=>'Lax']);

  redirect(FRONT_REDIRECT.'?provider=facebook&status=success');
}catch(Throwable $e){
  redirect(FRONT_REDIRECT.'?provider=facebook&status=error&reason='.urlencode($e->getMessage()));
}
