<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Dto;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\ExternalConfig;

final class ExternalConfigTest extends TestCase
{
    public function testOptionReturnsValueOrDefault(): void
    {
        $config = new ExternalConfig('https://cdn.example', options: ['accountHash' => 'abc123']);

        self::assertSame('abc123', $config->option('accountHash'));
        self::assertSame('fallback', $config->option('missing', 'fallback'));
        self::assertSame('', $config->option('missing'));
    }

    public function testRequireOptionReturnsValue(): void
    {
        $config = new ExternalConfig('https://cdn.example', options: ['variant' => 'public']);

        self::assertSame('public', $config->requireOption('variant'));
    }

    public function testRequireOptionThrowsDescriptivelyWhenMissing(): void
    {
        $config = new ExternalConfig('https://cdn.example');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/accountHash/');
        $this->expectExceptionMessageMatches('/processorOptions/');
        $config->requireOption('accountHash');
    }

    public function testRequireOptionMessagePointsAtTheConfiguredOptionsSource(): void
    {
        $config = new ExternalConfig('https://cdn.example', optionsSource: "['EXTENSIONS']['my_cdn']");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/\['EXTENSIONS'\]\['my_cdn'\]\['accountHash'\]/");
        $config->requireOption('accountHash');
    }

    public function testOptionsDefaultToEmpty(): void
    {
        $config = new ExternalConfig('https://cdn.example', 'key', 'salt');

        self::assertSame([], $config->options);
    }
}
