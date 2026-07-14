<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Generic factory behind attribute-registered URL builders: builds the {@see ExternalImageProcessor}
 * for any {@see UrlBuilderInterface} from the unified provider settings. Convention (documented on
 * the interface): the builder takes {@see ExternalConfig} as its sole constructor argument and
 * ignores the config fields its provider has no equivalent for (e.g. imagor ignores `salt`).
 * Lives as a DI factory so a processor is only constructed when actually selected.
 */
final readonly class ExternalProcessorFactory
{
    public function __construct(
        private SettingsFactory $settingsFactory,
        private ResourceFactory $resourceFactory,
        private CropResolver $cropResolver,
        private CropCalculator $cropCalculator,
    ) {}

    public function create(string $builderClass): ExternalImageProcessor
    {
        if (!is_a($builderClass, UrlBuilderInterface::class, true)) {
            throw new \LogicException(
                sprintf(
                    'imaginator: "%s" cannot back an external processor — it does not implement %s.',
                    $builderClass,
                    UrlBuilderInterface::class,
                ),
                1752400005,
            );
        }
        $settings = $this->settingsFactory->create();

        return new ExternalImageProcessor(
            new $builderClass(new ExternalConfig(
                $settings->processorBaseUrl,
                $settings->processorSignKey,
                $settings->processorSalt,
                $settings->processorOptions,
            )),
            $this->resourceFactory,
            $this->cropResolver,
            $this->cropCalculator,
            $settings->processorSourceBaseUrl,
        );
    }
}
