<?php

declare(strict_types=1);

namespace Schliesser\Imaginator\Backend\Form;

use Schliesser\Imaginator\Backend\Form\Element\AspectRatiosElement;

/**
 * Registers the {@see AspectRatiosElement} for the `imaginatorAspectRatios` renderType. Both
 * TYPO3 v13 and v14 still resolve custom FormEngine nodes via the
 * `$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry']` array; this adapter isolates
 * that surface so a future registry change only touches one place.
 */
final class AspectRatiosElementRegistration
{
    public const NODE_NAME = 'imaginatorAspectRatios';

    public static function register(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1717600100] = [
            'nodeName' => self::NODE_NAME,
            'priority' => 40,
            'class' => AspectRatiosElement::class,
        ];
    }
}
