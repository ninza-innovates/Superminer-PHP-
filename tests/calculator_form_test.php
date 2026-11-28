<?php
/**
 * The ROI calculator must show the submitted coin and inputs with the result.
 * A fresh form must stay blank. Run from any working directory.
 */
$root = dirname(__DIR__);
if (!is_file($root . '/.env')) {
    if (!copy($root . '/.env.example', $root . '/.env')) {
        fwrite(STDERR, "Could not create .env from .env.example\n");
        exit(1);
    }
}

chdir($root . '/public');
require_once $root . '/includes/functions.php';

$fixed = calculateROI(100, 0.1, 320, 1.2869);
if ($fixed !== 127.92) {
    fwrite(STDERR, "calculateROI returned {$fixed}, expected 127.92\n");
    exit(1);
}

$coins = json_decode(file_get_contents(CONFIG_DIR . '/coins.json'), true);
$names = is_array($coins) ? array_keys($coins) : [];
if (count($names) < 2) {
    fwrite(STDERR, "config/coins.json needs at least two coins\n");
    exit(1);
}

$failed = false;

function assert_true($condition, $message) {
    global $failed;
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        $failed = true;
    }
}

function input_tag($html, $id) {
    if (!preg_match_all('/<input\b[^>]*>/', $html, $matches)) {
        return '';
    }
    foreach ($matches[0] as $tag) {
        if (preg_match('/\bid="' . preg_quote($id, '/') . '"/', $tag)) {
            return $tag;
        }
    }
    return '';
}

function option_tag($html, $coin) {
    $value = htmlspecialchars($coin, ENT_QUOTES);
    $pattern = '/<option\b[^>]*\bvalue="' . preg_quote($value, '/') . '"[^>]*>/';
    if (!preg_match($pattern, $html, $matches)) {
        return '';
    }
    return $matches[0];
}

function render_calculator() {
    global $root;
    ob_start();
    require $root . '/public/calculator.php';
    return ob_get_clean();
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = [];
$fresh = render_calculator();
assert_true(strpos($fresh, 'ROI Calculator') !== false, 'GET did not render the calculator');
assert_true(strpos($fresh, 'Estimated Daily Profit') === false, 'GET rendered a profit result');
assert_true(strpos($fresh, ' selected') === false, 'GET marked a coin selected');
assert_true(strpos($fresh, 'href="./"') !== false, 'home link should be relative');
assert_true(strpos($fresh, 'href="/calculator.php"') === false, 'calculator link should stay relative');
assert_true(strpos($fresh, 'href="calculator.php"') !== false, 'calculator link missing');
assert_true(strpos($fresh, 'id="hardware"') !== false, 'GET missing hardware preset');
assert_true(strpos($fresh, 'data-hash-rate="100"') !== false, 'preset missing RTX 3080 hash rate');
assert_true(strpos($fresh, 'data-power-usage="320"') !== false, 'preset missing RTX 3080 power');
foreach (['hashRate', 'powerCost', 'powerUsage', 'hardwareCost'] as $id) {
    $tag = input_tag($fresh, $id);
    assert_true($tag !== '' && strpos($tag, 'value=""') !== false, "GET {$id} should be empty");
}

$coin = $names[1];
$daily = calculateROI(100, 0.1, 320, $coins[$coin]['profitability']);
$breakEven = ($daily > 0) ? round(500 / $daily, 1) : '';

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'coin' => $coin,
    'hardware' => 'AMD RX 6800',
    'hashRate' => '100',
    'powerCost' => '0.1',
    'powerUsage' => '320',
    'hardwareCost' => '500',
];
$posted = render_calculator();
assert_true(strpos($posted, '$' . $daily) !== false, "POST missing profit {$daily} for {$coin}");
if ($breakEven !== '') {
    assert_true(strpos($posted, $breakEven . ' days') !== false, "POST missing break-even {$breakEven}");
}

$selected = option_tag($posted, $coin);
assert_true($selected !== '' && strpos($selected, ' selected') !== false, "posted coin {$coin} is not selected");
$first = option_tag($posted, $names[0]);
assert_true($first !== '' && strpos($first, ' selected') === false, "first coin {$names[0]} should not be selected");
$preset = option_tag($posted, 'AMD RX 6800');
assert_true($preset !== '' && strpos($preset, ' selected') !== false, 'posted hardware preset is not selected');
$firstPreset = option_tag($posted, 'NVIDIA RTX 3080');
assert_true($firstPreset !== '' && strpos($firstPreset, ' selected') === false, 'first hardware preset should not be selected');
foreach (['hashRate' => '100', 'powerCost' => '0.1', 'powerUsage' => '320', 'hardwareCost' => '500'] as $id => $value) {
    $tag = input_tag($posted, $id);
    assert_true($tag !== '' && strpos($tag, 'value="' . $value . '"') !== false, "{$id} did not keep {$value}");
}

$_POST['hashRate'] = '1"><script>';
$escaped = render_calculator();
$hashTag = input_tag($escaped, 'hashRate');
assert_true(
    strpos($hashTag, 'value="1&quot;&gt;&lt;script&gt;"') !== false,
    'hashRate quote was not escaped'
);
assert_true(strpos($escaped, 'Estimated Daily Profit') === false, 'non-numeric hash rate still produced a profit');
assert_true(strpos($escaped, 'Enter a listed coin and positive numbers') !== false, 'non-numeric hash rate did not show an error');

