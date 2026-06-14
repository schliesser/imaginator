<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * Configuration for an external (offloaded) image processor. `signKey`/`salt` are the provider's
 * hex-encoded HMAC credentials; when either is empty the builder emits an unsigned/insecure URL
 * (fine for local dev, e.g. the ddev-imgproxy addon which runs keyless).
 */
final readonly class ExternalConfig
{
    public function __construct(
        public string $baseUrl,
        public string $signKey = '',
        public string $salt = '',
    ) {}
}
