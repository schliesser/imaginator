<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Dto;

/**
 * Configuration for an external (offloaded) image processor. `signKey`/`salt` are the provider's
 * HMAC credentials — their encoding/meaning is provider-specific: imgproxy takes a hex-encoded
 * key + salt pair, imagor a single plain-string secret (salt unused). When the required credential
 * is empty the builder emits an unsigned/insecure URL (fine for local dev, e.g. the ddev-imgproxy
 * addon which runs keyless).
 *
 * `options` carries provider-specific extras (e.g. Cloudflare accountHash/variant) from the
 * `processorOptions` Extension Configuration subtree; builders consume what they need via
 * {@see option()}/{@see requireOption()} and ignore the rest.
 */
final readonly class ExternalConfig
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(
        public string $baseUrl,
        public string $signKey = '',
        public string $salt = '',
        public array $options = [],
    ) {}

    public function option(string $name, string $default = ''): string
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Option the provider cannot work without — throws at processor-selection time, so a
     * misconfiguration fails loudly instead of emitting broken URLs.
     */
    public function requireOption(string $name): string
    {
        if (!isset($this->options[$name]) || $this->options[$name] === '') {
            throw new \RuntimeException(
                sprintf(
                    'imaginator: required processor option "%s" is not configured.'
                    . " Set it in \$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator']['processorOptions']['%s'].",
                    $name,
                    $name,
                ),
                1752400006,
            );
        }

        return $this->options[$name];
    }
}
