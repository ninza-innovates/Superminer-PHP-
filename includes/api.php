<?php
// /var/www/html/superminer/includes/api.php
require_once 'config.php';

/**
 * Fetches mining profitability data for top 10 coins and caches it.
 * A failed refresh keeps a saved coin list and renews its timer.
 * The built-in list is used only when the cache file is missing or not a list.
 * @param bool $forceRefresh Force a fresh API call
 * @return array Coin data
 */
function fetchMiningData($forceRefresh = false) {
    $cacheFile = CONFIG_DIR . '/coins.json';
    $cacheTime = 60; // 60 seconds cache expiration

    // Check if cache exists and is fresh, unless forcing refresh.
    // A fresh file that is not a coin list used to return null and warn on
    // the home page. Keep the file until the TTL expires, and render nothing.
    if (!$forceRefresh && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $cached = readCachedCoins($cacheFile);
        return is_array($cached) ? $cached : [];
    }

    // Fetch from WhatToMine API
    $ch = curl_init(API_WHATTOMINE_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return applyMiningResponse($cacheFile, $data, $httpCode);
}

/**
 * Saved coin list, or null when the file is missing or not a JSON list.
 *
 * @param string $cacheFile
 * @return array|null
 */
function readCachedCoins($cacheFile) {
    if (!is_readable($cacheFile)) {
        return null;
    }
    $raw = file_get_contents($cacheFile);
    if (!is_string($raw)) {
        return null;
    }
    $cached = json_decode($raw, true);
    return is_array($cached) ? $cached : null;
}

/**
 * Store a WhatToMine response.
 * HTTP 200 with a coin list replaces the cache.
 * Any other result keeps a saved list and renews the 60-second timer.
 * The built-in list is written only when there is no saved list.
 *
 * @param string $cacheFile
 * @param mixed $data Response body, or false when the request did not complete
 * @param mixed $httpCode
 * @return array
 */
function applyMiningResponse($cacheFile, $data, $httpCode) {
    if ($httpCode === 200 && is_string($data)) {
        $json = json_decode($data, true);
        if (is_array($json) && isset($json['coins']) && is_array($json['coins'])) {
            $coins = selectTopCoins($json['coins']);
            file_put_contents($cacheFile, json_encode($coins, JSON_PRETTY_PRINT));
            return $coins;
        }
    }

    $cached = readCachedCoins($cacheFile);
    if (is_array($cached)) {
        // Keep the saved bytes, but move the timer forward so later requests
        // do not wait on WhatToMine again during an outage.
        touch($cacheFile);
        return $cached;
    }

    $fallback = whattomineFallbackCoins();
    file_put_contents($cacheFile, json_encode($fallback, JSON_PRETTY_PRINT));
    return $fallback;
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
