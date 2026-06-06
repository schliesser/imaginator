<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Configuration;

/**
 * Parsed imaginator configuration. The pure {@see self::fromArray()} parser is unit-tested;
 * the DI side that sources the raw array (Site-Set settings + encryptionKey) lives in
 * {@see SettingsFactory}.
 */
final readonly class Settings
{
    public const DEFAULT_LADDER = [320, 420, 560, 740, 980, 1300, 1720, 2000];
    public const DEFAULT_MAX_DIMENSION = 2000;
    public const DEFAULT_FORMATS = ['avif', 'webp'];
    public const DEFAULT_QUALITIES = ['avif' => 50, 'webp' => 72];
    public const DEFAULT_LQIP = 'thumbhash';

    /**
     * @param int[]               $ladder
     * @param list<string>        $secrets index 0 signs, all verify (key rotation)
     * @param string[]            $formats
     * @param array<string, int>  $qualities
     */
    public function __construct(
        public array $ladder,
        public int $maxDimension,
        public array $secrets,
        public array $formats,
        public array $qualities,
        public string $processor,
        public string $lqip,
    ) {}

    /**
     * @param array<string, mixed> $raw site-set settings under the `imaginator.` namespace
     */
    public static function fromArray(array $raw, string $encryptionKey): self
    {
        $ladder = self::intList($raw['ladder'] ?? null);
        $formats = self::stringList($raw['formats'] ?? null);
        $qualities = self::qualityMap($raw['qualities'] ?? null);

        return new self(
            $ladder !== [] ? $ladder : self::DEFAULT_LADDER,
            (int)($raw['maxDimension'] ?? self::DEFAULT_MAX_DIMENSION),
            self::deriveSecrets($encryptionKey, $raw['secretsRotation'] ?? null),
            $formats !== [] ? $formats : self::DEFAULT_FORMATS,
            $qualities !== [] ? $qualities : self::DEFAULT_QUALITIES,
            (string)($raw['processor'] ?? 'local'),
            (string)($raw['lqip'] ?? self::DEFAULT_LQIP),
        );
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
        return array_values(array_map(static fn (string $v): int => (int)$v, self::stringList($value)));
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            $value = explode(',', (string)$value);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $v): string => trim((string)$v),
            $value,
        ), static fn (string $v): bool => $v !== ''));
    }

    /** @return array<string, int> */
    private static function qualityMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $format => $quality) {
            $map[(string)$format] = (int)$quality;
        }

        return $map;
    }
}
