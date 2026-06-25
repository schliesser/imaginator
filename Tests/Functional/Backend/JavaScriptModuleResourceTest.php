<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Backend;

use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Guards the backend ES module path the {@see \Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement}
 * registers: TYPO3 v14 only renders an importmap entry whose target resolves to a *published* public
 * resource, otherwise it throws CanNotResolvePublicResourceException when the FormEngine renders the
 * element. This asserts the extension's `Resources/Public` is a published resource and the concrete
 * web-component file resolves.
 */
final class JavaScriptModuleResourceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    public function testBackendModuleFileResolvesAsPublishedPublicResource(): void
    {
        if (!class_exists(SystemResourceFactory::class)) {
            self::markTestSkipped('SystemResourceFactory + published-resource resolution is TYPO3 v14+ only.');
        }

        $factory = $this->get(SystemResourceFactory::class);

        $resource = $factory->createPublicResource(
            'EXT:imaginator/Resources/Public/JavaScript/backend/aspect-ratios.js',
        );

        self::assertTrue($resource->isPublished());
    }
}
