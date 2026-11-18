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

if ($failed) {
    exit(1);
}

echo "ok\n";
