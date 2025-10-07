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

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use MoveElevator\Typo3LoginWarning\Service\GeolocationServiceInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * NewIpDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class NewIpDetector extends AbstractDetector
{
    public function __construct(
        private IpLogRepository $ipLogRepository,
        private ?GeolocationServiceInterface $geolocationService = null,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     * @throws Exception
     */
    public function detect(AbstractUserAuthentication $user, array $configuration = []): bool
    {
        $userArray = $user->user;

        if (
            array_key_exists('whitelist', $configuration) &&
            is_array($configuration['whitelist']) &&
            in_array($this->getIpAddress(false), $configuration['whitelist'], true)
        ) {
            return false;
        }

        $shouldHashIp = !array_key_exists('hashIpAddress', $configuration) || (bool)$configuration['hashIpAddress'];
        $ipAddress = $this->getIpAddress($shouldHashIp);
        $rawIpAddress = $shouldHashIp ? $this->getIpAddress(false) : $ipAddress;

        if (!$this->ipLogRepository->findByUserAndIp((int)$userArray['uid'], $ipAddress)) {
            if ($this->shouldFetchGeolocation($configuration, $rawIpAddress)) {
                $this->additionalData['locationData'] = $this->geolocationService?->getLocationData($rawIpAddress);
            }
            $this->ipLogRepository->addUserIp((int)$userArray['uid'], $ipAddress);
            return true;
        }
        return false;
    }

    private function getIpAddress(bool $hashedIpAddress = true): string
    {
        $ipAddress = GeneralUtility::getIndpEnv('REMOTE_ADDR');

        if ($hashedIpAddress) {
            $ipAddress = hash('sha256', $ipAddress);
        }

        return $ipAddress;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function shouldFetchGeolocation(array $configuration, string $rawIp): bool
    {
        if (($configuration['fetchGeolocation'] ?? false) !== true || $this->geolocationService === null) {
            return false;
        }
        // Only for public, non-reserved IPs
        return filter_var(
            $rawIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

}
