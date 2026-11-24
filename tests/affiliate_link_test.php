<?php
/**
 * Footer affiliate hrefs must stay http(s) URLs. A javascript: or other
 * non-http value becomes "#", and a normal https URL is kept and escaped.
 * Pages read the constants once, so each case renders in a fresh process.
 * Run from any working directory.
 */
$root = dirname(__DIR__);
require_once $root . '/includes/html.php';

$failed = false;

function assert_true($condition, $message) {
    global $failed;
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        $failed = true;
    }
}

function assert_same($actual, $expected, $message) {
    assert_true($actual === $expected, $message . " (got " . var_export($actual, true) . ")");
}

function ini_quoted($value) {
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
}

function write_env(array $overrides) {
    global $root;
    $example = file_get_contents($root . '/.env.example');
    if ($example === false) {
        throw new RuntimeException('Could not read .env.example');
    }
    $lines = preg_split("/\r\n|\n|\r/", $example);
    $seen = [];
    foreach ($lines as $i => $line) {
        if (!preg_match('/^(AFFILIATE_AMAZON|AFFILIATE_NEWEGG)=/', $line, $matches)) {
            continue;
        }
        $key = $matches[1];
        if (!array_key_exists($key, $overrides)) {
            continue;
        }
        $lines[$i] = $key . '=' . ini_quoted($overrides[$key]);
        $seen[$key] = true;
    }
    foreach (array_keys($overrides) as $key) {
        if (!isset($seen[$key])) {
            throw new RuntimeException("Missing $key in .env.example");
        }
    }
    $text = implode("\n", $lines);
    if ($text === '' || substr($text, -1) !== "\n") {
        $text .= "\n";
    }
    if (file_put_contents($root . '/.env', $text) === false) {
        throw new RuntimeException('Could not write .env');
    }
}

function render_public($script) {
    global $root, $cacheFile;
    if ($script === 'index.php') {
        touch($cacheFile);
    }
    $command = [PHP_BINARY, '-d', 'display_errors=stderr', $root . '/public/' . $script];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($command, $descriptors, $pipes, $root . '/public');
    if (!is_resource($proc)) {
        throw new RuntimeException('Could not start PHP for ' . $script);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 || $stderr !== '') {
        throw new RuntimeException($script . " failed ($code): " . $stderr);
    }
    return $stdout;
}

function affiliate_href($html, $label) {
    $pattern = '/<a href="([^"]*)" class="text-white">' . preg_quote($label, '/') . '<\/a>/';
    if (!preg_match($pattern, $html, $matches)) {
        return null;
    }
    return $matches[1];
}

function expected_href($url) {
    return htmlspecialchars(httpUrlOrHash($url), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function assert_affiliate($html, $label, $url, $message) {
    $href = affiliate_href($html, $label);
    assert_same($href, expected_href($url), $message);
}

$envFile = $root . '/.env';
$originalEnv = is_file($envFile) ? file_get_contents($envFile) : null;
$cacheFile = $root . '/config/coins.json';
$originalCache = file_get_contents($cacheFile);
$originalMtime = filemtime($cacheFile);
if ($originalCache === false || $originalMtime === false) {
    fwrite(STDERR, "Could not read config/coins.json\n");
    exit(1);
}

$pages = ['index.php', 'calculator.php'];
$safeAmazon = 'https://www.amazon.com/s?k=miner&tag=abc';
$safeNewegg = 'https://www.newegg.com/p?item=1&cm_sp=x';
$scriptAmazon = 'javascript:alert(1)';
$ftpNewegg = 'ftp://files.example/miner';
$breakoutNewegg = 'https://www.newegg.com/x" onmouseover="alert(1)';

try {
    write_env([]);
    foreach ($pages as $script) {
        $html = render_public($script);
        assert_affiliate($html, 'Buy on Amazon', 'https://www.amazon.com/', "$script dropped the example Amazon link");
        assert_affiliate($html, 'Buy on Newegg', 'https://www.newegg.com/', "$script dropped the example Newegg link");
    }

    write_env([
        'AFFILIATE_AMAZON' => $safeAmazon,
        'AFFILIATE_NEWEGG' => $safeNewegg,
    ]);
    foreach ($pages as $script) {
        $html = render_public($script);
        assert_affiliate($html, 'Buy on Amazon', $safeAmazon, "$script did not keep and escape the Amazon URL");
        assert_affiliate($html, 'Buy on Newegg', $safeNewegg, "$script did not keep and escape the Newegg URL");
        assert_true(strpos($html, $safeAmazon) === false, "$script emitted a raw ampersand in the Amazon URL");
        assert_true(strpos($html, $safeNewegg) === false, "$script emitted a raw ampersand in the Newegg URL");
        assert_true(
            strpos($html, 'href="' . expected_href($safeAmazon) . '"') !== false,
            "$script Amazon href is missing the escaped URL"
        );
    }

    write_env([
        'AFFILIATE_AMAZON' => $scriptAmazon,
        'AFFILIATE_NEWEGG' => $ftpNewegg,
    ]);
    foreach ($pages as $script) {
        $html = render_public($script);
        assert_affiliate($html, 'Buy on Amazon', $scriptAmazon, "$script kept a javascript affiliate URL");
        assert_affiliate($html, 'Buy on Newegg', $ftpNewegg, "$script kept a non-http affiliate URL");
        assert_true(strpos($html, 'javascript:') === false, "$script emitted a javascript affiliate URL");
        assert_true(strpos($html, 'ftp:') === false, "$script emitted a non-http affiliate URL");
        assert_same(affiliate_href($html, 'Buy on Amazon'), '#', "$script Amazon href should be a hash");
        assert_same(affiliate_href($html, 'Buy on Newegg'), '#', "$script Newegg href should be a hash");
    }

    write_env([
        'AFFILIATE_AMAZON' => '',
        'AFFILIATE_NEWEGG' => $breakoutNewegg,
    ]);
    foreach ($pages as $script) {
        $html = render_public($script);
        assert_affiliate($html, 'Buy on Amazon', '', "$script kept an empty affiliate URL");
        assert_affiliate($html, 'Buy on Newegg', $breakoutNewegg, "$script kept a quote in an affiliate URL");
        assert_true(strpos($html, 'onmouseover="') === false, "$script affiliate URL broke out of the attribute");
        assert_true(strpos($html, $breakoutNewegg) === false, "$script emitted the raw breakout affiliate URL");
    }
} finally {
    if ($originalEnv === null) {
        if (is_file($envFile)) {
            unlink($envFile);
        }
    } else {
        file_put_contents($envFile, $originalEnv);
    }
    file_put_contents($cacheFile, $originalCache);
    touch($cacheFile, $originalMtime);
}

if ($failed) {
    exit(1);
}

echo "ok\n";
