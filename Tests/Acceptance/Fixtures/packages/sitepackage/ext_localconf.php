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

// For testing purposes we disable the login rate limit
$GLOBALS['TYPO3_CONF_VARS']['BE']['loginRateLimit'] = 0;

$GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] = 'test456@test.de';
