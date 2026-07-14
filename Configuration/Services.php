<?php

declare(strict_types=1);

namespace Schliesser\Imaginator;

use Schliesser\Imaginator\DependencyInjection\ProcessorRegistrationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

// Loaded by TYPO3 alongside Services.yaml. The pass materializes #[AsImaginatorProcessor]
// attributes (this extension's built-ins and any other extension's classes) into
// `imaginator.image_processor` tags before the registry's tagged locator resolves.
return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $containerBuilder->addCompilerPass(new ProcessorRegistrationPass());
};
