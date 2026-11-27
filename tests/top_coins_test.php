<?php
/**
 * The home page should list the ten highest WhatToMine profitability scores.
 * The coins.json feed is not ordered that way. Run from any working directory.
 */
$root = dirname(__DIR__);
if (!is_file($root . '/.env')) {
    if (!copy($root . '/.env.example', $root . '/.env')) {
        fwrite(STDERR, "Could not create .env from .env.example\n");
        exit(1);
    }
}

require_once $root . '/includes/api.php';

$failed = false;

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function assert_true($condition, $message) {
    global $failed;
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        $failed = true;
    }
}

function coin($id, $tag, $algorithm, $score, $rewards) {
    return [
        'id' => $id,
        'tag' => $tag,
        'algorithm' => $algorithm,
        'profitability' => $score,
        'estimated_rewards' => $rewards,
        'difficulty' => 1000,
    ];
}

// Feed order matches a live coins.json snapshot: a low score sits in the
// first ten names, and a higher score sits after them.
$feed = [
    'Pearl' => coin(469, 'PRL', 'Pearl', 6237, 2.11592),
    'Quantus' => coin(473, 'QUANTUS', 'QPoW', 3623, 0.05489),
    'EPIC-ProgPow' => coin(438, 'EPIC', 'ProgPow', 3185, 2.39173),
    'Tari-Cuckaroo29' => coin(451, 'XTM', 'Cuckaroo29', 2399, 2534.9587),
    'Zano' => coin(330, 'ZANO', 'ProgPowZ', 1851, 0.26706),
    'Nicehash-NexaPow' => coin(53, 'NICEHASH', 'NexaPow', 205, 0.0),
    'Xelis' => coin(459, 'XEL', 'Xelishashv3', 1645, 8.35353),
    'Firo' => coin(175, 'FIRO', 'FiroPow', 1428, 1.14904),
    'Qubitcoin' => coin(450, 'QTC', 'Qhash', 1452, 2.28568),
    'Ryo' => coin(260, 'RYO', 'CryptoNightGPU', 1410, 311.76997),
    'Evrmore' => coin(413, 'EVR', 'EvrProgPow', 1257, 77316.57515),
    'Conceal' => coin(305, 'CCX', 'CryptoNightGPU', 1358, 248.79111),
    'Not a coin' => 'skip',
];

$selected = selectTopCoins($feed);
$names = array_keys($selected);

assert_true($names === [
    'Pearl',
    'Quantus',
    'EPIC-ProgPow',
    'Tari-Cuckaroo29',
    'Zano',
    'Xelis',
    'Qubitcoin',
    'Firo',
    'Ryo',
    'Conceal',
], 'top 10 was ' . implode(', ', $names));
assert_true(!isset($selected['Nicehash-NexaPow']), 'low-score coin in the first ten names was kept');
assert_true(!isset($selected['Evrmore']), 'lower-ranked coin past the cutoff was kept');
assert_true($selected['Conceal']['profitability'] === 248.79111, 'stored profitability should stay estimated rewards');
assert_true(
    $selected['Conceal']['link'] === WHATTOMINE_URL . '/coins/305-ccx-cryptonightgpu',
    'coin link was ' . $selected['Conceal']['link']
);

// A nested tag used to throw from strtolower() and abort the whole refresh.
// A non-finite score ranks as zero. A bad tag or algorithm stores a null link.
$broken = selectTopCoins([
    'BadTag' => [
        'id' => 9,
        'tag' => ['nope'],
        'algorithm' => 'Sha',
        'profitability' => 50,
        'estimated_rewards' => '1.5',
        'difficulty' => 3,
    ],
    'Missing' => [
        'profitability' => 40,
        'estimated_rewards' => '2',
    ],
    'Huge' => [
        'id' => 1,
        'tag' => 'H',
        'algorithm' => 'Sha',
        'profitability' => INF,
        'estimated_rewards' => INF,
        'difficulty' => NAN,
    ],
    'Spaced' => [
        'id' => 6,
        'tag' => 'OK COIN',
        'algorithm' => 'Sha',
        'profitability' => 30,
        'estimated_rewards' => '1',
        'difficulty' => 1,
    ],
    'Ok' => coin(4, 'OK', 'Sha', 10, 0.5),
]);
assert_true(array_keys($broken) === ['BadTag', 'Missing', 'Spaced', 'Ok', 'Huge'], 'malformed coins sorted as ' . implode(', ', array_keys($broken)));
assert_true($broken['BadTag']['link'] === null, 'nested tag still built a link');
assert_true($broken['Missing']['link'] === null, 'missing id still built a link');
assert_true($broken['Spaced']['link'] === null, 'tag with a space still built a link');
assert_true($broken['Huge']['link'] === WHATTOMINE_URL . '/coins/1-h-sha', 'finite id and tag should still build a link');
assert_true($broken['Huge']['profitability'] === null, 'non-finite reward should be stored as null');
assert_true($broken['Huge']['difficulty'] === null, 'non-finite difficulty should be stored as null');
assert_true($broken['Ok']['link'] === WHATTOMINE_URL . '/coins/4-ok-sha', 'valid coin link changed');
assert_true($broken['Ok']['profitability'] === 0.5, 'valid coin reward changed');
assert_true(json_encode($broken) !== false, 'malformed coin list is not JSON');

if ($failed) {
    exit(1);
}

echo "ok\n";
