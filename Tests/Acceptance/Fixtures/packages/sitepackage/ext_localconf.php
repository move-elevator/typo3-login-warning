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

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;
use MoveElevator\Typo3LoginWarning\Trigger\NewIp;

// For testing purposes we disable the login rate limit
$GLOBALS['TYPO3_CONF_VARS']['BE']['loginRateLimit'] = 0;

// Example configuration
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [
    NewIp::class => [
        //        'hashIpAddress' => true,
        //        'whitelist' => [
        //            '192.168.97.5',
        //        ],
        'notification' => [
            EmailNotification::class => [
                'recipient' => 'test123@test.de',
            ],
        ],
    ],
];
