<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\UrlBuilder\ImagorUrlBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Builds the imagor-backed {@see ExternalImageProcessor} from the unified provider settings.
 * `processorSignKey` carries imagor's IMAGOR_SECRET (plain string); `processorSalt` has no imagor
 * equivalent and is not passed. Lives as its own DI factory so the processor is only constructed
 * when `imaginator.processor = imagor` is actually selected.
 */
final readonly class ImagorProcessorFactory
{
    public function __construct(
        private SettingsFactory $settingsFactory,
        private ResourceFactory $resourceFactory,
        private CropResolver $cropResolver,
        private CropCalculator $cropCalculator,
    ) {}

    public function create(): ExternalImageProcessor
    {
        $settings = $this->settingsFactory->create();

        return new ExternalImageProcessor(
            new ImagorUrlBuilder(new ExternalConfig(
                $settings->processorBaseUrl,
                $settings->processorSignKey,
            )),
            $this->resourceFactory,
            $this->cropResolver,
            $this->cropCalculator,
            $settings->processorSourceBaseUrl,
        );
    }
}
