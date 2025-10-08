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

namespace MoveElevator\Typo3LoginWarning\Detector;

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use MoveElevator\Typo3LoginWarning\Service\GeolocationServiceInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function in_array;
use function is_array;

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
     *
     * @throws Exception
     */
    public function detect(AbstractUserAuthentication $user, array $configuration = []): bool
    {
        $userArray = $user->user;

        if (
            array_key_exists('whitelist', $configuration)
            && is_array($configuration['whitelist'])
            && in_array($this->getIpAddress(false), $configuration['whitelist'], true)
        ) {
            return false;
        }

        $shouldHashIp = !array_key_exists('hashIpAddress', $configuration) || (bool) $configuration['hashIpAddress'];
        $ipAddress = $this->getIpAddress($shouldHashIp);
        $rawIpAddress = $shouldHashIp ? $this->getIpAddress(false) : $ipAddress;

        if (!$this->ipLogRepository->findByUserAndIp((int) $userArray['uid'], $ipAddress)) {
            if ($this->shouldFetchGeolocation($configuration, $rawIpAddress)) {
                $this->additionalData['locationData'] = $this->geolocationService?->getLocationData($rawIpAddress);
            }
            $this->ipLogRepository->addUserIp((int) $userArray['uid'], $ipAddress);

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
        if (($configuration['fetchGeolocation'] ?? false) !== true || null === $this->geolocationService) {
            return false;
        }

        // Only for public, non-reserved IPs
        return false !== filter_var(
            $rawIp,
            \FILTER_VALIDATE_IP,
            \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE,
        );
    }
}
