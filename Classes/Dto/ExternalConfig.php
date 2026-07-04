<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * Configuration for an external (offloaded) image processor. `signKey`/`salt` are the provider's
 * HMAC credentials — their encoding/meaning is provider-specific: imgproxy takes a hex-encoded
 * key + salt pair, imagor a single plain-string secret (salt unused). When the required credential
 * is empty the builder emits an unsigned/insecure URL (fine for local dev, e.g. the ddev-imgproxy
 * addon which runs keyless).
 */
final readonly class ExternalConfig
{
    public function __construct(
        public string $baseUrl,
        public string $signKey = '',
        public string $salt = '',
    ) {}
}
