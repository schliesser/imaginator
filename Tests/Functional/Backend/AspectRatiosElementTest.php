<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Tests\Functional\Backend;

use Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class AspectRatiosElementTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['schliesser/imaginator'];

    protected function setUp(): void
    {
        parent::setUp();
        // renderLabel()/wrapWithFieldsetAndLegend() query the BE user for debug display.
        $beUser = $this->createMock(BackendUserAuthentication::class);
        $beUser->method('shallDisplayDebugInformation')->willReturn(false);
        $GLOBALS['BE_USER'] = $beUser;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderResult(): array
    {
        $element = GeneralUtility::makeInstance(AspectRatiosElement::class);
        $element->setData([
            'fieldName' => 'aspect_ratio',
            'parameterArray' => [
                'itemFormElName' => 'data[tt_content][1][aspect_ratio]',
                'itemFormElValue' => '{"xs":"1:1","lg":"16:9"}',
                'fieldConf' => [
                    'label' => 'Imaginator aspect ratios',
                    'config' => ['allowedRatios' => '1:1,16:9'],
                ],
            ],
        ]);

        return $element->render();
    }

    public function testRendersFieldLabel(): void
    {
        $html = $this->renderResult()['html'];

        self::assertStringContainsString('t3js-formengine-label', $html);
        self::assertStringContainsString('Imaginator aspect ratios', $html);
    }

    public function testRendersWebComponentHostWithDataAttributes(): void
    {
        $html = $this->renderResult()['html'];

        self::assertStringContainsString('<imaginator-aspect-ratios', $html);
        self::assertStringContainsString('data-allowed="1:1,16:9"', $html);
        // ext-config breakpoints serialized into data-breakpoints
        self::assertMatchesRegularExpression('/data-breakpoints="[^"]*lg[^"]*992/', $html);
        // current value (JSON, html-encoded)
        self::assertStringContainsString('data-value="', $html);
        self::assertStringContainsString('16:9', $html);
    }

    public function testRendersHiddenInputNamedForTheField(): void
    {
        $html = $this->renderResult()['html'];

        self::assertMatchesRegularExpression(
            '/<input[^>]*type="hidden"[^>]*name="data\[tt_content\]\[1\]\[aspect_ratio\]"/',
            $html,
        );
    }

    public function testRegistersBackendJavaScriptModule(): void
    {
        $modules = $this->renderResult()['javaScriptModules'];

        self::assertNotEmpty($modules);
        $names = array_map(static fn($m): string => $m->getName(), $modules);
        self::assertNotEmpty(array_filter($names, static fn(string $n): bool => str_contains($n, 'aspect-ratios')));
    }
}
