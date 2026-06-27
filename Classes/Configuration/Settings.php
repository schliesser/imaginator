<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Configuration;

use Schliesser\Imaginator\Dto\Breakpoint;

/**
 * Parsed imaginator configuration. The pure {@see self::fromArray()} parser is unit-tested;
 * the DI side that sources the raw array (Site-Set settings + encryptionKey) lives in
 * {@see SettingsFactory}.
 */
final readonly class Settings
{
    public const DEFAULT_LADDER = [320, 420, 560, 740, 980, 1300, 1720, 2000];
    public const DEFAULT_MAX_DIMENSION = 2000;
    /** Highest device-pixel-ratio a fixed-height hero ships a `min-resolution` source for (1x..cap). */
    public const DEFAULT_FIXED_HEIGHT_DPR_CAP = 3;
    /** Single output format. AVIF is the universal default — broad modern-browser support, best ratio. */
    public const DEFAULT_FORMAT = 'avif';
    /** The only output formats imaginator emits; anything else falls back to {@see DEFAULT_FORMAT}. */
    public const ALLOWED_FORMATS = ['avif', 'webp'];
    public const DEFAULT_QUALITIES = ['avif' => 50, 'webp' => 72];
    public const DEFAULT_LQIP = 'thumbhash';
    public const DEFAULT_BREAKPOINTS = ['xs' => 0, 'sm' => 576, 'md' => 768, 'lg' => 992, 'xl' => 1200];
    /** Non-raster vector formats + gif: served as-is, never run through the ladder/processor. */
    public const DEFAULT_EXCLUDE_EXTENSIONS = ['svg', 'ai', 'eps', 'gif'];

    /**
     * @param int[]               $ladder
     * @param list<string>        $secrets index 0 signs, all verify (key rotation)
     * @param string              $format    single output format (avif|webp), applied uniformly
     * @param array<string, int>  $qualities
     * @param Breakpoint[]        $breakpoints ordered by minWidth ascending; the min-0 entry is the base
     * @param list<string>        $excludeExtensions lowercased file extensions served as-is (no processing)
     */
    public function __construct(
        public array $ladder,
        public int $maxDimension,
        public int $fixedHeightDprCap,
        public array $secrets,
        public string $format,
        public array $qualities,
        public string $processor,
        public string $lqip,
        public array $breakpoints,
        public array $excludeExtensions,
        public string $processorBaseUrl = '',
        public string $processorSignKey = '',
        public string $processorSalt = '',
        public string $processorSourceBaseUrl = '',
    ) {}

    /**
     * @param array<string, mixed> $raw site-set settings under the `imaginator.` namespace
     */
    public static function fromArray(array $raw, string $encryptionKey): self
    {
        $ladder = self::intList($raw['ladder'] ?? null);
        $qualities = self::qualityMap($raw['qualities'] ?? null);
        $excludeExtensions = array_map(
            'strtolower',
            self::stringList($raw['excludeExtensions'] ?? null),
        );

        return new self(
            $ladder !== [] ? $ladder : self::DEFAULT_LADDER,
            (int) ($raw['maxDimension'] ?? self::DEFAULT_MAX_DIMENSION),
            (int) ($raw['fixedHeightDprCap'] ?? self::DEFAULT_FIXED_HEIGHT_DPR_CAP),
            self::deriveSecrets($encryptionKey, $raw['secretsRotation'] ?? null),
            self::format($raw),
            $qualities !== [] ? $qualities : self::DEFAULT_QUALITIES,
            (string) ($raw['processor'] ?? 'local:async'),
            (string) ($raw['lqip'] ?? self::DEFAULT_LQIP),
            self::breakpoints($raw['breakpoints'] ?? null),
            $excludeExtensions !== [] ? $excludeExtensions : self::DEFAULT_EXCLUDE_EXTENSIONS,
            (string) ($raw['processorBaseUrl'] ?? ''),
            (string) ($raw['processorSignKey'] ?? ''),
            (string) ($raw['processorSalt'] ?? ''),
            (string) ($raw['processorSourceBaseUrl'] ?? ''),
        );
    }

    /**
     * Parse a `key:px` comma list into {@see Breakpoint}s sorted by minWidth ascending. Entries
     * without a valid `key:px` shape are skipped; an empty result falls back to the default set.
     *
     * @return Breakpoint[]
     */
    private static function breakpoints(mixed $value): array
    {
        $parsed = [];
        foreach (self::stringList($value) as $entry) {
            if (!preg_match('/^([a-z0-9]+):(\d+)$/i', $entry, $m)) {
                continue;
            }
            $parsed[] = new Breakpoint($m[1], (int) $m[2]);
        }
        if ($parsed === []) {
            foreach (self::DEFAULT_BREAKPOINTS as $key => $minWidth) {
                $parsed[] = new Breakpoint($key, $minWidth);
            }
        }
        usort($parsed, static fn(Breakpoint $a, Breakpoint $b): int => $a->minWidth <=> $b->minWidth);

        return $parsed;
    }

    /**
     * Single output format (avif|webp). Prefers the `format` key; for backward-compat with the old
     * multi-format `formats` list, falls back to its first entry. Anything outside {@see ALLOWED_FORMATS}
     * (or unset) resolves to {@see DEFAULT_FORMAT}.
     *
     * @param array<string, mixed> $raw
     */
    private static function format(array $raw): string
    {
        $value = strtolower(trim((string) ($raw['format'] ?? '')));
        if ($value === '') {
            $value = strtolower(self::stringList($raw['formats'] ?? null)[0] ?? '');
        }

        return in_array($value, self::ALLOWED_FORMATS, true) ? $value : self::DEFAULT_FORMAT;
    }

    /** @return list<string> */
    private static function deriveSecrets(string $encryptionKey, mixed $rotation): array
    {
        $secrets = [hash_hmac('sha256', 'imaginator-url-signing', $encryptionKey)];
        foreach (self::stringList($rotation) as $old) {
            $secrets[] = $old;
        }

        return $secrets;
    }

    /** @return int[] */
    private static function intList(mixed $value): array
    {
        return array_map(static fn(string $v): int => (int) $v, self::stringList($value));
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            $value = explode(',', (string) $value);
        }

        return array_values(array_filter(array_map(
            static fn(mixed $v): string => trim((string) $v),
            $value,
        ), static fn(string $v): bool => $v !== ''));
    }

    /** @return array<string, int> */
    private static function qualityMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $format => $quality) {
            $map[(string) $format] = (int) $quality;
        }

        return $map;
    }
}