function post_invalid($overrides) {
    global $coin;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'coin' => $coin,
        'hardware' => 'AMD RX 6800',
        'hashRate' => '100',
        'powerCost' => '0.1',
        'powerUsage' => '320',
        'hardwareCost' => '500',
    ];
    foreach ($overrides as $name => $value) {
        $_POST[$name] = $value;
    }
    return render_calculator();
}

$negative = post_invalid(['hashRate' => '-10', 'powerCost' => '0', 'powerUsage' => 'nope']);
assert_true(strpos($negative, 'Estimated Daily Profit') === false, 'negative hash rate still produced a profit');
assert_true(strpos($negative, 'Enter a listed coin and positive numbers') !== false, 'invalid numbers did not show an error');
assert_true(strpos(input_tag($negative, 'hashRate'), 'value="-10"') !== false, 'rejected hash rate was not kept');
assert_true(strpos(input_tag($negative, 'powerCost'), 'value="0"') !== false, 'rejected power cost was not kept');

$unknown = post_invalid(['coin' => 'Not A Listed Coin']);
assert_true(strpos($unknown, 'Estimated Daily Profit') === false, 'unknown coin still produced a profit');
assert_true(strpos($unknown, 'Enter a listed coin and positive numbers') !== false, 'unknown coin did not show an error');

assert_true(positiveCalculatorNumber('1e2') === 100.0, 'scientific notation should stay a positive number');
assert_true(positiveCalculatorNumber('INF') === null, 'non-finite numbers should be rejected');
assert_true(positiveCalculatorNumber('  ') === null, 'blank numbers should be rejected');
assert_true(calculatorCoinProfit(['Missing' => ['difficulty' => 1]], 'Missing') === null, 'coin without profitability should be rejected');
assert_true(calculatorCoinProfit(['Zero' => ['profitability' => '0']], 'Zero') === 0.0, 'a listed zero reward is still a number');
assert_true(readConfigJson($root . '/config/does-not-exist.json') === [], 'missing config JSON should be an empty list');
assert_true(hardwarePresetNumber(50) === '50', 'int preset number should stay a digit string');
assert_true(hardwarePresetNumber(64.5) === '64.5', 'float preset number should stay decimal');
assert_true(hardwarePresetNumber(' 75 ') === '75', 'padded preset number should be trimmed');
assert_true(hardwarePresetNumber(['value' => 10]) === null, 'array preset number should be rejected');
assert_true(hardwarePresetNumber(INF) === null, 'infinite preset number should be rejected');
assert_true(hardwarePresetNumber('INF') === null, 'INF string should be rejected');
assert_true(hardwarePresetNumber('100 MH/s') === null, 'preset number with a unit should be rejected');

$hardwareFile = $root . '/config/hardware.json';
$originalHardware = file_get_contents($hardwareFile);
if ($originalHardware === false) {
    fwrite(STDERR, "Could not read config/hardware.json\n");
    exit(1);
}
$malformedHardware = json_encode([
    'OK GPU' => ['hashRate' => 50, 'powerUsage' => 120],
    'Nested GPU' => ['hashRate' => ['value' => 10], 'powerUsage' => 80],
    'NotAPreset' => 'skip',
    'Text Rate' => ['hashRate' => "100\n200", 'powerUsage' => '90'],
    'Spaced' => ['hashRate' => ' 75 ', 'powerUsage' => 80],
], JSON_PRETTY_PRINT);
if ($malformedHardware === false || file_put_contents($hardwareFile, $malformedHardware) === false) {
    fwrite(STDERR, "Could not write malformed hardware.json\n");
    exit(1);
}
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_POST = [];
    try {
        $malformedPage = render_calculator();
    } catch (Throwable $e) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $malformedPage = '';
        assert_true(false, 'malformed hardware preset crashed the calculator: ' . $e->getMessage());
    }
    assert_true(strpos($malformedPage, 'ROI Calculator') !== false, 'malformed hardware dropped the calculator');
    $okPreset = option_tag($malformedPage, 'OK GPU');
    assert_true(strpos($okPreset, 'data-hash-rate="50"') !== false, 'valid preset hash rate was dropped');
    assert_true(strpos($okPreset, 'data-power-usage="120"') !== false, 'valid preset wattage was dropped');
    $nested = option_tag($malformedPage, 'Nested GPU');
    assert_true($nested !== '', 'preset with a nested hash rate was dropped');
    assert_true(strpos($nested, 'data-hash-rate=') === false, 'nested hash rate was still written');
    assert_true(strpos($nested, 'data-power-usage="80"') !== false, 'valid wattage on a nested preset was dropped');
    assert_true(strpos($malformedPage, 'data-hash-rate="Array"') === false, 'nested hash rate was cast to Array');
    assert_true(strpos($malformedPage, 'NotAPreset') === false, 'non-array hardware entry was rendered');
    assert_true(strpos($malformedPage, "100\n200") === false, 'hash rate with a newline was rendered');
    $textRate = option_tag($malformedPage, 'Text Rate');
    assert_true($textRate !== '' && strpos($textRate, 'data-hash-rate=') === false, 'non-numeric hash rate was written');
    assert_true(strpos($textRate, 'data-power-usage="90"') !== false, 'numeric wattage string was dropped');
    $spaced = option_tag($malformedPage, 'Spaced');
    assert_true(strpos($spaced, 'data-hash-rate="75"') !== false, 'padded hash rate was not trimmed');
    assert_true(strpos($spaced, 'data-power-usage="80"') !== false, 'int wattage on a padded preset was dropped');
} finally {
    restore_error_handler();
    file_put_contents($hardwareFile, $originalHardware);
}

if ($failed) {
    exit(1);
}

echo "ok\n";
