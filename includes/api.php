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
        $fallback = [
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
        file_put_contents($cacheFile, json_encode($fallback, JSON_PRETTY_PRINT));
        return $fallback;
    }

    $json = json_decode($data, true);
    $coins = [];
    $count = 0;
    foreach ($json['coins'] as $coin => $info) {
        if ($count >= 10) break;
        $coins[$coin] = [
            'link' => WHATTOMINE_URL."/coins/".$info['id']."-".strtolower($info['tag'])."-".strtolower($info['algorithm']),
            'profitability' => $info['estimated_rewards'] ?? 0,
            'difficulty' => $info['difficulty'] ?? 0
        ];
        $count++;
    }

    // Cache the fresh data
    file_put_contents($cacheFile, json_encode($coins, JSON_PRETTY_PRINT));
    return $coins;
}
?>