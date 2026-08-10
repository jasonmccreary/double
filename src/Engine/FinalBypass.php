<?php

declare(strict_types=1);

namespace JMac\Testing\Engine;

/**
 * @internal
 *
 * Rewrites `final class` to `class` in PHP source read through the file://
 * protocol, so a target class Double::for() would otherwise reject via
 * InvalidDoubleTargetException::isFinal() can be subclassed instead. Backs
 * Double::bypassFinals().
 *
 * Deliberately narrower than dg/bypass-finals — the package this technique
 * (and FinalBypassMutatingWrapper/FinalBypassNativeWrapper's stream-wrapper
 * plumbing) is adapted from: only `final` immediately preceding `class` is
 * ever touched. A `final` method or constant on an otherwise non-final class
 * is left exactly as written — ClassGenerator already treats a final method
 * as "inherit the real implementation, don't override it" (see
 * ClassGenerator::overridableMethods()), and that's an intentional, separate
 * behavior this bypass has no reason to change.
 *
 * The reason this can't live inside ClassGenerator instead, checked lazily
 * per target: by the time `Double::for()` runs, `class_exists($target)` has
 * already triggered the normal autoloader, and PHP has already compiled the
 * class with `final` baked in — there's no undoing that for a class already
 * loaded, only preventing it for one that isn't yet. Hence enable() has to
 * run before the target is ever referenced anywhere in the process, not at
 * double-creation time.
 */
final class FinalBypass
{
    public static function enable(): void
    {
        // If some other stream wrapper (ours from an earlier call, or a
        // third party's) is already active for file://, defer to it rather
        // than blindly registering over it — matches the check
        // dg/bypass-finals itself uses for the same reason.
        $handle = fopen(__FILE__, 'r') ?: throw new \RuntimeException('Unable to open '.__FILE__);
        $wrapper = stream_get_meta_data($handle)['wrapper_data'] ?? null;

        if ($wrapper instanceof FinalBypassMutatingWrapper) {
            return;
        }

        // FinalBypassMutatingWrapper delegates every real filesystem
        // operation to a FinalBypassNativeWrapper instance, including its
        // own class file the first time anything needs it. Autoload that
        // now, through the still-native file:// handler, or the first such
        // load after the swap below would have to go through the very
        // wrapper that's waiting on it — a cycle Composer's autoloader has
        // no way out of, and the one dg/bypass-finals itself sidesteps by
        // require_once-ing both wrapper classes ahead of enable().
        class_exists(FinalBypassNativeWrapper::class);

        FinalBypassMutatingWrapper::$underlyingWrapperClass = $wrapper
            ? $wrapper::class
            : FinalBypassNativeWrapper::class;

        stream_wrapper_unregister('file');
        stream_wrapper_register('file', FinalBypassMutatingWrapper::class);
    }

    /**
     * @internal Used only by FinalBypassMutatingWrapper::stream_open().
     */
    public static function modifyCode(string $code): string
    {
        if (! str_contains($code, 'final')) {
            return $code;
        }

        try {
            $tokens = token_get_all($code, TOKEN_PARSE);
        } catch (\CompileError) { // also covers ParseError
            return $code;
        }

        $modified = '';

        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                $modified .= $token;

                continue;
            }

            $modified .= $token[0] === T_FINAL && self::precedesClass($tokens, $i) ? '' : $token[1];
        }

        return $modified;
    }

    /**
     * `final` and `readonly` may appear on a class declaration in either
     * order (`final readonly class` and `readonly final class` both
     * compile), so a `readonly` in between still counts as "precedes class".
     *
     * @param  list<string|array{int, string, int}>  $tokens
     */
    private static function precedesClass(array $tokens, int $i): bool
    {
        for ($j = $i + 1; isset($tokens[$j]); $j++) {
            $next = $tokens[$j];

            if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_READONLY], true)) {
                continue;
            }

            return is_array($next) && $next[0] === T_CLASS;
        }

        return false;
    }
}
