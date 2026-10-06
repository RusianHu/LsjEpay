<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$checks = 0;
$failures = [];

function checkBoundary($ok, $label)
{
    global $checks, $failures;
    ++$checks;
    if (!$ok) {
        $failures[] = $label;
    }
}

function isolatedPhp($source)
{
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'log_errors=0'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated PHP check');
    }
    fwrite($pipes[0], $source);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        throw new RuntimeException('Isolated check failed: ' . substr($error, 0, 300));
    }
    return $output;
}

try {
    // Execute the real handler with only its database bootstrap replaced.
    $handler = file_get_contents($root . '/getshop.php');
    $handler = str_replace("require './includes/common.php';", '// Isolated bootstrap.',
        $handler, $replacements);
    if ($replacements !== 1) {
        throw new RuntimeException('Captcha handler bootstrap changed; review the fixture');
    }
    foreach ([false, null, 1, true] as $result) {
        $bootstrap = '<?php
function daddslashes($value) { return $value; }
function checkRefererHost() { return true; }
function verify_captcha4() { return ' . var_export($result, true) . '; }
function getDefendKey($pid, $trade) { return "FIXTURE_GRANT"; }
$_GET = ["act" => "captcha_verify"];
$_POST = ["pid" => "999", "trade_no" => "FIXTURE_NO_ORDER"];
?>';
        $output = isolatedPhp($bootstrap . $handler);
        $response = json_decode($output, true);
        if ($result === true) {
            checkBoundary(is_array($response) && $response['code'] === 0
                && isset($response['key']), 'Valid captcha can issue a grant');
        } else {
            checkBoundary(is_array($response) && $response['code'] === -1
                && strpos($output, 'FIXTURE_GRANT') === false
                && !isset($response['key']), 'Failed captcha cannot issue a grant');
        }
    }

    // Exercise all return states, including OpenSSL's truthy error value -1.
    $adapters = [
        ['plugins/adapay/inc/Build.class.php', 'AdaTools', 'rsaPublicKey'],
        ['plugins/suixingpay/inc/Suixingpay.class.php', 'Suixingpay', 'platform_public_key'],
        ['plugins/fuiou2/inc/PayService.class.php', 'PayService', 'platform_public_key'],
        ['plugins/umfpay/inc/UmfService.class.php', 'UmfService', 'platform_public_key'],
    ];
    foreach ($adapters as $adapter) {
        $source = preg_replace('/^\s*<\?php\s*/', '', file_get_contents($root . '/' . $adapter[0]), 1);
        foreach ([1, 0, -1, false] as $result) {
            $probe = '<?php namespace SignatureProbe;
function openssl_verify($data, $signature, $key, $algorithm = null) { return '
                . var_export($result, true) . '; }
function openssl_pkey_get_public($key) { return "fixture-key"; }
function openssl_get_publickey($key) { return "fixture-key"; }
' . $source . '
$class = new \ReflectionClass(' . var_export('SignatureProbe\\' . $adapter[1], true) . ');
$client = $class->newInstanceWithoutConstructor();
$property = $class->getProperty(' . var_export($adapter[2], true) . ');
$property->setAccessible(true);
$property->setValue($client, "FIXTURE_PUBLIC_KEY");
';
            if ($adapter[1] === 'AdaTools') {
                $probe .= '$client->rsaPublicKeyFilePath = "";
$value = $client->verifySign("c2ln", "fixture-data");';
            } else {
                $probe .= '$value = $client->verifySign(["sign" => "c2ln", "order" => "fixture"]);';
            }
            $probe .= 'echo $value ? "accepted" : "rejected";';
            checkBoundary(isolatedPhp($probe) === ($result === 1 ? 'accepted' : 'rejected'),
                $adapter[1] . ' rejects verification result ' . var_export($result, true));
        }
    }

    // Verify genuine signatures too, without using any provider credentials.
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => __DIR__ . '/openssl-fixture.cnf']);
    if (!$privateKey) {
        throw new RuntimeException('Cannot generate isolated RSA test key');
    }
    $publicPem = openssl_pkey_get_details($privateKey)['key'];
    $publicBody = preg_replace('/-----[^-]+-----|\s+/', '', $publicPem);
    foreach ($adapters as $adapter) {
        require_once $root . '/' . $adapter[0];
        $class = new ReflectionClass($adapter[1]);
        $client = $class->newInstanceWithoutConstructor();
        $property = $class->getProperty($adapter[2]);
        $property->setAccessible(true);
        $property->setValue($client, $adapter[1] === 'UmfService' ? $publicPem : $publicBody);
        $params = ['order' => 'fixture-order', 'amount' => '1.00'];
        if ($adapter[1] === 'AdaTools') {
            $client->rsaPublicKeyFilePath = '';
            $content = 'fixture-order-data';
        } else {
            $method = $class->getMethod('getSignContent');
            $method->setAccessible(true);
            $content = $method->invoke($client, $params);
        }
        $algorithm = $adapter[1] === 'PayService' ? OPENSSL_ALGO_MD5 : OPENSSL_ALGO_SHA1;
        if (!openssl_sign($content, $signature, $privateKey, $algorithm)) {
            throw new RuntimeException('Cannot sign isolated RSA fixture');
        }
        if ($adapter[1] === 'AdaTools') {
            checkBoundary($client->verifySign(base64_encode($signature), $content) === true, 'AdaPay valid RSA signature accepted');
            checkBoundary($client->verifySign(base64_encode($signature), $content . 'changed') === false, 'AdaPay modified payload rejected');
            checkBoundary($client->verifySign('%%%INVALID%%%', $content) === false, 'AdaPay malformed signature rejected');
        } else {
            $params['sign'] = base64_encode($signature);
            checkBoundary($client->verifySign($params) === true, $adapter[1] . ' valid RSA signature accepted');
            $params['amount'] = '2.00';
            checkBoundary($client->verifySign($params) === false, $adapter[1] . ' modified payload rejected');
            $params['sign'] = '%%%INVALID%%%';
            checkBoundary($client->verifySign($params) === false, $adapter[1] . ' malformed signature rejected');
        }
    }

    // The active Alipay SDK must reject unsigned success responses.
    require_once $root . '/includes/vendor/autoload.php';
    $alipay = new \Alipay\Aop\AopClient();
    $alipay->rsaPublicKey = $publicBody;
    $alipay->rsaPublicKeyFilePath = '';
    $verifyResponse = new ReflectionMethod($alipay, 'verifyResponse');
    $verifyResponse->setAccessible(true);
    $api = 'alipay.data.bill.accountlog.query';
    $node = 'alipay_data_bill_accountlog_query_response';
    $data = json_encode(['code' => '10000', 'detail_list' => []]);
    openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    $signed = new \Alipay\Aop\AlipayResponse('{"' . $node . '":' . $data
        . ',"sign":"' . base64_encode($signature) . '"}', $api);
    $verifyResponse->invoke($alipay, $signed);
    checkBoundary($signed->isSuccess(), 'Alipay genuinely signed success remains accepted');
    foreach ([
        '{"' . $node . '":' . $data . '}',
        '{"' . $node . '":' . $data . ',"sign":"AQ=="}',
    ] as $raw) {
        $rejected = false;
        try {
            $verifyResponse->invoke($alipay, new \Alipay\Aop\AlipayResponse($raw, $api));
        } catch (Exception $error) {
            $rejected = true;
        }
        checkBoundary($rejected, 'Alipay unsigned or forged success response is rejected');
    }

    require_once $root . '/includes/lib/Cache.php';
    class CacheWakeupFixture
    {
        public static $calls = 0;
        public function __wakeup() { ++self::$calls; }
    }
    class CacheDatabaseFixture
    {
        public $value;
        public function getColumn($sql, $params) { return $this->value; }
    }
    $DB = new CacheDatabaseFixture();
    $DB->value = serialize(['version' => '2053', 'syskey' => 'FIXTURE_CACHE_KEY',
        'unexpected_object' => new CacheWakeupFixture()]);
    $cached = (new \lib\Cache())->pre_fetch();
    checkBoundary(CacheWakeupFixture::$calls === 0, 'Config cache cannot instantiate serialized classes');
    checkBoundary($cached['syskey'] === 'FIXTURE_CACHE_KEY' && $cached['version'] === '2053',
        'Ordinary serialized config arrays remain readable');

    require_once $root . '/includes/functions.php';
    $values = [];
    for ($i = 0; $i < 100; ++$i) {
        $value = random(32);
        checkBoundary(strlen($value) === 32 && preg_match('/^[A-Za-z0-9]+$/D', $value),
            'Security key alphabet and length remain compatible');
        $values[$value] = true;
    }
    checkBoundary(count($values) === 100, 'Security key generator has no duplicate samples');
    checkBoundary(strlen(random(6, true)) === 6 && ctype_digit(random(6, true)),
        'Numeric verification codes remain numeric');

    $key = 'FIXTURE_KEY_NOT_USED_IN_PRODUCTION';
    $payload = "999\tfixture-session\t" . (time() + 600);
    $token = authcode($payload, 'ENCODE', $key);
    checkBoundary(authcode($token, 'DECODE', $key) === $payload, 'Existing token format still round trips');
    checkBoundary(authcode($token, 'DECODE', 'WRONG_FIXTURE_KEY') === '', 'Wrong token key is rejected');
    checkBoundary(authcode(substr($token, 0, -4), 'DECODE', $key) === '', 'Truncated token is rejected');

    // A magic numeric MD5 must not satisfy the administrator session comparison.
    $member = file_get_contents($root . '/includes/member.php');
    foreach (['0', md5('240610708')] as $session) {
        $token = authcode("240610708\t" . $session . "\t" . (time() + 600), 'ENCODE', $key);
        $bootstrap = '<?php
define("SYS_KEY", ' . var_export($key, true) . ');
require ' . var_export($root . '/includes/functions.php', true) . ';
$conf = ["admin_user" => "240610708", "admin_pwd" => "", "ip_type" => 2];
$password_hash = "";
$_SERVER["REMOTE_ADDR"] = "127.0.0.1";
$_COOKIE = ["admin_token" => ' . var_export($token, true) . '];
?>';
        $output = isolatedPhp($bootstrap . $member . '<?php echo empty($islogin) ? "rejected" : "accepted";');
        checkBoundary($output === ($session === '0' ? 'rejected' : 'accepted'),
            'Administrator session uses an exact digest comparison');
    }

    $verifier = $root . '/plugins/paypal/inc/WebhookCertificate.php';
    if (is_file($verifier)) {
        require_once $verifier;
        foreach ([
            'https://api.paypal.com/v1/notifications/certs/CERT-fixture',
            'https://api.sandbox.paypal.com/v1/notifications/certs/CERT-fixture',
            'https://api-m.paypal.com/v1/notifications/certs/CERT-fixture',
        ] as $url) {
            checkBoundary(PayPalWebhookCertificate::isTrustedUrl($url), 'Official PayPal certificate URL accepted');
        }
        foreach ([
            'http://api.paypal.com/v1/notifications/certs/CERT-fixture',
            'https://api.paypal.com.evil.invalid/v1/notifications/certs/CERT-fixture',
            'https://evil.invalid/v1/notifications/certs/CERT-fixture',
            'https://api.paypal.com@127.0.0.1/v1/notifications/certs/CERT-fixture',
            'https://api.paypal.com:8443/v1/notifications/certs/CERT-fixture',
            'https://api.paypal.com/v1/notifications/certs/../fixture',
            'https://api.paypal.com/v1/notifications/certs/CERT-fixture?redirect=1',
            'file:///etc/passwd',
        ] as $url) {
            checkBoundary(!PayPalWebhookCertificate::isTrustedUrl($url), 'Untrusted webhook certificate URL rejected');
        }
    } else {
        checkBoundary(false, 'Webhook certificate URL policy exists');
    }
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
}

if ($failures) {
    foreach ($failures as $failure) {
        fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    }
    exit(1);
}
echo 'PASS: ' . $checks . " security boundary checks; no business database or provider calls.\n";
