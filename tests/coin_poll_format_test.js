/**
 * The home page poll must format profitability and difficulty the same way
 * as formatCoinNumber() on the server, including n/a for a bad stat.
 * Run from any working directory: node tests/coin_poll_format_test.js
 */
const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const root = path.resolve(__dirname, '..');
const {
    formatCoinNumber,
    coinProfitabilityText,
    coinDifficultyText,
} = require(path.join(root, 'public/assets/js/script.js'));

const envFile = path.join(root, '.env');
if (!fs.existsSync(envFile)) {
    try {
        fs.copyFileSync(path.join(root, '.env.example'), envFile);
    } catch (error) {
        process.stderr.write('Could not create .env from .env.example\n');
        process.exit(1);
    }
}

let failed = false;

function assertSame(actual, expected, message) {
    if (actual !== expected) {
        process.stderr.write(message + ' (got ' + JSON.stringify(actual) + ')\n');
        failed = true;
    }
}

const cases = [
    ['33.36358', 5],
    [309855.1384277344, 10],
    [1.5, 5],
    [12, 10],
    ['1e2', 5],
    [79374.42159, 5],
    [65534394.17988376, 10],
    [67848771804758, 10],
    [-1.2, 2],
    [0.00001, 5],
    [999.999999, 5],
    ['  1.25  ', 5],
    ['.5', 5],
    ['5.', 2],
    ['+1.5', 2],
    [-79374.42159, 5],
    [0, 5],
    ['0', 10],
    [null, 5],
    ['  ', 5],
    ['9"><script>', 5],
    ['10"><b>', 10],
    ['0x10', 5],
    ['INF', 5],
    ['NAN', 5],
    [true, 5],
    ['1e309', 5],
    ['1.2.3', 5],
    [[], 5],
    [{}, 5],
];

const phpCode = [
    'require $argv[1];',
    '$cases = json_decode($argv[2], true);',
    'if (!is_array($cases)) { fwrite(STDERR, "bad cases\\n"); exit(1); }',
    '$out = [];',
    'foreach ($cases as $case) {',
    '    $out[] = formatCoinNumber($case[0], $case[1]);',
    '}',
    'echo json_encode($out);',
].join('\n');

const php = spawnSync(
    process.env.PHP_BINARY || 'php',
    ['-d', 'display_errors=stderr', '-r', phpCode, path.join(root, 'includes/functions.php'), JSON.stringify(cases)],
    { encoding: 'utf8' }
);

if (php.status !== 0 || php.stderr) {
    process.stderr.write(php.stderr || 'PHP formatCoinNumber comparison failed\n');
    process.exit(1);
}

let phpFormatted;
try {
    phpFormatted = JSON.parse(php.stdout);
} catch (error) {
    process.stderr.write('PHP comparison returned ' + php.stdout + '\n');
    process.exit(1);
}

if (!Array.isArray(phpFormatted) || phpFormatted.length !== cases.length) {
    process.stderr.write('PHP comparison returned an unexpected list\n');
    process.exit(1);
}

cases.forEach(function (entry, index) {
    const actual = formatCoinNumber(entry[0], entry[1]);
    assertSame(
        actual,
        phpFormatted[index],
        'formatCoinNumber disagreed with PHP for ' + JSON.stringify(entry[0]) + ' @ ' + entry[1]
    );
});

assertSame(coinProfitabilityText(79374.42159), '$79,374.42159/day', 'large rewards should keep thousands separators');
assertSame(coinProfitabilityText('1.5'), '$1.50000/day', 'profitability should pad to 5 decimals');
assertSame(coinProfitabilityText('1e2'), '$100.00000/day', 'scientific notation should still format as profit');
assertSame(coinProfitabilityText(null), 'n/a', 'missing profitability should show n/a');
assertSame(coinProfitabilityText('9"><script>'), 'n/a', 'markup profitability should show n/a');
assertSame(coinDifficultyText(12), '12.0000000000', 'difficulty should pad to 10 decimals');
assertSame(
    coinDifficultyText(65534394.17988376),
    '65,534,394.1798837632',
    'difficulty should not be rounded to a short locale string'
);
assertSame(coinDifficultyText(null), 'n/a', 'missing difficulty should show n/a');
assertSame(coinDifficultyText('10"><b>'), 'n/a', 'markup difficulty should show n/a');

if (failed) {
    process.exit(1);
}

process.stdout.write('ok\n');
