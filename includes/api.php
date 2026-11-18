<?php
// /var/www/html/superminer/includes/api.php
require_once 'config.php';

/**
 * Fetches mining profitability data for top 10 coins and caches it.
 * @param bool $forceRefresh Force a fresh API call
 * @return array Coin data
 */
function fetchMiningData($forceRefresh = false) {
    $cacheFile = CONFIG_DIR . '/coins.json';
    $cacheTime = 60; // 60 seconds cache expiration

    // Check if cache exists and is fresh, unless forcing refresh
    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        return json_decode(file_get_contents($cacheFile), true);
    }

    // Fetch from WhatToMine API
    $ch = curl_init(API_WHATTOMINE_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($data === false || $httpCode !== 200) {
        // Fallback to hardcoded data
        $fallback = whattomineFallbackCoins();
        file_put_contents($cacheFile, json_encode($fallback, JSON_PRETTY_PRINT));
        return $fallback;
    }

    $json = json_decode($data, true);
    if (!is_array($json) || !isset($json['coins']) || !is_array($json['coins'])) {
        $fallback = whattomineFallbackCoins();
        file_put_contents($cacheFile, json_encode($fallback, JSON_PRETTY_PRINT));
        return $fallback;
    }

    $coins = selectTopCoins($json['coins']);

    // Cache the fresh data
    file_put_contents($cacheFile, json_encode($coins, JSON_PRETTY_PRINT));
    return $coins;
}

/**
 * WhatToMine lists coins in feed order, not by profitability.
 * Rank by that score and keep the highest ones.
 * Stored profitability stays estimated_rewards, which the pages already display.
 *
 * @param array $apiCoins Coin name => WhatToMine coin object
 * @param int $limit
 * @return array
 */
function selectTopCoins($apiCoins, $limit = 10) {
    $ranked = [];
    foreach ($apiCoins as $coin => $info) {
        if (!is_array($info)) {
            continue;
        }
        $score = $info['profitability'] ?? 0;
        $ranked[] = [
            'name' => $coin,
            'score' => is_numeric($score) ? (float) $score : 0,
            'info' => $info,
        ];
    }

    usort($ranked, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $coins = [];
    foreach (array_slice($ranked, 0, $limit) as $row) {
        $info = $row['info'];
        $coins[$row['name']] = [
            'link' => WHATTOMINE_URL."/coins/".$info['id']."-".strtolower($info['tag'])."-".strtolower($info['algorithm']),
            'profitability' => $info['estimated_rewards'] ?? 0,
            'difficulty' => $info['difficulty'] ?? 0
        ];
    }
    return $coins;
}

function whattomineFallbackCoins() {
    return [
        'Bitcoin' => ['profitability' => 0.05, 'difficulty' => 83594540283594],
        'Ethereum' => ['profitability' => 0.03, 'difficulty' => 2259845],
        'Litecoin' => ['profitability' => 0.02, 'difficulty' => 254321],
        'Monero' => ['profitability' => 0.01, 'difficulty' => 298765],
        'Ravencoin' => ['profitability' => 0.015, 'difficulty' => 87654],
        'Dogecoin' => ['profitability' => 0.008, 'difficulty' => 987654],
        'Zcash' => ['profitability' => 0.012, 'difficulty' => 654321],
        'Dash' => ['profitability' => 0.009, 'difficulty' => 543210],
        'Bitcoin Cash' => ['profitability' => 0.018, 'difficulty' => 765432],
        'Ethereum Classic' => ['profitability' => 0.014, 'difficulty' => 432109]
    ];
}
?>
