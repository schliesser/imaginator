<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Backend\Form\Element;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Dto\Breakpoint;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Custom FormEngine element for per-breakpoint aspect ratios at the content-element level. Renders a
 * web-component host (`<imaginator-aspect-ratios>`) carrying the design-system breakpoints (from
 * Extension Configuration), the field's allowed ratios (from TCA) and the current `{bp: ratio}`
 * JSON value, plus a hidden input the web component serializes into. The TS component (registered as
 * a backend ES module) renders one row per breakpoint with an allowed-ratio chooser and a swatch.
 */
final class AspectRatiosElement extends AbstractFormElement
{
    public const NODE_NAME = 'imaginatorAspectRatios';
    private const MODULE = '@schliesser/imaginator/backend/aspect-ratios.js';

    public function render(): array
    {
        $result = $this->initializeResultArray();

        $parameterArray = $this->data['parameterArray'];
        $name = (string) $parameterArray['itemFormElName'];
        $value = (string) ($parameterArray['itemFormElValue'] ?? '');
        $allowed = (string) ($parameterArray['fieldConf']['config']['allowedRatios'] ?? '');

        // FormEngine instantiates nodes via plain makeInstance (no DI), so build the factory from
        // its single dependency, which is itself makeInstance-able.
        $settingsFactory = new SettingsFactory(GeneralUtility::makeInstance(ExtensionConfiguration::class));
        $breakpoints = $settingsFactory->create()->breakpoints;
        $breakpointsJson = json_encode(array_map(
            static fn(Breakpoint $b): array => ['key' => $b->key, 'minWidth' => $b->minWidth],
            $breakpoints,
        ), JSON_THROW_ON_ERROR);

        $fieldId = 'imaginator-aspect-ratios-' . md5($name);
        $enc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);

        $component = sprintf(
            '<imaginator-aspect-ratios data-breakpoints="%s" data-allowed="%s" data-value="%s" data-field="%s">'
            . '<input type="hidden" id="%s" name="%s" value="%s"/>'
            . '</imaginator-aspect-ratios>',
            $enc($breakpointsJson),
            $enc($allowed),
            $enc($value),
            $enc($fieldId),
            $enc($fieldId),
            $enc($name),
            $enc($value),
        );

        // FormEngine does not add a label for `type=user` nodes; the element renders its own. The
        // component is multi-row (one per breakpoint) with no single labelable input, so use a
        // fieldset/legend like core's non-input elements rather than a `<label for>`.
        $result['html'] = $this->wrapWithFieldsetAndLegend($component);

        $result['javaScriptModules'][] = JavaScriptModuleInstruction::create(self::MODULE);
        $result['stylesheetFiles'][] = 'EXT:imaginator/Resources/Public/Css/backend/aspect-ratios.css';

        return $result;
    }
}
