<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Schliesser\Imaginator\Configuration\SettingsFactory;

/**
 * Resolves the active {@see ImageProcessorInterface} from the `processor` setting via the
 * {@see ImageProcessorRegistry}, and is wired as the DI factory for that interface, so the render
 * path and middleware stay processor-agnostic. `local:async` runs ImageService behind the signed
 * endpoint (middleware + 302), `local:sync` writes processed-file URLs straight into srcset, and
 * `imgproxy`/`imagor` offload pixels to an external service. Integrators add their own with
 * `#[AsImaginatorProcessor]` or a manual tag (see {@see ImageProcessorRegistry}).
 */
final readonly class ImageProcessorFactory
{
    public function __construct(
        private ImageProcessorRegistry $registry,
        private SettingsFactory $settingsFactory,
    ) {}

    public function create(): ImageProcessorInterface
    {
        return $this->registry->get($this->settingsFactory->create()->processor);
    }
}
