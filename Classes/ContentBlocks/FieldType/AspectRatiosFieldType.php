<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\ContentBlocks\FieldType;

use Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement;
use TYPO3\CMS\ContentBlocks\FieldType\AbstractFieldType;
use TYPO3\CMS\ContentBlocks\FieldType\FieldType;
use TYPO3\CMS\ContentBlocks\FieldType\WithCommonProperties;

/**
 * Exposes the per-breakpoint aspect-ratios field as a Content Blocks field type.
 *
 * Example usage:
 *   - identifier: aspect_ratio
 *     type: AspectRatio
 *     allowedRatios: '16:9,21:9'   # optional; comma-separated, defaults to the full set
 *
 * Content Blocks is an OPTIONAL integration: this class extends a `TYPO3\CMS\ContentBlocks\…` base, so
 * it must never be autoloaded when EXT:content_blocks is absent (it would fatal at class load).
 */
#[FieldType(name: 'AspectRatio', tcaType: 'user')]
final class AspectRatiosFieldType extends AbstractFieldType
{
    use WithCommonProperties;

    /**
     * Comma-separated ratios offered in the editor. Mirrors the default `allowedRatios` of the
     * standalone TCA registration; narrow it per block via the YAML `allowedRatios` setting.
     */
    private string $allowedRatios = '1:1,4:3,3:2,16:9,21:9,9:16,2:3,3:4';

    /**
     * @param array<string, mixed> $settings
     */
    public function createFromArray(array $settings): self
    {
        $self = clone $this;
        $self->setCommonProperties($settings);
        $self->allowedRatios = (string) ($settings['allowedRatios'] ?? $self->allowedRatios);

        return $self;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTca(): array
    {
        $tca = $this->toTca();
        $config = [
            'type' => $this->getTcaType(),
            'renderType' => AspectRatiosElement::NODE_NAME,
            'allowedRatios' => $this->allowedRatios,
        ];
        $tca['config'] = array_replace($tca['config'] ?? [], $config);

        return $tca;
    }

    public function getSql(string $column): string
    {
        // The element persists its breakpoint→ratio map as a JSON string; `type: user` has no SQL the
        // schema analyzer can infer, so declare the column explicitly (matches `ext_tables.sql`).
        return sprintf('`%s` text', $column);
    }
}
