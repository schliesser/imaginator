<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Imaging;

use Schliesser\Imaginator\Imaging\External\ExternalImageProcessor;
use Schliesser\Imaginator\Imaging\ImageProcessorFactory;
use Schliesser\Imaginator\Imaging\Local\LocalImageProcessor;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ImageProcessorFactoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    /**
     * @param array<string, mixed> $config
     */
    private function createWith(array $config): object
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['imaginator'] = $config;

        return $this->get(ImageProcessorFactory::class)->create();
    }

    public function testLocalProcessorIsTheDefault(): void
    {
        self::assertInstanceOf(LocalImageProcessor::class, $this->createWith(['processor' => 'local']));
    }

    public function testImgproxySelectsTheOffloadedProcessor(): void
    {
        $processor = $this->createWith([
            'processor' => 'imgproxy',
            'processorBaseUrl' => 'https://imgproxy.example:8081',
        ]);

        self::assertInstanceOf(ExternalImageProcessor::class, $processor);
        self::assertTrue($processor->isOffloaded());
    }

    public function testUnknownProcessorThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionCode(1718200002);
        $this->createWith(['processor' => 'nope']);
    }
}
