<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

/**
 * @internal
 *
 * The wrapper FinalBypass::enable() actually registers for the `file://`
 * protocol. Every other operation (directory listing, stat, write, ...) is
 * delegated straight through to the previously-active wrapper via __call() —
 * this class only ever intervenes on stream_open() for a `.php` file opened
 * for reading, and even then only to swap in FinalBypass::modifyCode()'s
 * output when it actually differs from the original source.
 *
 * Adapted from dg/bypass-finals's MutatingWrapper.php
 * (https://github.com/dg/bypass-finals), copyright David Grudl, used under
 * the BSD-3-Clause license — trimmed of the path allow/deny checks upstream
 * offers, since Double has no equivalent concept: whatever class is passed
 * to `Double::for()` is exactly the one that needs its source read unmodified
 * by default and rewritten once bypassing is enabled, with no per-path
 * carve-outs to configure.
 */
final class FinalBypassMutatingWrapper
{
    /** @var class-string Class of the wrapper that was active before enable() replaced it */
    public static string $underlyingWrapperClass;

    /** @var resource|null Stream context, which may be set by stream functions */
    public $context;

    private ?object $wrapper = null;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (is_dir($path)) {
            return false;
        }

        // is_dir() populates the stat cache; clear it so subsequent stat calls return fresh data
        clearstatcache(true, $path);

        $this->wrapper = $this->createUnderlyingWrapper();
        if (! $this->wrapper->stream_open($path, $mode, $options, $openedPath)) {
            return false;
        }

        if ($mode === 'rb' && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            $content = '';
            while (! $this->wrapper->stream_eof()) {
                $content .= $this->wrapper->stream_read(8192);
            }

            $modified = FinalBypass::modifyCode($content);

            if ($modified === $content) {
                $this->wrapper->stream_seek(0);
            } else {
                $this->wrapper->stream_close();
                $wrapper = new FinalBypassNativeWrapper;
                $wrapper->handle = tmpfile() ?: throw new \RuntimeException('Unable to create a temporary file.');
                $wrapper->stream_write($modified);
                $wrapper->stream_seek(0);
                $this->wrapper = $wrapper;
            }
        }

        return true;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        $this->wrapper = $this->createUnderlyingWrapper();

        return $this->wrapper->dir_opendir($path, $options);
    }

    private function createUnderlyingWrapper(): object
    {
        $wrapper = new self::$underlyingWrapperClass;
        $wrapper->context = $this->context;

        return $wrapper;
    }

    /** @param  mixed[]  $args */
    public function __call(string $method, array $args): mixed
    {
        $wrapper = $this->wrapper ?? $this->createUnderlyingWrapper();

        return method_exists($wrapper, $method) ? $wrapper->$method(...$args) : false;
    }
}
