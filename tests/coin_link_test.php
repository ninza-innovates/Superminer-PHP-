<?php
/**
 * Coin links from the cache must stay http(s) URLs, and calculator
 * profitability text must be escaped inside the option attribute.
 * Run from any working directory.
 */
$root = dirname(__DIR__);
if (!is_file($root . '/.env')) {
    if (!copy($root . '/.env.example', $root . '/.env')) {
        fwrite(STDERR, "Could not create .env from .env.example\n");
        exit(1);
    }
}

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

assert_same(httpUrlOrHash(null), '#', 'null link should be a hash');
assert_same(httpUrlOrHash(''), '#', 'empty link should be a hash');
assert_same(httpUrlOrHash('javascript:alert(1)'), '#', 'javascript link should be a hash');
assert_same(httpUrlOrHash('data:text/html,hi'), '#', 'data link should be a hash');
assert_same(httpUrlOrHash(['https://example.com']), '#', 'non-string link should be a hash');
assert_same(httpUrlOrHash('https://example.com/a b'), '#', 'link with a space should be a hash');
assert_same(
    httpUrlOrHash('https://example.com/x" onmouseover="alert(1)'),
    '#',
    'link with a quote should be a hash'
);
assert_same(
    httpUrlOrHash('  https://whattomine.com/coins/1-a-b  '),
    'https://whattomine.com/coins/1-a-b',
    'surrounding whitespace should be trimmed from an https link'
);
assert_same(
    httpUrlOrHash('HTTP://example.com/x'),
    'HTTP://example.com/x',
    'uppercase HTTP scheme should be kept'
);
assert_same(
    httpUrlOrHash('https://example.com/a?b=1&c=2'),
    'https://example.com/a?b=1&c=2',
    'query string should be kept'
);

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

function option_tag($html, $coin) {
    $value = htmlspecialchars($coin, ENT_QUOTES);
    $pattern = '/<option\b[^>]*\bvalue="' . preg_quote($value, '/') . '"[^>]*>/';
    if (!preg_match($pattern, $html, $matches)) {
        return '';
    }
    return $matches[0];
}

$cacheFile = $root . '/config/coins.json';
$original = file_get_contents($cacheFile);
if ($original === false) {
    fwrite(STDERR, "Could not read config/coins.json\n");
    exit(1);
}
$originalMtime = filemtime($cacheFile);

try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];

    touch($cacheFile);
    $home = render_public('index.php');
    assert_true(
        strpos($home, 'href="https://whattomine.com/coins/324-grin-cuckatoo32"') !== false,
        'home page dropped the cached Grin link'
    );
    assert_true(
        strpos($home, 'rel="noopener noreferrer"') !== false,
        'home page coin link should not keep the opener'
    );

    $calculator = render_public('calculator.php');
    assert_true(
        strpos($calculator, 'data-href="https://whattomine.com/coins/324-grin-cuckatoo32"') !== false,
        'calculator dropped the cached Grin link'
    );
    assert_true(
        strpos($calculator, 'data-profitability="33.36358"') !== false,
        'calculator dropped cached Grin profitability'
    );

    $hostileLinks = [
        'AmpCoin' => [
            'link' => 'https://example.com/a?b=1&c=2',
            'profitability' => '1.25',
            'difficulty' => 10,
        ],
        'BadCoin' => [
            'link' => 'https://example.com/x" onmouseover="alert(1)',
            'profitability' => '2',
            'difficulty' => 11,
        ],
        'ScriptCoin' => [
            'link' => 'javascript:alert(1)',
            'profitability' => '3',
            'difficulty' => 12,
        ],
    ];
    if (file_put_contents($cacheFile, json_encode($hostileLinks)) === false) {
        throw new RuntimeException('Could not write hostile coin cache');
    }
    touch($cacheFile);

    $homeHostile = render_public('index.php');
    assert_true(
        strpos($homeHostile, 'href="https://example.com/a?b=1&amp;c=2"') !== false,
        'home page did not escape an ampersand in a coin link'
    );
    assert_true(strpos($homeHostile, 'javascript:') === false, 'home page kept a javascript coin link');
    assert_true(strpos($homeHostile, 'onmouseover="') === false, 'home page kept a broken-out coin link');
    assert_true(
        strpos($homeHostile, 'data-coin="BadCoin"') !== false,
        'home page dropped the coin whose link was rejected'
    );

    $calcHostile = render_public('calculator.php');
    $amp = option_tag($calcHostile, 'AmpCoin');
    assert_true(
        strpos($amp, 'data-href="https://example.com/a?b=1&amp;c=2"') !== false,
        'calculator did not escape an ampersand in data-href'
    );
    $bad = option_tag($calcHostile, 'BadCoin');
    assert_true(strpos($bad, 'data-href="#"') !== false, 'quoted coin link was not replaced');
    $script = option_tag($calcHostile, 'ScriptCoin');
    assert_true(strpos($script, 'data-href="#"') !== false, 'javascript coin link was not replaced');
    assert_true(strpos($calcHostile, 'javascript:') === false, 'calculator kept a javascript coin link');
    assert_true(strpos($calcHostile, 'onmouseover="') === false, 'calculator kept a broken-out coin link');

    $hostileProfit = [
        'MarkupCoin' => [
            'link' => 'https://example.com/ok',
            'profitability' => '9"><script>',
            'difficulty' => 4,
        ],
    ];
    if (file_put_contents($cacheFile, json_encode($hostileProfit)) === false) {
        throw new RuntimeException('Could not write hostile profitability cache');
    }
    $calcProfit = render_public('calculator.php');
    $markup = option_tag($calcProfit, 'MarkupCoin');
    assert_true(
        strpos($markup, 'data-profitability="9&quot;&gt;&lt;script&gt;"') !== false,
        'profitability quote was not escaped'
    );
    assert_true(
        strpos($calcProfit, '9"><script>') === false,
        'raw profitability markup was rendered'
    );
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
