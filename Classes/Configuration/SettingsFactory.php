<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Configuration;

use Schliesser\Imaginator\Ladder\LadderFactory;
use Schliesser\Imaginator\Url\SignedUrlBuilder;

/**
 * Sources {@see Settings} for the DI container and builds the services whose constructors take
 * scalar configuration (signing secrets, ladder rungs) — these cannot be autowired directly.
 *
 * v1 reads the global encryptionKey for the signing secret and uses default ladder/format values.
 * Per-site Site-Set overrides (imaginator.*) are a follow-on plan; the parser already accepts them.
 */
final class SettingsFactory
{
    public function create(): Settings
    {
        $encryptionKey = (string)($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '');

        return Settings::fromArray([], $encryptionKey);
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
}
