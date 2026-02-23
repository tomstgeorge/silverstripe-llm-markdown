<?php

/**
 * Static cache request handler that serves .md when Accept includes text/markdown or text/plain
 * (e.g. AI agents), otherwise delegates to the staticpublishqueue handler (serves .html).
 *
 * Usage from public/index.php (after defining cache dir):
 *   $handler = require 'path/to/tomstgeorge/silverstripe-llm-markdown/includes/staticrequesthandler.php';
 *   if ($handler($cacheDir)) { exit; }
 */

use function SilverStripe\StaticPublishQueue\URLtoPath;

return function ($cacheDir, $urlMapping = null) {
    // Allow content authors to avoid static cache via cookie
    if (isset($_COOKIE['bypassStaticCache'])) {
        return false;
    }

    // Build full URL from request
    $port = $_SERVER['SERVER_PORT'] ?? 80;
    $https = $port === '443' || !empty($_SERVER['HTTPS']);
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $url = ($https ? 'https://' : 'http://') . $host . $uri;

    $path = is_callable($urlMapping) ? $urlMapping($url) : URLtoPath($url);
    if (!$path) {
        return false;
    }

    $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $path;
    $realCacheDir = realpath($cacheDir);
    $realPathDir = $cachePath !== '' ? realpath(dirname($cachePath)) : false;

    if (!$realCacheDir || !$realPathDir || strpos($realPathDir, $realCacheDir) !== 0) {
        return false;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

    // Prefer markdown when client asks for text/markdown or text/plain (e.g. AI agents) and we have a .md file
    $wantsText = stripos($accept, 'text/markdown') !== false || stripos($accept, 'text/plain') !== false;
    if ($wantsText && file_exists($cachePath . '.md')) {
        header('Content-Type: text/markdown; charset=utf-8');
        header('X-Cache-Hit: ' . date(DATE_COOKIE));
        readfile($cachePath . '.md');
        return true;
    }

    // Delegate to staticpublishqueue handler for .html (and .php config)
    $baseHandlerPath = null;
    if (defined('BASE_PATH')) {
        $baseHandlerPath = BASE_PATH . '/vendor/silverstripe/staticpublishqueue/includes/staticrequesthandler.php';
    }
    if (!$baseHandlerPath || !is_file($baseHandlerPath)) {
        $candidates = [
            dirname(__DIR__, 2) . '/silverstripe/staticpublishqueue/includes/staticrequesthandler.php',
            dirname(__DIR__, 2) . '/vendor/silverstripe/staticpublishqueue/includes/staticrequesthandler.php',
            dirname(__DIR__, 3) . '/silverstripe/staticpublishqueue/includes/staticrequesthandler.php',
        ];
        foreach ($candidates as $c) {
            if (is_file($c)) {
                $baseHandlerPath = $c;
                break;
            }
        }
    }
    if ($baseHandlerPath && is_file($baseHandlerPath)) {
        $baseHandler = require $baseHandlerPath;
        return $baseHandler($cacheDir, $urlMapping);
    }

    return false;
};
