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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function in_array;
use function is_array;
use function sprintf;
use function str_contains;
use function str_replace;

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
        $userId = (int) ($userArray['uid'] ?? 0);

        if (!$this->ipLogRepository->findByUserAndIp($userId, $ipAddress)) {
            if ($this->shouldFetchGeolocation($configuration, $rawIpAddress)) {
                $this->additionalData['locationData'] = $this->geolocationService?->getLocationData($rawIpAddress);
            }

            if (($configuration['includeDeviceInfo'] ?? true) === true) {
                $this->addDeviceInfo($request);
            }

            $this->ipLogRepository->addUserIp($userId, $ipAddress);

            return true;
        }

        return false;
    }

    private function addDeviceInfo(?ServerRequestInterface $request = null): void
    {
        if (null === $request) {
            return;
        }
        $userAgent = $request->getHeaderLine('User-Agent');
        if ('' === $userAgent) {
            return;
        }

        $this->additionalData['deviceInfo'] = [
            'userAgent' => $userAgent,
            'browser' => $this->parseBrowser($userAgent),
            'os' => $this->parseOperatingSystem($userAgent),
            'date' => date(sprintf('%s %s', $GLOBALS['TYPO3_CONF_VARS']['SYS']['ddmmyy'] ?? 'Y-m-d', $GLOBALS['TYPO3_CONF_VARS']['SYS']['hhmm'] ?? 'H:i')),
        ];
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

    private function parseBrowser(string $userAgent): string
    {
        $browsers = [
            '/Edg\/([0-9.]+)/' => 'Edge',
            '/Chrome\/([0-9.]+)/' => 'Chrome',
            '/Firefox\/([0-9.]+)/' => 'Firefox',
            '/Safari\/([0-9.]+)/' => 'Safari',
            '/Opera\/([0-9.]+)/' => 'Opera',
        ];

        foreach ($browsers as $pattern => $name) {
            if (1 === preg_match($pattern, $userAgent, $matches)) {
                return $name.' '.$matches[1];
            }
        }

        return 'Unknown';
    }

    private function parseOperatingSystem(string $userAgent): string
    {
        $operatingSystems = [
            '/Windows NT 10.0/' => 'Windows 10/11',
            '/Windows NT 6.3/' => 'Windows 8.1',
            '/Windows NT 6.2/' => 'Windows 8',
            '/Windows NT 6.1/' => 'Windows 7',
            '/Macintosh.*Mac OS X ([0-9._]+)/' => 'macOS',
            '/Linux/' => 'Linux',
            '/Android ([0-9.]+)/' => 'Android',
            '/iPhone OS ([0-9_]+)/' => 'iOS',
            '/iPad.*OS ([0-9_]+)/' => 'iPadOS',
        ];

        foreach ($operatingSystems as $pattern => $name) {
            if (1 === preg_match($pattern, $userAgent, $matches)) {
                if (str_contains($name, 'OS')) {
                    $version = $matches[1] ?? '';
                    $version = str_replace('_', '.', $version);

                    return $name.' '.$version;
                }

                return $name;
            }
        }

        return 'Unknown';
    }
}
