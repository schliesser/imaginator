<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\UrlBuilder;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\UrlBuilder\ImagorUrlBuilder;

final class ImagorUrlBuilderTest extends TestCase
{
    private function variant(): ImageVariant
    {
        // isReference, uid, cropVariant, width, height, format, quality
        return new ImageVariant(false, 5, 'default', 2000, 1125, 'webp', 72);
    }

    public function testUnsafeUrlWhenNoSecret(): void
    {
        $builder = new ImagorUrlBuilder(new ExternalConfig('https://imagor.example:8083/'));

        self::assertSame(
            'https://imagor.example:8083/unsafe/2000x1125/smart/filters:quality(72):format(webp)/fileadmin/demo/hero.jpg',
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg'),
        );
    }

    public function testSignedUrlMatchesImagorScheme(): void
    {
        // imagor secrets are plain strings (IMAGOR_SECRET), not hex like imgproxy's key/salt.
        $secret = 'imaginator-dev-secret';
        $builder = new ImagorUrlBuilder(new ExternalConfig('https://imagor.example', $secret));

        $path = '2000x1125/smart/filters:quality(72):format(webp)/fileadmin/demo/hero.jpg';
        $sig = strtr(base64_encode(hash_hmac('sha256', $path, $secret, true)), '+/', '-_');

        self::assertSame(
            'https://imagor.example/' . $sig . '/' . $path,
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg'),
        );
    }

    public function testCropRectEmitsManualCropSegmentInsteadOfSmart(): void
    {
        $builder = new ImagorUrlBuilder(new ExternalConfig('https://imagor.example'));

        // imagor's crop segment is left x top : right x bottom in source pixels, applied before
        // the resize — the rect already encodes ratio + focus, so `smart` is dropped.
        self::assertSame(
            'https://imagor.example/unsafe/200x100:1978x1100/2000x1125/filters:quality(72):format(webp)/fileadmin/demo/hero.jpg',
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg', new Rectangle(200.4, 99.6, 1777.8, 1000.0)),
        );
    }

    public function testCropRectIsSigned(): void
    {
        $secret = 'imaginator-dev-secret';
        $builder = new ImagorUrlBuilder(new ExternalConfig('https://imagor.example', $secret));

        $path = '100x50:2100x1175/2000x1125/filters:quality(72):format(webp)/fileadmin/demo/hero.jpg';
        $sig = strtr(base64_encode(hash_hmac('sha256', $path, $secret, true)), '+/', '-_');

        self::assertSame(
            'https://imagor.example/' . $sig . '/' . $path,
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg', new Rectangle(100, 50, 2000, 1125)),
        );
    }

    public function testSaltIsIgnored(): void
    {
        // Unified settings expose a salt field; imagor's scheme has none — same secret, same URL.
        $withSalt = new ImagorUrlBuilder(new ExternalConfig('https://imagor.example', 'secret', 'deadbeef'));
        $withoutSalt = new ImagorUrlBuilder(new ExternalConfig('https://imagor.example', 'secret'));

        self::assertSame(
            $withoutSalt->build($this->variant(), 'fileadmin/demo/hero.jpg'),
            $withSalt->build($this->variant(), 'fileadmin/demo/hero.jpg'),
        );
    }
}
