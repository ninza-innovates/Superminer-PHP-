<?php
// /var/www/html/superminer/includes/functions.php
require_once 'config.php';

/**
 * Load a config JSON object. A missing or invalid file yields an empty list
 * so the calculator can render instead of fatalling on the decode.
 *
 * @param string $path
 * @return array
 */
function readConfigJson($path) {
    if (!is_readable($path)) {
        return [];
    }
    $decoded = json_decode(file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Calculates daily ROI based on hash rate, power cost, usage, and coin profitability.
 * @param float $hashRate Hash rate in MH/s
 * @param float $powerCost Power cost in $/kWh
 * @param float $powerUsage Power usage in watts
 * @param float $profitPerDay Daily profit from coin data
 * @return float Net daily profit
 */
function calculateROI($hashRate, $powerCost, $powerUsage, $profitPerDay) {
    $dailyCost = ($powerUsage / 1000) * $powerCost * 24; // Convert W to kW, cost per day
    $netProfit = ($profitPerDay * $hashRate) - $dailyCost; // Adjust profit by hash rate
    return round($netProfit, 2);
}

/**
 * Hash rate, power cost, wattage, and hardware cost must be positive finite numbers.
 * Empty, non-numeric, zero, negative, and non-finite values are rejected.
 *
 * @param mixed $raw
 * @return float|null
 */
function positiveCalculatorNumber($raw) {
    if (is_int($raw) || is_float($raw)) {
        $value = (float) $raw;
    } elseif (is_string($raw)) {
        $raw = trim($raw);
        if ($raw === '' || !is_numeric($raw)) {
            return null;
        }
        $value = (float) $raw;
    } else {
        return null;
    }

    if (!is_finite($value) || $value <= 0) {
        return null;
    }
    return $value;
}

/**
 * Profitability for a coin the calculator actually lists.
 * Cached rewards are often numeric strings, so those are accepted.
 *
 * @param mixed $coins
 * @param mixed $coin
 * @return float|null
 */
function calculatorCoinProfit($coins, $coin) {
    if (!is_string($coin) || $coin === '' || !is_array($coins) || !isset($coins[$coin]) || !is_array($coins[$coin])) {
        return null;
    }
    $profit = $coins[$coin]['profitability'] ?? null;
    if (!is_numeric($profit)) {
        return null;
    }
    $value = (float) $profit;
    if (!is_finite($value)) {
        return null;
    }
    return $value;
}
?>