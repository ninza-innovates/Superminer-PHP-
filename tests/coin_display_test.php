<?php
/**
 * The home page should keep rendering when config/coins.json is unreadable
 * or a coin stat is not a number. Run from any working directory.
 */
$root = dirname(__DIR__);
if (!is_file($root . '/.env')) {
    if (!copy($root . '/.env.example', $root . '/.env')) {
        fwrite(STDERR, "Could not create .env from .env.example\n");
        exit(1);
    }
}

require_once $root . '/includes/functions.php';
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

$cacheFile = $root . '/config/coins.json';
$original = file_get_contents($cacheFile);
if ($original === false) {
    fwrite(STDERR, "Could not read config/coins.json\n");
    exit(1);
}
$originalMtime = filemtime($cacheFile);
$originalCoins = json_decode($original, true);
if (!is_array($originalCoins) || !isset($originalCoins['Grin-CT32'], $originalCoins['Firo'])) {
    fwrite(STDERR, "config/coins.json is missing Grin-CT32 or Firo\n");
    exit(1);
}

foreach ($originalCoins as $name => $data) {
    assert_same(
        formatCoinNumber($data['profitability'], 5),
        number_format($data['profitability'], 5),
        "$name profitability formatting changed"
    );
    assert_same(
        formatCoinNumber($data['difficulty'], 10),
        number_format($data['difficulty'], 10),
        "$name difficulty formatting changed"
    );
}

assert_same(formatCoinNumber('9"><script>', 5), null, 'markup profitability should not be formatted');
assert_same(formatCoinNumber('10"><b>', 10), null, 'markup difficulty should not be formatted');
assert_same(formatCoinNumber(null, 5), null, 'missing stat should not be formatted');
assert_same(formatCoinNumber('  ', 5), null, 'blank stat should not be formatted');
assert_same(formatCoinNumber([], 5), null, 'array stat should not be formatted');
assert_same(formatCoinNumber(INF, 5), null, 'infinite stat should not be formatted');
assert_same(formatCoinNumber(NAN, 5), null, 'NAN stat should not be formatted');
assert_same(formatCoinNumber('1e2', 5), number_format(100.0, 5), 'scientific notation should still format');

function render_public($script) {
    global $root;
    $cwd = getcwd();
    chdir($root . '/public');
    ob_start();
    try {
        require $root . '/public/' . $script;
        return ob_get_clean();
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    } finally {
        if ($cwd !== false) {
            chdir($cwd);
        }
    }
}

function render_index() {
    try {
        return render_public('index.php');
    } catch (Throwable $e) {
        fwrite(STDERR, "index render failed: " . $e->getMessage() . "\n");
        return '';
    }
}

try {
    if (touch($cacheFile) === false) {
        throw new RuntimeException('Could not refresh coin cache mtime');
    }
    $home = render_index();
    $grinProfit = '$' . number_format($originalCoins['Grin-CT32']['profitability'], 5) . '/day';
    $firoProfit = '$' . number_format($originalCoins['Firo']['profitability'], 5) . '/day';
    $grinDifficulty = number_format($originalCoins['Grin-CT32']['difficulty'], 10);
    assert_true($home !== '', 'home page did not render the cached coin list');
    assert_true(strpos($home, 'Top 10 Coins') !== false, 'home page dropped the heading');
    assert_true(strpos($home, $grinProfit) !== false, "home page dropped Grin profit {$grinProfit}");
    assert_true(strpos($home, $firoProfit) !== false, "home page dropped Firo profit {$firoProfit}");
    assert_true(strpos($home, $grinDifficulty) !== false, "home page dropped Grin difficulty {$grinDifficulty}");
    assert_true(strpos($home, 'n/a') === false, 'cached numeric stats were shown as n/a');

    $hostile = [
        'OkCoin' => [
            'link' => 'https://example.com/a?b=1&c=2',
            'profitability' => '1.5',
            'difficulty' => 12,
        ],
        'MarkupCoin' => [
            'link' => 'javascript:alert(1)',
            'profitability' => '9"><script>',
            'difficulty' => '10"><b>',
        ],
        'Broken' => 'nope',
    ];
    if (file_put_contents($cacheFile, json_encode($hostile)) === false) {
        throw new RuntimeException('Could not write hostile coin cache');
    }
    touch($cacheFile);

    $bad = render_index();
    assert_true($bad !== '', 'home page fatals when a coin stat is not numeric');
    assert_true(strpos($bad, 'data-coin="OkCoin"') !== false, 'numeric coin was dropped');
    assert_true(strpos($bad, '$' . number_format(1.5, 5) . '/day') !== false, 'numeric profitability string was not formatted');
    assert_true(strpos($bad, number_format(12, 10)) !== false, 'numeric difficulty was not formatted');
    assert_true(
        strpos($bad, 'href="https://example.com/a?b=1&amp;c=2"') !== false,
        'home page did not escape an ampersand in a coin link'
    );
    assert_true(strpos($bad, 'data-coin="MarkupCoin"') !== false, 'coin with a bad stat was dropped');
    assert_true(substr_count($bad, '>n/a<') === 2, 'bad profitability and difficulty should both show n/a');
    assert_true(strpos($bad, '9"><script>') === false, 'raw profitability markup was rendered');
    assert_true(strpos($bad, '10"><b>') === false, 'raw difficulty markup was rendered');
    assert_true(strpos($bad, 'javascript:') === false, 'javascript coin link was kept');
    assert_true(strpos($bad, 'data-coin="Broken"') === false, 'non-array coin entry was rendered');

    if (file_put_contents($cacheFile, '{') === false) {
        throw new RuntimeException('Could not write invalid coin cache');
    }
    touch($cacheFile);
    $cached = fetchMiningData(false);
    assert_true($cached === [], 'invalid fresh cache should be an empty list');
    assert_true(file_get_contents($cacheFile) === '{', 'invalid fresh cache should not be rewritten');
    $empty = render_index();
    assert_true($empty !== '', 'home page fatals when the coin cache is invalid JSON');
    assert_true(strpos($empty, 'Top 10 Coins') !== false, 'invalid cache dropped the heading');
    assert_true(strpos($empty, 'data-coin=') === false, 'invalid cache still rendered a coin card');
    assert_true(file_get_contents($cacheFile) === '{', 'rendering the home page rewrote an invalid cache');
} finally {
    file_put_contents($cacheFile, $original);
    if ($originalMtime !== false) {
        touch($cacheFile, $originalMtime);
    }
}

if ($failed) {
    exit(1);
}

echo "ok\n";
