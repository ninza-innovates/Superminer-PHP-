<?php
/**
 * A failed WhatToMine refresh must keep a saved coin list and renew its timer.
 * The built-in list is written only when the cache is missing or not a list.
 * Run from any working directory.
 */
$root = dirname(__DIR__);
$mode = getenv('SUPERMINER_CACHE_REFRESH_CHECK');
if (is_string($mode) && $mode !== '') {
    require_once $root . '/includes/api.php';
    $cacheFile = CONFIG_DIR . '/coins.json';
    $force = $mode === 'force';
    $coins = fetchMiningData($force);
    clearstatcache(true, $cacheFile);
    echo json_encode([
        'coins' => $coins,
        'raw' => file_get_contents($cacheFile),
        'mtime' => filemtime($cacheFile),
    ]);
    exit;
}

$envFile = $root . '/.env';
$envExisted = is_file($envFile);
$originalEnv = $envExisted ? file_get_contents($envFile) : null;
if (!$envExisted) {
    if (!copy($root . '/.env.example', $envFile)) {
        fwrite(STDERR, "Could not create .env from .env.example\n");
        exit(1);
    }
}

require_once $root . '/includes/api.php';

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

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function with_cache($initial, $fn) {
    $dir = sys_get_temp_dir() . '/superminer-cache-' . bin2hex(random_bytes(4));
    if (!mkdir($dir) && !is_dir($dir)) {
        throw new RuntimeException('Could not create temp cache dir');
    }
    $file = $dir . '/coins.json';
    try {
        if ($initial !== null && file_put_contents($file, $initial) === false) {
            throw new RuntimeException('Could not write temp cache');
        }
        $fn($file);
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
        rmdir($dir);
    }
}

function stamp_cache($file, $mtime) {
    if (touch($file, $mtime) === false) {
        throw new RuntimeException('Could not stamp temp cache');
    }
    clearstatcache(true, $file);
}

function assert_kept($file, $saved, $data, $httpCode, $message) {
    stamp_cache($file, 1600000000);
    $coins = applyMiningResponse($file, $data, $httpCode);
    clearstatcache(true, $file);
    assert_same(file_get_contents($file), $saved, $message . ' rewrote the cache');
    assert_same($coins, json_decode($saved, true), $message . ' did not return the saved list');
    $mtime = filemtime($file);
    assert_true($mtime !== false && $mtime >= time() - 5, $message . ' did not renew the cache timer');
}

$saved = "{\"Firo\":{\"profitability\":\"1.2869\",\"difficulty\":345.46}}\n";
$savedCoins = json_decode($saved, true);

with_cache($saved, function ($file) use ($saved) {
    assert_kept($file, $saved, false, 0, 'transport failure');
    assert_kept($file, $saved, false, 200, 'empty body with status 200');
    assert_kept($file, $saved, 'not-json', 200, 'invalid JSON body');
    assert_kept($file, $saved, '{"coins":"nope"}', 200, 'coins value that is not a list');
    assert_kept($file, $saved, '{"other":[]}', 500, 'status 500');
    assert_kept($file, $saved, '{"coins":{"Firo":{}}}', '200', 'string status 200');
});

with_cache('[]', function ($file) {
    assert_kept($file, '[]', false, 503, 'empty saved list');
});

$feed = [
    'Low' => [
        'id' => 1,
        'tag' => 'LOW',
        'algorithm' => 'Sha',
        'profitability' => 1,
        'estimated_rewards' => '0.1',
        'difficulty' => 2,
    ],
    'Firo' => [
        'id' => 175,
        'tag' => 'FIRO',
        'algorithm' => 'FiroPow',
        'profitability' => 80,
        'estimated_rewards' => '1.14904',
        'difficulty' => 1000,
    ],
    'Skip' => 'nope',
];
$expected = selectTopCoins($feed);
$expectedRaw = json_encode($expected, JSON_PRETTY_PRINT);

with_cache($saved, function ($file) use ($feed, $expected, $expectedRaw, $saved) {
    stamp_cache($file, 1600000000);
    $coins = applyMiningResponse($file, json_encode(['coins' => $feed]), 200);
    clearstatcache(true, $file);
    assert_same($coins, $expected, 'successful response did not return the ranked coins');
    assert_same(file_get_contents($file), $expectedRaw, 'successful response did not replace the cache');
    assert_true(file_get_contents($file) !== $saved, 'successful response left the previous cache in place');
    $mtime = filemtime($file);
    assert_true($mtime !== false && $mtime >= time() - 5, 'successful response did not update the cache timer');
});

with_cache($saved, function ($file) {
    $coins = applyMiningResponse($file, json_encode(['coins' => []]), 200);
    assert_same($coins, [], 'empty coin list should replace the cache');
    assert_same(file_get_contents($file), json_encode([], JSON_PRETTY_PRINT), 'empty coin list was not stored');
});

// A nested tag used to throw and skip the saved-list fallback. An overflowing
// number used to make json_encode() fail and blank the cache file.
$malformedBody = json_encode([
    'coins' => [
        'BadTag' => [
            'id' => 9,
            'tag' => ['nope'],
            'algorithm' => 'Sha',
            'profitability' => 1000,
            'estimated_rewards' => '9',
            'difficulty' => 4,
        ],
        'Ok' => [
            'id' => 2,
            'tag' => 'OK',
            'algorithm' => 'Sha',
            'profitability' => 5,
            'estimated_rewards' => '0.5',
            'difficulty' => 3,
        ],
    ],
]);
$overflowBody = '{"coins":{"Huge":{"id":1,"tag":"H","algorithm":"Sha","profitability":1e309,"estimated_rewards":1e309,"difficulty":1e309},"Ok":{"id":2,"tag":"OK","algorithm":"Sha","profitability":5,"estimated_rewards":"0.5","difficulty":3}}}';

