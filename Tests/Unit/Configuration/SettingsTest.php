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
            'format' => 'webp',
            'qualities' => ['webp' => 70],
            'processor' => 'local:async',
            'lqip' => 'dominant-color',
            'fixedHeightDprCap' => '2',
        ], 'enc-key');

        self::assertSame([320, 640, 1280], $settings->ladder);
        self::assertSame(1800, $settings->maxDimension);
        self::assertSame(2, $settings->fixedHeightDprCap);
        self::assertSame('webp', $settings->format);
        self::assertSame(['webp' => 70], $settings->qualities);
        self::assertSame('local:async', $settings->processor);
        self::assertSame('dominant-color', $settings->lqip);
        self::assertSame(hash_hmac('sha256', 'imaginator-url-signing', 'enc-key'), $settings->secret);
    }

    public function testFromArrayFallsBackToDefaults(): void
    {
        $settings = Settings::fromArray([], 'key');

        self::assertSame(Settings::DEFAULT_LADDER, $settings->ladder);
        // The default ladder covers up to 4K (UHD) out of the box.
        self::assertSame([320, 420, 560, 740, 980, 1300, 1720, 2000, 2560, 3200, 3840], $settings->ladder);
        self::assertSame(Settings::DEFAULT_MAX_DIMENSION, $settings->maxDimension);
        self::assertSame(3840, $settings->maxDimension);
        self::assertSame(Settings::DEFAULT_FIXED_HEIGHT_DPR_CAP, $settings->fixedHeightDprCap);
        self::assertSame(Settings::DEFAULT_FORMAT, $settings->format);
        self::assertSame('avif', $settings->format);
        self::assertSame('local:async', $settings->processor);
        self::assertSame('thumbhash', $settings->lqip);
        self::assertSame(hash_hmac('sha256', 'imaginator-url-signing', 'key'), $settings->secret);
    }

    public function testFormatFallsBackToFirstEntryOfLegacyFormatsList(): void
    {
        // Backward-compat: the old multi-format `formats` list collapses to its first entry.
        $settings = Settings::fromArray(['formats' => 'webp,avif'], 'key');

        self::assertSame('webp', $settings->format);
    }

    public function testInvalidFormatFallsBackToDefault(): void
    {
        $settings = Settings::fromArray(['format' => 'jpeg'], 'key');

        self::assertSame(Settings::DEFAULT_FORMAT, $settings->format);
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
            array_map(static fn(Breakpoint $b): array => [$b->key, $b->minWidth], $settings->breakpoints),
        );
    }

    public function testBreakpointsFallBackToDefault(): void
    {
        $settings = Settings::fromArray([], 'key');

        self::assertSame(
            [['xs', 0], ['sm', 576], ['md', 768], ['lg', 992], ['xl', 1200]],
            array_map(static fn(Breakpoint $b): array => [$b->key, $b->minWidth], $settings->breakpoints),
        );
    }

    public function testInvalidBreakpointEntriesAreSkipped(): void
    {
        $settings = Settings::fromArray(['breakpoints' => 'xs:0,bogus,sm:576,md:'], 'key');

        self::assertSame(
            [['xs', 0], ['sm', 576]],
            array_map(static fn(Breakpoint $b): array => [$b->key, $b->minWidth], $settings->breakpoints),
        );
    }

    public function testExcludeExtensionsFallBackToDefault(): void
    {
        $settings = Settings::fromArray([], 'key');

        self::assertSame(['svg', 'ai', 'eps', 'gif'], $settings->excludeExtensions);
    }

    public function testExcludeExtensionsAreParsedTrimmedAndLowercased(): void
    {
        $settings = Settings::fromArray(['excludeExtensions' => 'SVG, eps , Ai'], 'key');

        self::assertSame(['svg', 'eps', 'ai'], $settings->excludeExtensions);
    }

    public function testProcessorOptionsDefaultToEmpty(): void
    {
        $settings = Settings::fromArray([], 'key');

        self::assertSame([], $settings->processorOptions);
    }

    public function testProcessorOptionsCoerceScalarsAndDropNestedEntries(): void
    {
        // ext_conf nesting is mixed: scalars become strings, deeper arrays are dropped, not passed blind.
        $settings = Settings::fromArray(['processorOptions' => [
            'accountHash' => 'abc123',
            'timeout' => 30,
            'nested' => ['a' => 'b'],
        ]], 'key');

        self::assertSame(['accountHash' => 'abc123', 'timeout' => '30'], $settings->processorOptions);
    }

    public function testProcessorOptionsIgnoreNonArrayValue(): void
    {
        $settings = Settings::fromArray(['processorOptions' => 'not-a-map'], 'key');

        self::assertSame([], $settings->processorOptions);
    }
}
