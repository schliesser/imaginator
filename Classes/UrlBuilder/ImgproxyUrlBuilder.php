<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\UrlBuilder;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;

/**
 * Builds imgproxy URLs. Signed scheme:
 *   {base}/{signature}/rs:fill:{w}:{h}/g:sm/q:{quality}/plain/{source}@{ext}
 * With an editor crop rect the smart gravity is replaced by a manual crop op (north-west gravity +
 * absolute offsets) applied before the fill-resize:
 *   {base}/{signature}/c:{cw}:{ch}:nowe:{x}:{y}/rs:fill:{w}:{h}/q:{quality}/plain/{source}@{ext}
 * where signature = urlsafe-base64( HMAC_SHA256(hex2bin(signKey), hex2bin(salt) . path) ), path being
 * everything after the signature (leading slash included). Without key+salt the literal `insecure`
 * segment is used (dev mode, e.g. the ddev-imgproxy addon).
 *
 * The source is passed as given by {@see \Schliesser\Imaginator\Imaging\External\ExternalImageProcessor}
 * — a root-relative path when imgproxy has IMGPROXY_BASE_URL set, or an absolute URL otherwise.
 */
#[AsImaginatorProcessor('imgproxy')]
final readonly class ImgproxyUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private ExternalConfig $config) {}

    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        $gravity = $crop === null ? '/g:sm' : '';
        $cropOp = $crop === null ? '' : sprintf(
            '/c:%d:%d:nowe:%d:%d',
            (int) round($crop->width),
            (int) round($crop->height),
            (int) round($crop->x),
            (int) round($crop->y),
        );
        $path = sprintf(
            '%s/rs:fill:%d:%d%s/q:%d/plain/%s@%s',
            $cropOp,
            $variant->width,
            $variant->height,
            $gravity,
            $variant->quality,
            $this->encodeSource($sourceUrl),
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

    /**
     * An absolute source URL is percent-encoded wholesale: its literal `//` would be merged to `/`
     * by reverse proxies in front of imgproxy (Traefik sanitizePath, nginx merge_slashes) *before*
     * signature verification, breaking the HMAC. imgproxy decodes an escaped `plain/` source;
     * relative paths carry no double slash and stay readable.
     */
    private function encodeSource(string $sourceUrl): string
    {
        return str_contains($sourceUrl, '://') ? rawurlencode($sourceUrl) : $sourceUrl;
    }
}
