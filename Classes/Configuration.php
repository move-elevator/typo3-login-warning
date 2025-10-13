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

namespace MoveElevator\Typo3LoginWarning;

/**
 * Configuration.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class Configuration
{
    final public const EXT_KEY = 'typo3_login_warning';
    final public const EXT_NAME = 'Typo3LoginWarning';

    public static function initExtLocalConfig(): void
    {
        self::registerMailTemplate();
    }

    private static function registerMailTemplate(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][500] = 'EXT:'.self::EXT_KEY.'/Resources/Private/Templates/Email';
    }
}
