<?php
// Run with: php tests/security-autoload.php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$secret = 'REGRESSION_ONLY_NOT_A_REAL_SYSKEY';
$temporary = sys_get_temp_dir() . '/lsjepay-security-' . bin2hex(random_bytes(8));
$process = null;
$pipes = [];
$failure = null;

try {
    if (!mkdir($temporary, 0700)) {
        throw new RuntimeException('Cannot create isolated test directory');
    }
    $autoload = var_export($root . '/includes/vendor/autoload.php', true);
    $dummy = var_export($secret, true);
    $fixture = '<?php
error_reporting(E_ERROR | E_PARSE);
$requestHeaders = $_SERVER;
$conf = ["syskey" => ' . $dummy . '];
define("SYS_KEY", $conf["syskey"]);
session_start();
$loader = require ' . $autoload . ';
header("Content-Type: text/plain");
echo get_class($loader) . "\n" . (class_exists("Alipay\\\\AlipayService") ? "sdk-loaded" : "sdk-missing");
';
    file_put_contents($temporary . '/index.php', $fixture);

    $listener = stream_socket_server('tcp://127.0.0.1:0', $error, $message);
    if (!$listener) {
        throw new RuntimeException('Cannot allocate a loopback test port');
    }
    $address = stream_socket_get_name($listener, false);
    fclose($listener);
    $process = proc_open([
        PHP_BINARY, '-n', '-d', 'display_errors=0', '-d', 'log_errors=1',
        '-d', 'session.save_path=' . $temporary, '-S', $address, '-t', $temporary,
    ], [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'],
        2 => ['file', $temporary . '/server.log', 'a']], $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start isolated PHP server');
    }
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 100; ++$attempt) {
        $socket = @stream_socket_client('tcp://' . $address, $error, $message, 0.1);
        if ($socket) {
            fclose($socket);
            $ready = true;
            break;
        }
        if (!proc_get_status($process)['running']) {
            break;
        }
        usleep(50000);
    }
    if (!$ready) {
        throw new RuntimeException('Isolated PHP server did not become ready');
    }

    $cases = ['', 'PHP-Version: 70199', 'PHP-Version: 70200',
        'PHP-Version: 999999', 'PHP-Version: 8.4.14', 'PHP-Version: 1e10',
        'php-version: 70200', 'PHP-VERSION: 70200', 'PHP-Version: arbitrary'];
    foreach ($cases as $header) {
        $context = stream_context_create(['http' => [
            'header' => 'User-Agent: LsjEpay-Security-Regression' . "\r\n" . $header,
            'timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0,
        ]]);
        $body = file_get_contents('http://' . $address . '/', false, $context);
        $headers = isset($http_response_header) ? $http_response_header : [];
        if (strpos(implode("\n", $headers) . $body, $secret) !== false) {
            throw new RuntimeException('Secret leaked for ' . ($header ?: 'ordinary request'));
        }
        if (!$headers || !preg_match('/^HTTP\/\S+ 200\b/', $headers[0])
            || $body !== "Composer\\Autoload\\ClassLoader\nsdk-loaded") {
            throw new RuntimeException('Autoload or SDK smoke check failed for ' . ($header ?: 'ordinary request'));
        }
        $sessionCookie = false;
        foreach ($headers as $responseHeader) {
            if (preg_match('/^Set-Cookie:\s*PHPSESSID=([^;\s]+)/i', $responseHeader)) {
                $sessionCookie = true;
            }
        }
        if (!$sessionCookie) {
            throw new RuntimeException('Ordinary session cookie missing');
        }
    }
    echo 'PASS: ' . count($cases) . " HTTP cases; no system key disclosure; sessions and SDK autoload work.\n";
} catch (Throwable $error) {
    $failure = $error->getMessage();
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        for ($attempt = 0; $attempt < 50 && proc_get_status($process)['running']; ++$attempt) {
            usleep(20000);
        }
        proc_close($process);
    }
    if (is_dir($temporary)) {
        foreach (glob($temporary . '/*') as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($temporary);
    }
}
if ($failure !== null) {
    fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    exit(1);
}
