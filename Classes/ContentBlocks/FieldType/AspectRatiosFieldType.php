<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\ContentBlocks\FieldType;

use Schliesser\Imaginator\Backend\Form\AspectRatiosElementRegistration;
use TYPO3\CMS\ContentBlocks\FieldType\AbstractFieldType;
use TYPO3\CMS\ContentBlocks\FieldType\FieldType;
use TYPO3\CMS\ContentBlocks\FieldType\WithCommonProperties;

/**
 * Exposes the per-breakpoint aspect-ratios field as a Content Blocks field type, so block authors can
 * write `type: AspectRatios` in their YAML instead of hand-rolling the `type: user` TCA. The emitted
 * TCA mirrors the standalone registration in `Configuration/TCA/Overrides/tt_content.php`: a custom
 * FormEngine node ({@see AspectRatiosElement}) editing a `{"<breakpoint>": "<ratio>"}` JSON map.
 *
 * Content Blocks is an OPTIONAL integration: this class extends a `TYPO3\CMS\ContentBlocks\…` base, so
 * it must never be autoloaded when EXT:content_blocks is absent (it would fatal at class load).
 *
 * YAML usage:
 *   - identifier: aspect_ratios
 *     type: AspectRatios
 *     allowedRatios: '16:9,21:9'   # optional; comma-separated, defaults to the full set
 */
#[FieldType(name: 'ImaginatorAspectRatios', tcaType: 'user', searchable: false)]
final class AspectRatiosFieldType extends AbstractFieldType
{
    use WithCommonProperties;

    /**
     * Comma-separated ratios offered in the editor. Mirrors the default `allowedRatios` of the
     * standalone TCA registration; narrow it per block via the YAML `allowedRatios` setting.
     */
    private string $allowedRatios = '1:1,4:3,3:2,16:9,21:9,9:16,2:3,3:4';

    public function createFromArray(array $settings): self
    {
        $self = clone $this;
        $self->setCommonProperties($settings);
        $self->allowedRatios = (string)($settings['allowedRatios'] ?? $self->allowedRatios);

        return $self;
    }

    public function getTca(): array
    {
        $tca = $this->toTca();
        // renderType is forced to the element's node name (single source of truth) so a stray
        // `renderType:` in the block YAML cannot detach the field from its editor.
        $config = [
            'type' => $this->getTcaType(),
            'renderType' => AspectRatiosElementRegistration::NODE_NAME,
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
