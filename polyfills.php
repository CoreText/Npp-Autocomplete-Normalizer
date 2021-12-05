<?php

if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle) {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}
