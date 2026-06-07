<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Url\CanonicalParams;
use Schliesser\Imaginator\Url\SignedUrlBuilder;

final class SignedUrlBuilderTest extends TestCase
{
    private CanonicalParams $params;

    protected function setUp(): void
    {
        $this->params = new CanonicalParams(false, 4567, 'hero', 1280, 720, 'webp');
    }

    public function testBuildProducesExpectedShapeForFile(): void
    {
        $url = (new SignedUrlBuilder(['s3cr3t']))->build($this->params);
        self::assertMatchesRegularExpression(
            '#^/_imaginator/[0-9a-f]{16}/f4567/hero/1280x720\.webp$#',
            $url
        );
    }

    public function testBuildProducesExpectedShapeForReference(): void
    {
        $url = (new SignedUrlBuilder(['s3cr3t']))->build(new CanonicalParams(true, 4567, 'hero', 1280, 720, 'webp'));
        self::assertMatchesRegularExpression(
            '#^/_imaginator/[0-9a-f]{16}/r4567/hero/1280x720\.webp$#',
            $url
        );
    }

    public function testRoundTripVerifies(): void
    {
        $b = new SignedUrlBuilder(['s3cr3t']);
        $verified = $b->verify($b->build($this->params));
        self::assertEquals($this->params, $verified);
    }

    public function testTamperedSizeFailsVerification(): void
    {
        $b = new SignedUrlBuilder(['s3cr3t']);
        $url = $b->build($this->params);
        $tampered = str_replace('1280x720', '4000x720', $url);
        self::assertNull($b->verify($tampered));
    }

    public function testKeyRotationVerifiesAgainstOldSecret(): void
    {
        $oldUrl = (new SignedUrlBuilder(['old-secret']))->build($this->params);
        $rotated = new SignedUrlBuilder(['new-secret', 'old-secret']); // new active, old still valid
        self::assertEquals($this->params, $rotated->verify($oldUrl));
    }

    public function testWrongSecretFails(): void
    {
        $url = (new SignedUrlBuilder(['old-secret']))->build($this->params);
        self::assertNull((new SignedUrlBuilder(['new-secret']))->verify($url));
    }

    public function testGarbagePathReturnsNull(): void
    {
        self::assertNull((new SignedUrlBuilder(['s']))->verify('/not/ours.jpg'));
    }

    public function testEmptySecretsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new SignedUrlBuilder([]);
    }
}
