<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Imaging;

use Psr\Container\ContainerInterface;

/**
 * Open registry of {@see ImageProcessorInterface} implementations, keyed by their `processor`
 * setting identifier. Built-ins and integrator processors alike register via
 * `#[AsImaginatorProcessor('my-cdn')]` on the class — the implemented interface picks the shape
 * ({@see \Schliesser\Imaginator\Attribute\AsImaginatorProcessor}); a manual Services.yaml tag
 * remains a supported alternative:
 *
 *   My\Ext\MyProcessor:
 *     tags:
 *       - { name: imaginator.image_processor, key: 'my-cdn' }
 *
 * Select it via `imaginator.processor = my-cdn` — no core change required. Backed by a
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
