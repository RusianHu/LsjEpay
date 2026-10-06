<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$manifest = json_decode(file_get_contents($root . '/deploy/reviewed-source.json'), true);
$failures = [];
if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
    fwrite(STDERR, "FAIL: Reviewed source manifest unavailable.\n");
    exit(1);
}
foreach ($manifest['files'] as $relative => $expected) {
    $path = $root . '/' . $relative;
    if (!is_file($path) || is_link($path)
        || hash('sha256', str_replace("\r\n", "\n", file_get_contents($path))) !== $expected) {
        $failures[] = 'Reviewed dependency or bootstrap changed: ' . $relative;
    }
}

$process = proc_open(['git', '-C', $root, 'ls-files', '-z'],
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "FAIL: Cannot enumerate tracked source.\n");
    exit(1);
}
fclose($pipes[0]);
$files = explode("\0", stream_get_contents($pipes[1]));
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) !== 0) {
    fwrite(STDERR, "FAIL: Source inventory failed.\n");
    exit(1);
}

$nameTokens = [T_STRING];
foreach (['T_NAME_FULLY_QUALIFIED', 'T_NAME_QUALIFIED'] as $tokenName) {
    if (defined($tokenName)) $nameTokens[] = constant($tokenName);
}
$forbidden = ['shell_exec', 'exec', 'system', 'passthru', 'popen', 'proc_open',
    'pcntl_exec', 'create_function'];
$phpCount = 0;
foreach ($files as $relative) {
    if ($relative === '' || strpos($relative, 'tests/') === 0 || strpos($relative, 'php/') === 0) {
        continue;
    }
    if (preg_match('/\.(?:phtml|php[3-9]|phar|dll|so)$/i', $relative)) {
        $failures[] = 'Unreviewed executable artifact: ' . $relative;
    }
    if (substr($relative, -4) !== '.php') continue;
    ++$phpCount;
    $vendored = strpos($relative, 'includes/vendor/') === 0
        || strpos($relative, 'includes/lib/mail/PHPMailer/') === 0;
    if ($vendored && !isset($manifest['files'][$relative])) {
        $failures[] = 'New dependency PHP file needs review: ' . $relative;
    }
    $source = file_get_contents($root . '/' . $relative);
    if (preg_match('/HTTP_PHP_VERSION|SERVER_PHP_VERSION|SENTENCEIA|Set-Cookie:\s*PHPSESSID=/i', $source)) {
        $failures[] = 'System-key disclosure marker: ' . $relative;
    }
    try {
        $tokens = token_get_all($source, TOKEN_PARSE);
    } catch (ParseError $error) {
        $failures[] = 'PHP syntax check failed: ' . $relative;
        continue;
    }
    foreach ($tokens as $index => $token) {
        if (!is_array($token)) continue;
        if ($token[0] === T_EVAL) {
            $failures[] = 'PHP eval requires explicit security review: ' . $relative;
        }
        if (strpos($relative, 'includes/vendor/composer/') === 0 && $token[0] === T_VARIABLE
            && in_array($token[1], ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SERVER', '$_FILES'], true)) {
            $failures[] = 'Request input inside Composer bootstrap: ' . $relative;
        }
        if (!in_array($token[0], $nameTokens, true)) continue;
        $name = strtolower(ltrim($token[1], '\\'));
        if (!in_array($name, $forbidden, true) && $name !== 'assert') continue;
        $next = $index + 1;
        while (isset($tokens[$next]) && is_array($tokens[$next])
            && in_array($tokens[$next][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) ++$next;
        $previous = $index - 1;
        while ($previous >= 0 && is_array($tokens[$previous])
            && in_array($tokens[$previous][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) --$previous;
        if (!isset($tokens[$next]) || $tokens[$next] !== '('
            || ($previous >= 0 && is_array($tokens[$previous])
                && in_array($tokens[$previous][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true))) continue;
        // Existing audited mail transport and ASN.1 assertions are hash-pinned above.
        if ($name === 'popen' && $relative === 'includes/lib/mail/PHPMailer/PHPMailer.php') continue;
        if ($name === 'assert' && strpos($relative, 'includes/vendor/fgrosse/phpasn1/') === 0) continue;
        $failures[] = 'Executable call requires explicit security review: ' . $relative . ':' . $token[2];
    }
}

if ($failures) {
    foreach (array_unique($failures) as $failure) fwrite(STDERR, 'FAIL: ' . $failure . "\n");
    exit(1);
}
echo 'PASS: ' . $phpCount . ' runtime PHP files scanned; ' . count($manifest['files'])
    . " reviewed dependency/bootstrap hashes match.\n";
