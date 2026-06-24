<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\AspectRatio;
use Schliesser\Imaginator\Dto\Breakpoint;
use Schliesser\Imaginator\Dto\BreakpointRatio;
use Schliesser\Imaginator\Service\RatioMapResolver;

final class RatioMapResolverTest extends TestCase
{
    /** @return Breakpoint[] */
    private function breakpoints(): array
    {
        return [
            new Breakpoint('xs', 0),
            new Breakpoint('sm', 576),
            new Breakpoint('md', 768),
            new Breakpoint('lg', 992),
        ];
    }

    public function testResolvesLargestFirstWithNullMediaForBase(): void
    {
        $result = (new RatioMapResolver())->fromJson('{"xs":"1:1","lg":"16:9"}', $this->breakpoints());

        self::assertEquals(
            [
                new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
                new BreakpointRatio(new AspectRatio(1, 1), null),
            ],
            $result,
        );
    }

    public function testBreakpointAbsentFromJsonIsOmitted(): void
    {
        $result = (new RatioMapResolver())->fromJson('{"md":"4:3"}', $this->breakpoints());

        self::assertEquals(
            [new BreakpointRatio(new AspectRatio(4, 3), '(min-width:768px)')],
            $result,
        );
    }

    public function testAutoRatioIsOmitted(): void
    {
        $result = (new RatioMapResolver())->fromJson('{"xs":"auto","md":"4:3"}', $this->breakpoints());

        self::assertEquals(
            [new BreakpointRatio(new AspectRatio(4, 3), '(min-width:768px)')],
            $result,
        );
    }

    public function testUnparsableValueThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RatioMapResolver())->fromJson('{"xs":"auto","lg":"nonsense","md":"4:3"}', $this->breakpoints());
    }

    public function testParseSpecReturnsRatioPxHeightOrNull(): void
    {
        $resolver = new RatioMapResolver();

        self::assertEquals(new AspectRatio(16, 9), $resolver->parseSpec('16:9'));
        self::assertSame(600, $resolver->parseSpec('600px'));
        self::assertSame(600, $resolver->parseSpec('600'));
        self::assertNull($resolver->parseSpec('auto'));
        self::assertNull($resolver->parseSpec(''));
    }

    public function testParseSpecThrowsOnGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RatioMapResolver())->parseSpec('foo');
    }

    public function testFromMapParsesPxValueAsFixedHeightTier(): void
    {
        $result = (new RatioMapResolver())->fromMap(['xs' => '16:9', 'lg' => '600px'], $this->breakpoints());

        self::assertEquals(
            [
                new BreakpointRatio(media: '(min-width:992px)', fixedHeight: 600),
                new BreakpointRatio(new AspectRatio(16, 9), null),
            ],
            $result,
        );
    }

    public function testFromMapThrowsOnBadValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new RatioMapResolver())->fromMap(['lg' => 'foo'], $this->breakpoints());
    }

    public function testEmptyOrInvalidJsonReturnsEmptyArray(): void
    {
        $resolver = new RatioMapResolver();

        self::assertSame([], $resolver->fromJson('', $this->breakpoints()));
        self::assertSame([], $resolver->fromJson('not json', $this->breakpoints()));
        self::assertSame([], $resolver->fromJson('[]', $this->breakpoints()));
        self::assertSame([], $resolver->fromJson('{}', $this->breakpoints()));
    }

    public function testFromMapResolvesAliasKeysLargestFirstWithNullBase(): void
    {
        $result = (new RatioMapResolver())->fromMap(['xs' => '1:1', 'lg' => '16:9'], $this->breakpoints());

        self::assertEquals(
            [
                new BreakpointRatio(new AspectRatio(16, 9), '(min-width:992px)'),
                new BreakpointRatio(new AspectRatio(1, 1), null),
            ],
            $result,
        );
    }

    public function testFromMapResolvesIntegerKeysAsMinWidth(): void
    {
        $result = (new RatioMapResolver())->fromMap([768 => '4:3', 1400 => '21:9'], $this->breakpoints());

        self::assertEquals(
            [
                new BreakpointRatio(new AspectRatio(21, 9), '(min-width:1400px)'),
                new BreakpointRatio(new AspectRatio(4, 3), '(min-width:768px)'),
            ],
            $result,
        );
    }

    public function testFromMapZeroKeyIsBaseWithNullMedia(): void
    {
        $result = (new RatioMapResolver())->fromMap([0 => '1:1', 'md' => '4:3'], $this->breakpoints());

        self::assertEquals(
            [
                new BreakpointRatio(new AspectRatio(4, 3), '(min-width:768px)'),
                new BreakpointRatio(new AspectRatio(1, 1), null),
            ],
            $result,
        );
    }

    public function testFromMapDropsUnknownAliasAndAutoRatio(): void
    {
        $result = (new RatioMapResolver())->fromMap(['xxl' => '16:9', 'md' => 'auto', 'lg' => '4:3'], $this->breakpoints());

        self::assertEquals(
            [new BreakpointRatio(new AspectRatio(4, 3), '(min-width:992px)')],
            $result,
        );
    }

    public function testFromMapEmptyReturnsEmpty(): void
    {
        self::assertSame([], (new RatioMapResolver())->fromMap([], $this->breakpoints()));
    }
}
