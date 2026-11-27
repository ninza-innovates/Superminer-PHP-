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
 * A list that cannot be encoded, or any other result, keeps a saved list and renews the 60-second timer.
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
            // json_encode() returns false for non-finite numbers. Writing that
            // false value blanks coins.json, so keep the saved list instead.
            $encoded = json_encode($coins, JSON_PRETTY_PRINT);
            if (is_string($encoded)) {
                file_put_contents($cacheFile, $encoded);
                return $coins;
            }
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
 * Path segment for a WhatToMine coin URL. It must be letters and digits;
 * anything else yields null so a missing or nested tag cannot throw or build a broken link.
 *
 * @param mixed $value
 * @return string|null
 */
function whattomineSlug($value) {
    if (!is_string($value) || preg_match('/\A[A-Za-z0-9]+\z/', $value) !== 1) {
        return null;
    }
    return strtolower($value);
}

/**
 * Coin page URL, or null when id, tag, or algorithm is not a safe path segment.
 *
 * @param array $info
 * @return string|null
 */
function whattomineCoinLink($info) {
    $id = $info['id'] ?? null;
    if (is_int($id) && $id >= 0) {
        $idPart = (string) $id;
    } elseif (is_string($id) && preg_match('/\A[0-9]+\z/', $id) === 1) {
        $idPart = $id;
    } else {
        return null;
    }
    $tag = whattomineSlug($info['tag'] ?? null);
    $algorithm = whattomineSlug($info['algorithm'] ?? null);
    if ($tag === null || $algorithm === null) {
        return null;
    }
    return WHATTOMINE_URL . '/coins/' . $idPart . '-' . $tag . '-' . $algorithm;
}

/**
 * Non-finite floats cannot be JSON-encoded. Storing them used to make
 * json_encode() fail and wipe the coin cache, so they become null.
 *
 * @param mixed $value
 * @return mixed
 */
function finiteCoinStat($value) {
    if (is_float($value) && !is_finite($value)) {
        return null;
    }
    return $value;
}

/**
 * WhatToMine lists coins in feed order, not by profitability.
 * Rank by that score and keep the highest ones.
 * A coin with a bad id, tag, or algorithm stays in the list with a null link.
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
        if (is_string($score)) {
            $score = trim($score);
        }
        $score = is_numeric($score) ? (float) $score : 0.0;
        if (!is_finite($score)) {
            $score = 0.0;
        }
        $ranked[] = [
            'name' => $coin,
            'score' => $score,
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
            'link' => whattomineCoinLink($info),
            'profitability' => finiteCoinStat($info['estimated_rewards'] ?? 0),
            'difficulty' => finiteCoinStat($info['difficulty'] ?? 0)
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
