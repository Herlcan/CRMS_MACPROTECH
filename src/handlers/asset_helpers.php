<?php

if (!function_exists('asset_path')) {
    function asset_path(string $path): string
    {
        $normalized = ltrim($path, '/');

        if (preg_match('/\.(css|js)$/', $normalized, $matches)) {
            $minified = preg_replace('/\.(css|js)$/', '.min.$1', $normalized);
            $sourceFullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
            $minifiedFullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $minified);

            if (
                is_file($minifiedFullPath)
                && (!is_file($sourceFullPath) || filemtime($minifiedFullPath) >= filemtime($sourceFullPath))
            ) {
                return $minified;
            }
        }

        return $normalized;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        $assetPath = asset_path($path);
        $fullPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $assetPath);

        if (!is_file($fullPath)) {
            return $assetPath;
        }

        $separator = str_contains($assetPath, '?') ? '&' : '?';

        return $assetPath . $separator . 'v=' . filemtime($fullPath);
    }
}

if (!function_exists('asset_attr')) {
    function asset_attr(string $path): string
    {
        return htmlspecialchars(asset_url($path), ENT_QUOTES, 'UTF-8');
    }
}

?>
