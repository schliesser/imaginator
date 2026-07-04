<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging\External;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Imaging\CropCalculator;
use Schliesser\Imaginator\Imaging\CropResolver;
use Schliesser\Imaginator\UrlBuilder\ImgproxyUrlBuilder;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Builds the imgproxy-backed {@see ExternalImageProcessor} from the scalar provider settings
 * (base URL, sign key, salt, source base URL). Lives as its own DI factory so the processor is
 * only constructed when `imaginator.processor = imgproxy` is actually selected.
 */
final readonly class ImgproxyProcessorFactory
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
            new ImgproxyUrlBuilder(new ExternalConfig(
                $settings->processorBaseUrl,
                $settings->processorSignKey,
                $settings->processorSalt,
            )),
            $this->resourceFactory,
            $this->cropResolver,
            $this->cropCalculator,
            $settings->processorSourceBaseUrl,
        );
    }
}
