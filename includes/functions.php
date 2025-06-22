<?php
// /var/www/html/superminer/includes/functions.php
require_once 'config.php';

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
?>