<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\ExternalConfig;
use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\Imaging\External\UrlBuilder\ImgproxyUrlBuilder;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Selects the active {@see ImageProcessorInterface} from the `processor` setting and is wired as the
 * DI factory for that interface, so the render path and middleware stay processor-agnostic. `local`
 * runs GraphicsMagick behind the signed endpoint; `imgproxy` offloads pixels to an imgproxy service.
 */
final readonly class ImageProcessorFactory
{
    public function __construct(
        private LocalImageProcessor $local,
        private SettingsFactory $settingsFactory,
        private ResourceFactory $resourceFactory,
    ) {}

    public function create(): ImageProcessorInterface
    {
        $settings = $this->settingsFactory->create();

        return match ($settings->processor) {
            'local' => $this->local,
            'imgproxy' => new ExternalImageProcessor(
                new ImgproxyUrlBuilder(new ExternalConfig(
                    $settings->processorBaseUrl,
                    $settings->processorSignKey,
                    $settings->processorSalt,
                )),
                $this->resourceFactory,
                $settings->processorSourceBaseUrl,
            ),
            default => throw new \InvalidArgumentException(
                sprintf('imaginator: unknown processor "%s".', $settings->processor),
                1718200002,
            ),
        };
    }
}
