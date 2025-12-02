<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_login_warning" TYPO3 CMS extension.
 *
 * (c) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use KonradMichalik\PhpCsFixerPreset\Config;
use KonradMichalik\PhpCsFixerPreset\Package\{Author, CopyrightRange, Type};
use KonradMichalik\PhpCsFixerPreset\Rules\Header;
use KonradMichalik\PhpCsFixerPreset\Rules\Set\Set;
use KonradMichalik\PhpDocBlockHeaderFixer\Generators\DocBlockHeader;
use KonradMichalik\PhpDocBlockHeaderFixer\Rules\DocBlockHeaderFixer;
use Symfony\Component\Finder\Finder;

$rootPath = dirname(__DIR__, 2);

return Config::create()
    ->registerCustomFixers([
        new DocBlockHeaderFixer(),
    ])
    ->withRule(
        Header::create(
            'typo3_login_warning',
            Type::TYPO3Extension,
            Author::create('Konrad Michalik', 'km@move-elevator.de'),
            CopyrightRange::from(2025),
        ),
    )
    ->withRule(
        Set::fromArray(
            DocBlockHeader::fromComposer()->__toArray(),
        ),
    )
    ->withFinder(
        static fn (Finder $finder) => $finder
            ->in($rootPath)
            ->notPath(['ext_emconf.php']),
    )
;
