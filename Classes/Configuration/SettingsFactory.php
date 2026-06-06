<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Configuration;

use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Url\SignedUrlBuilder;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Sources {@see Settings} for the DI container and builds the services whose constructors take
 * scalar configuration (signing secrets, ladder rungs) — these cannot be autowired directly.
 *
 * Configuration is instance-wide Extension Configuration (`ext_conf_template.txt`), so the same
 * values apply on every request without site/request context — which keeps the render path and the
 * signed-endpoint verify path trivially in sync. The signing secret stays derived from the global
 * encryptionKey.
 */
final class SettingsFactory
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function create(): Settings
    {
        $encryptionKey = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');

        return Settings::fromArray($this->rawConfiguration(), $encryptionKey);
    }

    public function createSignedUrlBuilder(): SignedUrlBuilder
    {
        return new SignedUrlBuilder($this->create()->secrets);
    }

    public function createLadderFactory(): LadderFactory
    {
        $settings = $this->create();

        return new LadderFactory($settings->ladder, $settings->maxDimension);
    }

    /**
     * @return array<string, mixed> shape expected by {@see Settings::fromArray()}
     */
    private function rawConfiguration(): array
    {
        try {
            $configuration = $this->extensionConfiguration->get('imaginator');
        } catch (\Throwable) {
            // Not configured yet (e.g. ext_conf_template not synced) -> use defaults.
            return [];
        }
        if (!is_array($configuration)) {
            return [];
        }
        // ext_conf_template nests `quality.avif`/`quality.webp`; Settings expects a `qualities` map.
        if (isset($configuration['quality']) && is_array($configuration['quality'])) {
            $configuration['qualities'] = $configuration['quality'];
        }

        return $configuration;
    }
}
