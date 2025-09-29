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
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;
use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;

$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][500] = 'EXT:' . Configuration::EXT_KEY . '/Resources/Private/Templates/Email';

// default configuration for detectors
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_detector'] = [
    NewIpDetector::class => [
        'hashIpAddress' => true,
        'fetchGeolocation' => true,
        'whitelist' => [],
    ],
    LongTimeNoSeeDetector::class => [
        'thresholdDays' => 365,
    ],
    OutOfOfficeDetector::class => [
        'workingHours' => [
            'monday' => ['07:00', '19:00'],
            'tuesday' => ['07:00', '19:00'],
            'wednesday' => ['07:00', '19:00'],
            'thursday' => ['07:00', '19:00'],
            'friday' => ['07:00', '19:00'],
        ],
        'timezone' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone'] ?? 'UTC',
        'holidays' => [],
        'vacationPeriods' => [],
    ],
];

// default configuration for notifications
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'] = [
    EmailNotification::class => [
        'recipient' => '',
    ],
];

$GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['detector'] = [];
