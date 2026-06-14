<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External\UrlBuilder;

use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;

/**
 * Builds imgproxy URLs. Signed scheme:
 *   {base}/{signature}/rs:fill:{w}:{h}/g:sm/q:{quality}/plain/{source}@{ext}
 * where signature = urlsafe-base64( HMAC_SHA256(hex2bin(signKey), hex2bin(salt) . path) ), path being
 * everything after the signature (leading slash included). Without key+salt the literal `insecure`
 * segment is used (dev mode, e.g. the ddev-imgproxy addon).
 *
 * The source is passed as given by {@see \Schliesser\Imaginator\Imaging\External\ExternalImageProcessor}
 * — a root-relative path when imgproxy has IMGPROXY_BASE_URL set, or an absolute URL otherwise.
 */
final readonly class ImgproxyUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private ExternalConfig $config) {}

    public function build(ImageVariant $variant, string $sourceUrl): string
    {
        $path = sprintf(
            '/rs:fill:%d:%d/g:sm/q:%d/plain/%s@%s',
            $variant->width,
            $variant->height,
            $variant->quality,
            $sourceUrl,
            $variant->format,
        );
        $base = rtrim($this->config->baseUrl, '/');

        if ($this->config->signKey === '' || $this->config->salt === '') {
            return $base . '/insecure' . $path;
        }

        $signature = rtrim(strtr(base64_encode(hash_hmac(
            'sha256',
            (string) hex2bin($this->config->salt) . $path,
            (string) hex2bin($this->config->signKey),
            true,
        )), '+/', '-_'), '=');

        return $base . '/' . $signature . $path;
    }
}
