<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\UrlBuilder;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\Rectangle;

/**
 * Builds imagor URLs (thumbor-compatible grammar). Signed scheme:
 *   {base}/{signature}/{w}x{h}/smart/filters:quality({q}):format({ext})/{source}
 * With an editor crop rect the `smart` segment is replaced by a manual crop applied before the
 * resize — left x top : right x bottom in source pixels:
 *   {base}/{signature}/{x1}x{y1}:{x2}x{y2}/{w}x{h}/filters:quality({q}):format({ext})/{source}
 * where signature = urlsafe-base64( HMAC_SHA256(secret, path) ), path being everything after the
 * signature segment (no leading slash). Matches IMAGOR_SIGNER_TYPE=sha256; the secret is imagor's
 * IMAGOR_SECRET (a plain string). Without a secret the literal `unsafe` segment is used (dev mode,
 * requires IMAGOR_UNSAFE=1 server-side).
 *
 * The source is passed as given by {@see \Schliesser\Imaginator\Imaging\External\ExternalImageProcessor}
 * a root-relative path when imagor has HTTP_LOADER_BASE_URL set, or an absolute URL otherwise.
 */
#[AsImaginatorProcessor('imagor')]
final readonly class ImagorUrlBuilder implements UrlBuilderInterface
{
    public function __construct(private ExternalConfig $config) {}

    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        $cropSegment = $crop === null ? '' : sprintf(
            '%dx%d:%dx%d/',
            (int) round($crop->x),
            (int) round($crop->y),
            (int) round($crop->x + $crop->width),
            (int) round($crop->y + $crop->height),
        );
        $path = sprintf(
            '%s%dx%d%s/filters:quality(%d):format(%s)/%s',
            $cropSegment,
            $variant->width,
            $variant->height,
            $crop === null ? '/smart' : '',
            $variant->quality,
            $variant->format,
            $this->encodeSource($sourceUrl),
        );
        $base = rtrim($this->config->baseUrl, '/');

        if ($this->config->signKey === '') {
            return $base . '/unsafe/' . $path;
        }

        $signature = strtr(base64_encode(hash_hmac(
            'sha256',
            $path,
            $this->config->signKey,
            true,
        )), '+/', '-_');

        return $base . '/' . $signature . '/' . $path;
    }

    /**
     * An absolute source URL is percent-encoded wholesale: its literal `//` would be merged to `/`
     * by reverse proxies in front of imagor (Traefik sanitizePath, nginx merge_slashes) *before*
     * signature verification, breaking the HMAC. imagor decodes the escaped URI; relative paths
     * carry no double slash and stay readable.
     */
    private function encodeSource(string $sourceUrl): string
    {
        return str_contains($sourceUrl, '://') ? rawurlencode($sourceUrl) : $sourceUrl;
    }
}
