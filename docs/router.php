<?php

/**
 * Router for `php -S`, so local dev resolves clean URLs the same way
 * Cloudflare Pages does in production: a request for `/foo` serves
 * `foo.html`, and `/foo` (or `/foo/`) falls back to `foo/index.html` when
 * there's no `foo.html`. Without this, PHP's built-in server has no such
 * resolution at all — an unmatched path just falls through to the nearest
 * `index.html` above it, silently serving the wrong page.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docroot = __DIR__.'/build';

if ($uri === '/' || is_file($docroot.$uri)) {
    return false;
}

$path = rtrim($uri, '/');

foreach ([$path.'.html', $path.'/index.html'] as $candidate) {
    if (is_file($docroot.$candidate)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($docroot.$candidate);

        return true;
    }
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
readfile($docroot.'/404.html');

return true;
