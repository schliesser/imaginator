<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Psr\Container\ContainerInterface;

/**
 * Open registry of {@see ImageProcessorInterface} implementations, keyed by their `processor`
 * setting identifier. Built-ins (`local:async`, `local:sync`, `imgproxy`) are tagged in
 * Services.yaml; an integrator registers a custom processor purely by tagging their service:
 *
 *   My\Ext\MyProcessor:
 *     tags:
 *       - { name: imaginator.image_processor, key: 'my-cdn' }
 *
 * and selecting it via `imaginator.processor = my-cdn` — no core change required. Backed by a
 * lazy service locator, so only the selected processor is ever instantiated.
 */
final readonly class ImageProcessorRegistry
{
    public function __construct(private ContainerInterface $processors) {}

    public function get(string $identifier): ImageProcessorInterface
    {
        if (!$this->processors->has($identifier)) {
            throw new \InvalidArgumentException(
                sprintf('imaginator: unknown processor "%s".', $identifier),
                1718200002,
            );
        }

        return $this->processors->get($identifier);
    }
}
