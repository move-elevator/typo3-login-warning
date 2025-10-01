<?php

declare(strict_types=1);

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

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3LoginWarning\Domain\Repository\UserLogRepository;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * LongTimeNoSeeDetector.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class LongTimeNoSeeDetector extends AbstractDetector
{
    private const DEFAULT_THRESHOLD_DAYS = 365;

    private ?int $daysSinceLastLogin = null;

    public function __construct(
        private UserLogRepository $userLogRepository,
    ) {}

    /**
     * Unfortunately, I was unable to use the existing “lastlogin” field in the backend user. In the AfterUserLoggedInEvent,
     * the field is already overwritten with the current time, so that the required time span can no longer be accessed.
     * Unfortunately, I was also unable to find another event or hook to access this information “earlier” during
     * authentication. For the time being, the only option was to store this information in a separate table.
     *
     * @param array<string, mixed> $configuration
     * @throws Exception
     */
    public function detect(AbstractUserAuthentication $user, array $configuration = []): bool
    {
        $userArray = $user->user;

        // Check user role filtering
        if (!$this->shouldDetectForUser($user, $configuration)) {
            return false;
        }

        $userId = (int)$userArray['uid'];
        $currentTimestamp = time();

        $thresholdDays = (int)($configuration['thresholdDays'] ?? self::DEFAULT_THRESHOLD_DAYS);
        $thresholdTimestamp = $currentTimestamp - ($thresholdDays * 24 * 60 * 60);

        $lastLoginCheckTimestamp = $this->userLogRepository->getLastLoginCheckTimestamp($userId);

        // Calculate days since last login for additional data
        if ($lastLoginCheckTimestamp !== null) {
            $this->daysSinceLastLogin = (int)floor(($currentTimestamp - $lastLoginCheckTimestamp) / (24 * 60 * 60));
        }

        $this->userLogRepository->updateLastLoginCheckTimestamp($userId, $currentTimestamp);

        return $lastLoginCheckTimestamp !== null && $lastLoginCheckTimestamp <= $thresholdTimestamp;
    }

    public function getDaysSinceLastLogin(): ?int
    {
        return $this->daysSinceLastLogin;
    }

}
