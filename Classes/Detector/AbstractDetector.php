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

namespace MoveElevator\Typo3LoginWarning\Detector;

use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * AbstractDetector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
abstract class AbstractDetector implements DetectorInterface
{
    /**
     * Check if detection should be performed for the given user based on role filtering configuration.
     */
    protected function shouldDetectForUser(AbstractUserAuthentication $user, array $configuration): bool
    {
        $userArray = $user->user;

        // Check if detection should only apply to admins
        if (
            array_key_exists('onlyAdmins', $configuration) &&
            $configuration['onlyAdmins'] === true &&
            !($userArray['admin'] ?? false)
        ) {
            return false;
        }

        // Check if detection should only apply to system maintainers
        if (
            array_key_exists('onlySystemMaintainers', $configuration) &&
            $configuration['onlySystemMaintainers'] === true &&
            !in_array((int)$userArray['uid'], $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] ?? [], true)
        ) {
            return false;
        }

        return true;
    }
}
