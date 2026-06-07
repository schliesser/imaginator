<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Configuration;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Configuration\Settings;
use Schliesser\Imaginator\Dto\Breakpoint;

final class SettingsTest extends TestCase
{
    public function testFromArrayParsesValuesAndDerivesSecret(): void
    {
        $settings = Settings::fromArray([
            'ladder' => '320,640,1280',
            'maxDimension' => '1800',
            'formats' => 'webp',
            'qualities' => ['webp' => 70],
            'processor' => 'local',
            'lqip' => 'dominant-color',
            'secretsRotation' => ['old-secret'],
        ], 'enc-key');

        self::assertSame([320, 640, 1280], $settings->ladder);
        self::assertSame(1800, $settings->maxDimension);
        self::assertSame(['webp'], $settings->formats);
        self::assertSame(['webp' => 70], $settings->qualities);
        self::assertSame('local', $settings->processor);
        self::assertSame('dominant-color', $settings->lqip);
        self::assertSame(hash_hmac('sha256', 'imaginator-url-signing', 'enc-key'), $settings->secrets[0]);
        self::assertSame('old-secret', $settings->secrets[1]);
    }

    public function testFromArrayFallsBackToDefaults(): void
    {
        $settings = Settings::fromArray([], 'key');

        self::assertSame(Settings::DEFAULT_LADDER, $settings->ladder);
        self::assertSame(Settings::DEFAULT_MAX_DIMENSION, $settings->maxDimension);
        self::assertSame(Settings::DEFAULT_FORMATS, $settings->formats);
        self::assertSame('local', $settings->processor);
        self::assertSame('thumbhash', $settings->lqip);
        self::assertCount(1, $settings->secrets); // derived key only, no rotation
    }

    public function testLadderAcceptsArrayInput(): void
    {
        $settings = Settings::fromArray(['ladder' => [200, 400, 800]], 'key');

        self::assertSame([200, 400, 800], $settings->ladder);
    }

    public function testBreakpointsAreParsedAndSortedByMinWidth(): void
    {
        // Deliberately out of order in the input.
        $settings = Settings::fromArray(['breakpoints' => 'lg:992,xs:0,sm:576'], 'key');

        self::assertSame(
            [['xs', 0], ['sm', 576], ['lg', 992]],
            array_map(static fn (Breakpoint $b): array => [$b->key, $b->minWidth], $settings->breakpoints),
        );
    }

    public function testBreakpointsFallBackToDefault(): void
    {
        $settings = Settings::fromArray([], 'key');

        self::assertSame(
            [['xs', 0], ['sm', 576], ['md', 768], ['lg', 992], ['xl', 1200]],
            array_map(static fn (Breakpoint $b): array => [$b->key, $b->minWidth], $settings->breakpoints),
        );
    }

    public function testInvalidBreakpointEntriesAreSkipped(): void
    {
        $settings = Settings::fromArray(['breakpoints' => 'xs:0,bogus,sm:576,md:'], 'key');

        self::assertSame(
            [['xs', 0], ['sm', 576]],
            array_map(static fn (Breakpoint $b): array => [$b->key, $b->minWidth], $settings->breakpoints),
        );
    }
}
