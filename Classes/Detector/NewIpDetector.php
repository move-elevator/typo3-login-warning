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
use MoveElevator\Typo3LoginWarning\Utility\{DeviceInfoParser, IpAddressMatcher};
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
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
        private readonly IpLogRepository $ipLogRepository,
        private readonly ?GeolocationServiceInterface $geolocationService = null,
    ) {}

    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     *
     * @throws Exception
     */
    public function detect(array $userArray, array $configuration = [], ?ServerRequestInterface $request = null): bool
    {
        $rawIpAddress = GeneralUtility::getIndpEnv('REMOTE_ADDR');

        if (
            array_key_exists('whitelist', $configuration)
            && is_array($configuration['whitelist'])
            && IpAddressMatcher::isWhitelisted($rawIpAddress, $configuration['whitelist'])
        ) {
            return false;
        }

        $userId = (int) ($userArray['uid'] ?? 0);

        $shouldHashIp = !array_key_exists('hashIpAddress', $configuration) || (bool) $configuration['hashIpAddress'];
        $ipForStorage = $shouldHashIp ? hash_hmac('sha256', $rawIpAddress, $this->getHmacKey()) : $rawIpAddress;

        $identifierHash = $this->generateIdentifierHash($userId, $ipForStorage);

        if (!$this->ipLogRepository->findByHash($identifierHash)) {
            $this->collectAdditionalData($configuration, $rawIpAddress, $request);
            $this->ipLogRepository->addHash($identifierHash);

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

    private function generateIdentifierHash(int $userId, string $ipAddress): string
    {
        $identifier = $userId.':'.$ipAddress;

        return hash_hmac('sha256', $identifier, $this->getHmacKey());
    }

    private function getHmacKey(): string
    {
        $key = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['hmacKey'] ?? '';

        if ('' === $key) {
            $key = $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? '';
        }

        if ('' === $key) {
            throw new RuntimeException('No HMAC key configured for login warning extension. Please set $GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'typo3_login_warning\'][\'hmacKey\'] or ensure $GLOBALS[\'TYPO3_CONF_VARS\'][\'SYS\'][\'encryptionKey\'] is set.', 8700213462);
        }

        return $key;
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
