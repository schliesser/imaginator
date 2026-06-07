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

    public function testAutoOrUnknownRatioIsOmitted(): void
    {
        $result = (new RatioMapResolver())->fromJson('{"xs":"auto","lg":"nonsense","md":"4:3"}', $this->breakpoints());

        self::assertEquals(
            [new BreakpointRatio(new AspectRatio(4, 3), '(min-width:768px)')],
            $result,
        );
    }

    public function testEmptyOrInvalidJsonReturnsEmptyArray(): void
    {
        $resolver = new RatioMapResolver();

        self::assertSame([], $resolver->fromJson('', $this->breakpoints()));
        self::assertSame([], $resolver->fromJson('not json', $this->breakpoints()));
        self::assertSame([], $resolver->fromJson('[]', $this->breakpoints()));
        self::assertSame([], $resolver->fromJson('{}', $this->breakpoints()));
    }
}
