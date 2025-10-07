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

namespace MoveElevator\Typo3LoginWarning\Notification;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * NotifierInterface.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
interface NotifierInterface
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $additionalValues
     */
    public function notify(AbstractUserAuthentication $user, ServerRequestInterface $request, string $triggerClass, array $configuration = [], array $additionalValues = []): void;
}