with_cache($saved, function ($file) use ($saved, $malformedBody, $overflowBody) {
    $coins = applyMiningResponse($file, $malformedBody, 200);
    $raw = file_get_contents($file);
    assert_same($coins, selectTopCoins(json_decode($malformedBody, true)['coins']), 'malformed coin aborted the refresh');
    assert_same($raw, json_encode($coins, JSON_PRETTY_PRINT), 'malformed coin did not store the ranked list');
    assert_same($coins['BadTag']['link'], null, 'nested tag was written into the cache as a link');
    assert_true($raw !== '' && $raw !== $saved, 'malformed refresh left the previous cache or blanked the file');

    $overflow = applyMiningResponse($file, $overflowBody, 200);
    $overflowRaw = file_get_contents($file);
    assert_true($overflowRaw !== '' && json_decode($overflowRaw, true) === $overflow, 'overflowing coin blanked the cache');
    assert_same(array_keys($overflow), ['Ok', 'Huge'], 'overflowing score outranked a real coin');
    assert_same($overflow['Huge']['profitability'], null, 'overflowing reward was stored');
    assert_same($overflow['Huge']['difficulty'], null, 'overflowing difficulty was stored');
    assert_same($overflow['Ok']['link'], WHATTOMINE_URL . '/coins/2-ok-sha', 'valid coin link was dropped after an overflow');
});

$fallback = whattomineFallbackCoins();
$fallbackRaw = json_encode($fallback, JSON_PRETTY_PRINT);

with_cache(null, function ($file) use ($fallback, $fallbackRaw) {
    $coins = applyMiningResponse($file, false, 0);
    assert_same($coins, $fallback, 'missing cache should use the built-in list');
    assert_same(file_get_contents($file), $fallbackRaw, 'missing cache should store the built-in list');
});

with_cache('{', function ($file) use ($fallback, $fallbackRaw) {
    $coins = applyMiningResponse($file, 'not-json', 200);
    assert_same($coins, $fallback, 'invalid cache should use the built-in list');
    assert_same(file_get_contents($file), $fallbackRaw, 'invalid cache should store the built-in list');
});

$cacheFile = $root . '/config/coins.json';
$originalCache = file_get_contents($cacheFile);
$originalMtime = filemtime($cacheFile);
if ($originalCache === false || $originalMtime === false) {
    fwrite(STDERR, "Could not read config/coins.json\n");
    exit(1);
}

function write_dead_env() {
    global $root, $envFile;
    $example = file_get_contents($root . '/.env.example');
    if ($example === false) {
        throw new RuntimeException('Could not read .env.example');
    }
    $needle = 'API_WHATTOMINE_URL="https://whattomine.com/coins.json"';
    $dead = str_replace($needle, 'API_WHATTOMINE_URL="http://127.0.0.1:9/"', $example);
    if ($dead === $example || file_put_contents($envFile, $dead) === false) {
        throw new RuntimeException('Could not retarget API_WHATTOMINE_URL');
    }
}

function probe_fetch($mode) {
    global $root;
    $env = [];
    foreach (getenv() as $key => $value) {
        if (is_string($key) && is_string($value)) {
            $env[$key] = $value;
        }
    }
    $env['SUPERMINER_CACHE_REFRESH_CHECK'] = $mode;
    $command = [PHP_BINARY, '-d', 'display_errors=stderr', __FILE__];
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($command, $descriptors, $pipes, $root, $env);
    if (!is_resource($proc)) {
        throw new RuntimeException('Could not start PHP for ' . $mode);
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 || $stderr !== '') {
        throw new RuntimeException($mode . " probe failed ($code): " . $stderr . $stdout);
    }
    $decoded = json_decode($stdout, true);
    if (!is_array($decoded)) {
        throw new RuntimeException($mode . ' probe returned ' . $stdout);
    }
    return $decoded;
}

try {
    write_dead_env();
    if (file_put_contents($cacheFile, $saved) === false) {
        throw new RuntimeException('Could not write saved coin cache');
    }
    stamp_cache($cacheFile, 1600000000);

    foreach (['force', 'stale'] as $mode) {
        stamp_cache($cacheFile, 1600000000);
        $probed = probe_fetch($mode);
        clearstatcache(true, $cacheFile);
        assert_same($probed['coins'], $savedCoins, "$mode refresh did not return the saved list");
        assert_same($probed['raw'], $saved, "$mode refresh rewrote the cache");
        assert_same(file_get_contents($cacheFile), $saved, "$mode refresh changed the cache on disk");
        assert_true(
            is_int($probed['mtime']) && $probed['mtime'] >= time() - 5,
            "$mode refresh did not renew the cache timer"
        );
    }

    if (file_put_contents($cacheFile, '{') === false) {
        throw new RuntimeException('Could not write invalid coin cache');
    }
    stamp_cache($cacheFile, 1600000000);
    $probed = probe_fetch('stale');
    clearstatcache(true, $cacheFile);
    assert_same($probed['coins'], $fallback, 'unusable cache should use the built-in list');
    assert_same($probed['raw'], $fallbackRaw, 'unusable cache should store the built-in list');
    assert_same(file_get_contents($cacheFile), $fallbackRaw, 'unusable cache was not replaced on disk');
} finally {
    if ($originalEnv === null) {
        if (is_file($envFile)) {
            unlink($envFile);
        }
    } else {
        file_put_contents($envFile, $originalEnv);
    }
    file_put_contents($cacheFile, $originalCache);
    if ($originalMtime !== false) {
        touch($cacheFile, $originalMtime);
    }
}

if ($failed) {
    exit(1);
}

echo "ok\n";
