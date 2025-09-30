<?php

/*
 * This file is part of the TYPO3 CMS extension "typo3_login_warning".
 *
 * Copyright (C) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace MoveElevator\Typo3LoginWarning\Configuration;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;

/**
 * LoginWarning.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class LoginWarning
{
    public static function newIp(array $configuration = []): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['register'][] = [
            'detector' => NewIpDetector::class,
            'configuration' => $configuration,
        ];
    }

    public static function longTimeNoSee(array $configuration = []): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['register'][] = [
            'detector' => LongTimeNoSeeDetector::class,
            'configuration' => $configuration,
        ];
    }

    public static function outOfOffice(array $configuration = []): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['register'][] = [
            'detector' => OutOfOfficeDetector::class,
            'configuration' => $configuration,
        ];
    }
}
