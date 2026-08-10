<?php

declare(strict_types=1);

namespace JMac\Testing\Tests\Engine;

use JMac\Testing\Engine\FinalBypass;
use PHPUnit\Framework\TestCase;

/**
 * Covers FinalBypass::modifyCode() only — the pure, filesystem-free token
 * rewriting FinalBypassMutatingWrapper delegates to on every `.php` read
 * once bypassing is enabled. enable() itself (registering the stream
 * wrapper process-wide) is proven end-to-end, #[RunInSeparateProcess], by
 * DoubleTest::test_bypass_finals_allows_doubling_a_final_class() — doing
 * that here too would mutate file:// for the rest of this test class's own
 * process, corrupting every other test's ability to rely on final-class
 * rejection.
 */
final class FinalBypassTest extends TestCase
{
    public function test_strips_final_immediately_before_class(): void
    {
        $code = '<?php final class Foo {}';

        $this->assertSame('<?php  class Foo {}', FinalBypass::modifyCode($code));
    }

    public function test_strips_final_across_whitespace_and_comments_before_class(): void
    {
        $code = "<?php final /* comment */\nclass Foo {}";

        $this->assertSame("<?php  /* comment */\nclass Foo {}", FinalBypass::modifyCode($code));
    }

    /**
     * `final readonly class` and `readonly final class` both compile in PHP
     * — the `readonly` in between still counts as "final precedes class",
     * and is left untouched either way since only `final` is ever stripped.
     */
    public function test_strips_final_regardless_of_readonly_ordering(): void
    {
        $this->assertSame(
            '<?php  readonly class Foo {}',
            FinalBypass::modifyCode('<?php final readonly class Foo {}'),
        );

        $this->assertSame(
            '<?php readonly  class Foo {}',
            FinalBypass::modifyCode('<?php readonly final class Foo {}'),
        );
    }

    /**
     * A final *method* is intentionally out of scope — see FinalBypass's own
     * docblock for why: ClassGenerator already has settled, separate
     * behavior for a final method on a non-final class (inherit it
     * unoverridden), and bypassing final classes has no reason to change
     * that.
     */
    public function test_leaves_a_final_method_untouched(): void
    {
        $code = '<?php class Foo { final public function bar() {} }';

        $this->assertSame($code, FinalBypass::modifyCode($code));
    }

    public function test_leaves_a_final_constant_untouched(): void
    {
        $code = '<?php class Foo { final const BAR = 1; }';

        $this->assertSame($code, FinalBypass::modifyCode($code));
    }

    public function test_leaves_code_without_final_byte_identical(): void
    {
        $code = '<?php class Foo { public function bar(): int { return 1; } }';

        $this->assertSame($code, FinalBypass::modifyCode($code));
    }

    /**
     * The word "final" appearing outside of the `final` keyword itself
     * (inside a string, here) must never trigger a rewrite — token_get_all()
     * only ever classifies the actual reserved word as T_FINAL, never text
     * that merely spells the same way inside other tokens.
     */
    public function test_leaves_the_word_final_inside_a_string_untouched(): void
    {
        $code = '<?php class Foo { public function bar(): string { return "final"; } }';

        $this->assertSame($code, FinalBypass::modifyCode($code));
    }

    /**
     * TOKEN_PARSE (used so token_get_all() can see structural context, not
     * just individual tokens) throws CompileError on code PHP can't parse at
     * all — returning the input unchanged rather than letting that escape is
     * the same fallback dg/bypass-finals itself uses, since a file this
     * broken was never going to compile either way.
     */
    public function test_leaves_unparsable_code_unchanged(): void
    {
        $code = '<?php final class Foo { public function bar() {';

        $this->assertSame($code, FinalBypass::modifyCode($code));
    }
}
