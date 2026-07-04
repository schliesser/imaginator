<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\UrlBuilder;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\UrlBuilder\ImgproxyUrlBuilder;

final class ImgproxyUrlBuilderTest extends TestCase
{
    private function variant(): ImageVariant
    {
        // isReference, uid, cropVariant, width, height, format, quality
        return new ImageVariant(false, 5, 'default', 2000, 1125, 'webp', 72);
    }

    public function testInsecureUrlWhenNoKey(): void
    {
        $builder = new ImgproxyUrlBuilder(new ExternalConfig('https://imgproxy.example:8081/'));

        self::assertSame(
            'https://imgproxy.example:8081/insecure/rs:fill:2000:1125/g:sm/q:72/plain/fileadmin/demo/hero.jpg@webp',
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg'),
        );
    }

    public function testCropRectEmitsCropOpWithNorthWestOffsetInsteadOfSmartGravity(): void
    {
        $builder = new ImgproxyUrlBuilder(new ExternalConfig('https://imgproxy.example'));

        // Editor crop rect (already ratio-fitted + focus-positioned) → imgproxy crop op with
        // north-west gravity + absolute offsets, applied before the fill-resize; `g:sm` is dropped.
        self::assertSame(
            'https://imgproxy.example/insecure/c:1778:1000:nowe:200:100/rs:fill:2000:1125/q:72/plain/fileadmin/demo/hero.jpg@webp',
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg', new Rectangle(200.4, 99.6, 1777.8, 1000.0)),
        );
    }

    public function testCropRectIsSigned(): void
    {
        $key = '736563726574';
        $salt = '68656c6c6f';
        $builder = new ImgproxyUrlBuilder(new ExternalConfig('https://imgproxy.example', $key, $salt));

        $path = '/c:2000:1125:nowe:100:50/rs:fill:2000:1125/q:72/plain/fileadmin/demo/hero.jpg@webp';
        $sig = rtrim(strtr(base64_encode(
            hash_hmac('sha256', (string) hex2bin($salt) . $path, (string) hex2bin($key), true)
        ), '+/', '-_'), '=');

        self::assertSame(
            'https://imgproxy.example/' . $sig . $path,
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg', new Rectangle(100, 50, 2000, 1125)),
        );
    }

    public function testAbsoluteSourceUrlIsPercentEncoded(): void
    {
        // Same reverse-proxy double-slash hazard as imagor: a literal `https://` in the path gets
        // merged before imgproxy verifies the signature. imgproxy decodes an escaped plain source.
        $builder = new ImgproxyUrlBuilder(new ExternalConfig('https://imgproxy.example'));

        self::assertSame(
            'https://imgproxy.example/insecure/rs:fill:2000:1125/g:sm/q:72/plain/https%3A%2F%2Forigin.example%2Ffileadmin%2Fdemo%2Fhero.jpg@webp',
            $builder->build($this->variant(), 'https://origin.example/fileadmin/demo/hero.jpg'),
        );
    }

    public function testSignedUrlMatchesImgproxyScheme(): void
    {
        $key = '736563726574';   // "secret" hex
        $salt = '68656c6c6f';    // "hello" hex
        $builder = new ImgproxyUrlBuilder(new ExternalConfig('https://imgproxy.example', $key, $salt));

        $path = '/rs:fill:2000:1125/g:sm/q:72/plain/fileadmin/demo/hero.jpg@webp';
        $sig = rtrim(strtr(base64_encode(
            hash_hmac('sha256', (string) hex2bin($salt) . $path, (string) hex2bin($key), true)
        ), '+/', '-_'), '=');

        self::assertSame(
            'https://imgproxy.example/' . $sig . $path,
            $builder->build($this->variant(), 'fileadmin/demo/hero.jpg'),
        );
    }
}
