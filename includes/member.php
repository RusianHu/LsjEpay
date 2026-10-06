<?php
$clientip=real_ip($conf['ip_type']?$conf['ip_type']:0);
$islogin = 0;
$islogin2 = 0;

if(isset($_COOKIE["admin_token"]) && is_string($_COOKIE["admin_token"]))
{
	$token=authcode(daddslashes($_COOKIE['admin_token']), 'DECODE', SYS_KEY);
	$parts = explode("\t", $token);
	if (count($parts) === 3) {
		list($user, $sid, $expiretime) = $parts;
		$session=md5($conf['admin_user'].$conf['admin_pwd'].$password_hash);
		if($user === $conf['admin_user'] && hash_equals($session, $sid) && ctype_digit($expiretime) && $expiretime>time()) {
			$islogin=1;
		}
	}
}
if(isset($_COOKIE["user_token"]) && is_string($_COOKIE["user_token"]))
{
	$token=authcode(daddslashes($_COOKIE['user_token']), 'DECODE', SYS_KEY);
	$parts = explode("\t", $token);
	if (count($parts) === 3 && ctype_digit($parts[0])) {
		list($uid, $sid, $expiretime) = $parts;
		$uid = intval($uid);
		$userrow=$DB->getRow("SELECT * FROM pre_user WHERE uid=:uid limit 1", [':uid'=>$uid]);
		if ($userrow) {
			$session=md5($userrow['uid'].$userrow['key'].$password_hash);
			if(hash_equals($session, $sid) && ctype_digit($expiretime) && $expiretime>time()) {
				$islogin2=1;
			}
		}
	}
}
?>
