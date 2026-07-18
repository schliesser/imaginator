<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Schliesser\Imaginator\Attribute\AsImaginatorProcessor;
use Schliesser\Imaginator\DependencyInjection\ProcessorRegistrationPass;
use Schliesser\Imaginator\Dto\ImageVariant;
use Schliesser\Imaginator\Dto\ProcessedImage;
use Schliesser\Imaginator\Dto\Rectangle;
use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\Imaging\External\ExternalProcessorFactory;
use Schliesser\Imaginator\Imaging\ImageProcessorInterface;
use Schliesser\Imaginator\UrlBuilder\UrlBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class ProcessorRegistrationPassTest extends TestCase
{
    private function compile(ContainerBuilder $container): void
    {
        (new ProcessorRegistrationPass())->process($container);
    }

    private function containerWith(string ...$classes): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($classes as $class) {
            $container->setDefinition($class, new Definition($class));
        }

        return $container;
    }

    public function testProcessorInterfaceClassGetsTaggedDirectly(): void
    {
        $container = $this->containerWith(FixtureProcessor::class);
        $this->compile($container);

        self::assertSame(
            [['key' => 'fixture-processor']],
            $container->getDefinition(FixtureProcessor::class)->getTag('imaginator.image_processor'),
        );
    }

    public function testUrlBuilderClassGetsSynthesizedExternalProcessorDefinition(): void
    {
        $container = $this->containerWith(FixtureUrlBuilder::class);
        $this->compile($container);

        self::assertTrue($container->hasDefinition('imaginator.processor.fixture-cdn'));
        $definition = $container->getDefinition('imaginator.processor.fixture-cdn');
        self::assertSame(ExternalImageProcessor::class, $definition->getClass());
        $factory = $definition->getFactory();
        self::assertIsArray($factory);
        self::assertSame(ExternalProcessorFactory::class, (string) $factory[0]);
        self::assertSame('create', $factory[1]);
        self::assertSame([FixtureUrlBuilder::class, ''], $definition->getArguments());
        self::assertSame(
            [['key' => 'fixture-cdn']],
            $definition->getTag('imaginator.image_processor'),
        );
    }

    public function testUrlBuilderExtensionKeyIsPassedToTheFactory(): void
    {
        $container = $this->containerWith(FixtureNamespacedUrlBuilder::class);
        $this->compile($container);

        self::assertSame(
            [FixtureNamespacedUrlBuilder::class, 'fixture_cdn'],
            $container->getDefinition('imaginator.processor.fixture-namespaced')->getArguments(),
        );
    }

    public function testExtensionKeyOnProcessorInterfaceClassThrows(): void
    {
        $container = $this->containerWith(FixtureProcessorWithExtensionKey::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/extensionKey/');
        $this->compile($container);
    }

    public function testUrlBuilderDefinitionItselfIsNotTagged(): void
    {
        $container = $this->containerWith(FixtureUrlBuilder::class);
        $this->compile($container);

        self::assertSame([], $container->getDefinition(FixtureUrlBuilder::class)->getTag('imaginator.image_processor'));
    }

    public function testClassImplementingNeitherInterfaceThrows(): void
    {
        $container = $this->containerWith(FixtureNeitherInterface::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/ImageProcessorInterface/');
        $this->expectExceptionMessageMatches('/UrlBuilderInterface/');
        $this->compile($container);
    }

    public function testClassImplementingBothInterfacesThrows(): void
    {
        $container = $this->containerWith(FixtureBothInterfaces::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/ambiguous/i');
        $this->compile($container);
    }

    public function testDuplicateKeyAcrossRegistrationShapesThrows(): void
    {
        $container = $this->containerWith(FixtureProcessor::class, FixtureDuplicateKeyUrlBuilder::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/fixture-processor/');
        $this->compile($container);
    }

    public function testAttributeKeyCollidingWithManualTagThrows(): void
    {
        $container = $this->containerWith(FixtureProcessor::class);
        $manual = new Definition(FixtureNoAttribute::class);
        $manual->addTag('imaginator.image_processor', ['key' => 'fixture-processor']);
        $container->setDefinition('manual.processor', $manual);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/fixture-processor/');
        $this->compile($container);
    }

    public function testEmptyKeyThrows(): void
    {
        $container = $this->containerWith(FixtureEmptyKey::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/empty/i');
        $this->compile($container);
    }

    public function testAbstractSyntheticAndClasslessDefinitionsAreSkipped(): void
    {
        $container = new ContainerBuilder();
        $abstract = new Definition(FixtureProcessor::class);
        $abstract->setAbstract(true);
        $container->setDefinition('abstract.definition', $abstract);
        $synthetic = new Definition(FixtureProcessor::class);
        $synthetic->setSynthetic(true);
        $container->setDefinition('synthetic.definition', $synthetic);
        $container->setDefinition('classless.definition', new Definition());

        $this->compile($container);

        self::assertSame([], $abstract->getTag('imaginator.image_processor'));
        self::assertSame([], $synthetic->getTag('imaginator.image_processor'));
    }

    public function testUnattributedClassesAreIgnored(): void
    {
        $container = $this->containerWith(FixtureNoAttribute::class);
        $this->compile($container);

        self::assertSame([], $container->getDefinition(FixtureNoAttribute::class)->getTag('imaginator.image_processor'));
        self::assertSame([], $container->findTaggedServiceIds('imaginator.image_processor'));
    }
}

#[AsImaginatorProcessor('fixture-processor')]
final class FixtureProcessor implements ImageProcessorInterface
{
    public function buildUrl(ImageVariant $variant): string
    {
        return '/fixture/' . $variant->width;
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        throw new \LogicException('fixture only');
    }
}

#[AsImaginatorProcessor('fixture-cdn')]
final class FixtureUrlBuilder implements UrlBuilderInterface
{
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return 'https://cdn.example/' . $sourceUrl;
    }
}

#[AsImaginatorProcessor('fixture-namespaced', extensionKey: 'fixture_cdn')]
final class FixtureNamespacedUrlBuilder implements UrlBuilderInterface
{
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return '';
    }
}

#[AsImaginatorProcessor('fixture-misplaced', extensionKey: 'fixture_cdn')]
final class FixtureProcessorWithExtensionKey implements ImageProcessorInterface
{
    public function buildUrl(ImageVariant $variant): string
    {
        return '';
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        throw new \LogicException('fixture only');
    }
}

#[AsImaginatorProcessor('fixture-duplicate')]
final class FixtureNeitherInterface {}

#[AsImaginatorProcessor('fixture-both')]
final class FixtureBothInterfaces implements ImageProcessorInterface, UrlBuilderInterface
{
    public function buildUrl(ImageVariant $variant): string
    {
        return '';
    }

    public function isOffloaded(): bool
    {
        return false;
    }

    public function materialize(ImageVariant $variant): ProcessedImage
    {
        throw new \LogicException('fixture only');
    }

    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return '';
    }
}

#[AsImaginatorProcessor('fixture-processor')]
final class FixtureDuplicateKeyUrlBuilder implements UrlBuilderInterface
{
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return '';
    }
}

#[AsImaginatorProcessor('')]
final class FixtureEmptyKey implements UrlBuilderInterface
{
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return '';
    }
}

final class FixtureNoAttribute implements UrlBuilderInterface
{
    public function build(ImageVariant $variant, string $sourceUrl, ?Rectangle $crop = null): string
    {
        return '';
    }
}
