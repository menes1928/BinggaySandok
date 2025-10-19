<?php
// App base URL helper: returns '' for root deployments, or '/Binggay' (or '/BinggaySandok')
// when the app is accessed under a subfolder. Use for building absolute URLs.
if (!function_exists('app_base_prefix')) {
    function app_base_prefix(): string {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (!is_string($uri) || $uri === '') return '';
        $path = explode('?', $uri, 2)[0];
        $segs = array_values(array_filter(explode('/', $path)));
        if (!$segs) return '';
        $first = $segs[0];
        // Support both historical folder names just in case
        if (in_array($first, ['Binggay', 'BinggaySandok'], true)) {
            return '/' . $first;
        }
        return '';
    }
}
