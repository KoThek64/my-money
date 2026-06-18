<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/src',
    ])
    ->withPhpSets(php85: true)
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ])
    // importShortClasses: false → ne pas importer les classes du namespace global
    // (DateTimeImmutable, etc.). On les laisse pleinement qualifiées (\DateTimeImmutable),
    // pour rester cohérent avec la convention @Symfony de php-cs-fixer (sinon les deux
    // outils se contredisent en boucle sur les `use`).
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
