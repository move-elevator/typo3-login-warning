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
use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use MoveElevator\Typo3LoginWarning\Service\GeolocationServiceInterface;
use MoveElevator\Typo3LoginWarning\Utility\IpAddressMatcher;
use MoveElevator\Typo3LoginWarning\Utility\DeviceInfoParser;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function is_array;

/**
 * NewIpDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class NewIpDetector extends AbstractDetector
{
    public function __construct(
        private IpLogRepository $ipLogRepository,
        private ?GeolocationServiceInterface $geolocationService = null,
    ) {}

    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     *
     * @throws Exception
     */
    public function detect(array $userArray, array $configuration = [], ?ServerRequestInterface $request = null): bool
    {
        $rawIpAddress = $this->getIpAddress(false);

        if (
            array_key_exists('whitelist', $configuration)
            && is_array($configuration['whitelist'])
            && IpAddressMatcher::isWhitelisted($rawIpAddress, $configuration['whitelist'])
        ) {
            return false;
        }

        $shouldHashIp = !array_key_exists('hashIpAddress', $configuration) || (bool) $configuration['hashIpAddress'];
        $ipAddress = $this->getIpAddress($shouldHashIp);
        $userId = (int) ($userArray['uid'] ?? 0);

        if (!$this->ipLogRepository->findByUserAndIp($userId, $ipAddress)) {
            $this->collectAdditionalData($configuration, $rawIpAddress, $request);
            $this->ipLogRepository->addUserIp($userId, $ipAddress);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function collectAdditionalData(array $configuration, string $rawIpAddress, ?ServerRequestInterface $request): void
    {
        if ($this->shouldFetchGeolocation($configuration, $rawIpAddress)) {
            $this->additionalData['locationData'] = $this->geolocationService?->getLocationData($rawIpAddress);
        }

        if (($configuration['includeDeviceInfo'] ?? true) === true) {
            $deviceInfo = DeviceInfoParser::parseFromRequest($request);
            if (null !== $deviceInfo) {
                $this->additionalData['deviceInfo'] = $deviceInfo;
            }
        }
    }

    private function getIpAddress(bool $hashedIpAddress = true): string
    {
        $ipAddress = GeneralUtility::getIndpEnv('REMOTE_ADDR');

        if ($hashedIpAddress) {
            $ipAddress = hash_hmac('sha256', $ipAddress, $this->getHmacKey());
        }

        return $ipAddress;
    }

    private function getHmacKey(): string
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey'] ?? '';
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
