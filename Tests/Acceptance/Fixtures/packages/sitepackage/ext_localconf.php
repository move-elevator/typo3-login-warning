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
use MoveElevator\Typo3LoginWarning\Configuration\LoginWarning;
use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;

// For testing purposes we disable the login rate limit
$GLOBALS['TYPO3_CONF_VARS']['BE']['loginRateLimit'] = 0;

$GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] = 'test456@test.de';

// Clean configuration using shorthand syntax
// NewIp Detector with specific configuration for testing
LoginWarning::newIp([
    'hashIpAddress' => true,
    'fetchGeolocation' => false,
    'whitelist' => [], // No whitelist for testing
    'notification' => [
        EmailNotification::class => [
            'recipient' => 'test123@test.de',
        ],
    ],
]);

// LongTimeNoSee Detector with default settings
LoginWarning::longTimeNoSee([
    'thresholdDays' => 365,
    'notification' => [
        EmailNotification::class => [
            'recipient' => 'longterm@test.de',
        ],
    ],
]);

// OutOfOffice Detector with specific test configuration
LoginWarning::outOfOffice([
    'workingHours' => [
        'monday' => [['09:00', '12:00'], ['16:00', '17:00']], // Testing lunch break
    ],
    'timezone' => 'Europe/Berlin',
    'holidays' => [],
    'vacationPeriods' => [],
    'notification' => [
        EmailNotification::class => [
            'recipient' => 'outofoffice@test.de',
        ],
    ],
]);
