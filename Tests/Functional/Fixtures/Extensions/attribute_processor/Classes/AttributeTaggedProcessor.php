<?php

declare(strict_types=1);

namespace Schliesser\ImaginatorFixture\AttributeProcessor;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;

/**
 * Full-processor registration shape: implements the processor interface, carries only the
 * attribute — no Services.yaml tag anywhere in this fixture extension.
 */
#[AsImaginatorProcessor('fixture:attribute')]
final class AttributeTaggedProcessor implements ImageProcessorInterface
{
    public function buildUrl(ImageVariant $variant): string
    {
        return sprintf('/fixture-attribute/%dx%d.%s', $variant->width, $variant->height, $variant->format);
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        throw new \LogicException('fixture processor never materializes', 1752400100);
    }
}
