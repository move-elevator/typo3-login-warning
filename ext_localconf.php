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

use MoveElevator\Typo3LoginWarning\Configuration;

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][500] = 'EXT:'.Configuration::EXT_KEY.'/Resources/Private/Templates/Email';
