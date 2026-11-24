<?php
/**
 * Coin links from the WhatToMine cache, and affiliate footer URLs from .env,
 * are copied into href and data-href. Only an http(s) URL without spaces,
 * quotes, or brackets is safe to emit. Anything else becomes "#" so a
 * javascript: link or a broken-out attribute cannot run from that value.
 *
 * @param mixed $link
 * @return string
 */
function httpUrlOrHash($link) {
    if (!is_string($link)) {
        return '#';
    }
    $link = trim($link);
    if (preg_match('#\Ahttps?://#i', $link) !== 1) {
        return '#';
    }
    if (preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $link) === 1) {
        return '#';
    }
    return $link;
}
?>
