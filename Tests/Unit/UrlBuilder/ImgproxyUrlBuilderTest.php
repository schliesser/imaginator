<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\UrlBuilder;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
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
