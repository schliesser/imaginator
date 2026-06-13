<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\DataProcessing;

use Schliesser\Imaginator\Configuration\SettingsFactory;
use Schliesser\Imaginator\Service\RatioMapResolver;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Resolves the content element's `aspect_ratio` JSON into the
 * {@see \Schliesser\Imaginator\Dto\BreakpointRatio[]} the `<i:image breakpoints="...">` argument
 * consumes, so a single per-CE setting drives the ratio of every image the element renders.
 *
 * Configuration:
 * - `fieldName` (default `aspect_ratio`) — source column on the record.
 * - `as` (default `imaginatorAspectRatios`) — target variable for the Fluid template.
 */
final readonly class AspectRatioProcessor implements DataProcessorInterface
{
    public function __construct(
        private RatioMapResolver $ratioMapResolver,
        private SettingsFactory $settingsFactory,
    ) {}

    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $fieldName = (string)($processorConfiguration['fieldName'] ?? 'aspect_ratio');
        $target = (string)($processorConfiguration['as'] ?? 'imaginatorAspectRatios');
        $json = (string)($processedData['data'][$fieldName] ?? '');

        $processedData[$target] = $this->ratioMapResolver->fromJson(
            $json,
            $this->settingsFactory->create()->breakpoints,
        );

        return $processedData;
    }
}
