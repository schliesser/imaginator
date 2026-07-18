<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\DependencyInjection;

use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\Imaging\External\ExternalProcessorFactory;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Materializes {@see AsImaginatorProcessor} attributes into `imaginator.image_processor` tags, so
 * attributed classes land in the same tagged locator as manually tagged services. The implemented
 * interface picks the shape: an {@see ImageProcessorInterface} class is tagged directly; an
 * {@see UrlBuilderInterface} class gets a synthetic `imaginator.processor.{key}` definition — an
 * {@see ExternalImageProcessor} built by {@see ExternalProcessorFactory} around the builder. The
 * builder is passed as a class-name string (not a reference), so its own unreferenced definition is
 * dropped by the container and its scalar `ExternalConfig` constructor never has to autowire.
 *
 * Does its own reflection instead of `registerAttributeForAutoconfiguration` because the URL-builder
 * shape synthesizes a *new* definition (autoconfiguration can only modify the attributed service
 * itself) and duplicate-key validation needs a full-container view — the tagged locator's `index_by`
 * would otherwise silently last-win. Attributed classes register through their own extension's
 * standard `Services.yaml` discovery. Note TYPO3's container cache ignores Symfony resource
 * tracking: adding/removing the attribute needs a cache flush, same as editing a yaml tag.
 */
final class ProcessorRegistrationPass implements CompilerPassInterface
{
    private const TAG = 'imaginator.image_processor';

    public function process(ContainerBuilder $container): void
    {
        // Seed with manual yaml/instanceof tags so attribute-vs-manual collisions are caught too.
        $seenKeys = [];
        foreach ($container->findTaggedServiceIds(self::TAG) as $serviceId => $tags) {
            foreach ($tags as $tagAttributes) {
                $key = (string) ($tagAttributes['key'] ?? '');
                if ($key !== '') {
                    $seenKeys[$key] = $serviceId;
                }
            }
        }

        foreach ($container->getDefinitions() as $serviceId => $definition) {
            $class = $definition->getClass();
            if ($definition->isAbstract() || $definition->isSynthetic() || $class === null || $class === '') {
                continue;
            }
            $reflection = $container->getReflectionClass($class, false);
            if ($reflection === null) {
                continue;
            }
            $attributes = $reflection->getAttributes(AsImaginatorProcessor::class);
            if ($attributes === []) {
                continue;
            }

            $key = $attributes[0]->newInstance()->key;
            $this->register($container, $definition, $reflection, $serviceId, $key, $seenKeys);
            $seenKeys[$key] = $serviceId;
        }
    }

    /**
     * @param \ReflectionClass<object>       $reflection
     * @param array<string, string>          $seenKeys
     */
    private function register(
        ContainerBuilder $container,
        Definition $definition,
        \ReflectionClass $reflection,
        string $serviceId,
        string $key,
        array $seenKeys,
    ): void {
        if ($key === '') {
            throw new \LogicException(
                sprintf('imaginator: #[AsImaginatorProcessor] on "%s" has an empty key.', $reflection->getName()),
                1752400001,
            );
        }
        if (isset($seenKeys[$key])) {
            throw new \LogicException(
                sprintf(
                    'imaginator: processor key "%s" of "%s" is already registered by service "%s".',
                    $key,
                    $reflection->getName(),
                    $seenKeys[$key],
                ),
                1752400002,
            );
        }

        $isProcessor = $reflection->implementsInterface(ImageProcessorInterface::class);
        $isBuilder = $reflection->implementsInterface(UrlBuilderInterface::class);
        if ($isProcessor && $isBuilder) {
            throw new \LogicException(
                sprintf(
                    'imaginator: "%s" implements both ImageProcessorInterface and UrlBuilderInterface —'
                    . ' ambiguous registration shape for #[AsImaginatorProcessor]. Split the class.',
                    $reflection->getName(),
                ),
                1752400003,
            );
        }
        if (!$isProcessor && !$isBuilder) {
            throw new \LogicException(
                sprintf(
                    'imaginator: "%s" carries #[AsImaginatorProcessor] but implements neither %s (full'
                    . ' processor) nor %s (URL grammar for an external provider). Implement one of the two.',
                    $reflection->getName(),
                    ImageProcessorInterface::class,
                    UrlBuilderInterface::class,
                ),
                1752400004,
            );
        }

        if ($isProcessor) {
            $definition->addTag(self::TAG, ['key' => $key]);

            return;
        }

        $external = new Definition(ExternalImageProcessor::class);
        $external->setFactory([new Reference(ExternalProcessorFactory::class), 'create']);
        $external->setArguments([$reflection->getName()]);
        $external->addTag(self::TAG, ['key' => $key]);
        $container->setDefinition('imaginator.processor.' . $key, $external);
    }
}
