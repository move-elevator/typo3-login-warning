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

namespace MoveElevator\Typo3LoginWarning\Configuration;

use Exception;
use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Detector\{LongTimeNoSeeDetector, NewIpDetector, OutOfOfficeDetector};
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

use function is_array;
use function is_string;

/**
 * DetectorConfigurationBuilder.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class DetectorConfigurationBuilder implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var array<string, mixed>
     */
    private array $extensionConfiguration = [];

    public function __construct(
        private readonly ExtensionConfiguration $extConfiguration,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getExtensionConfiguration(): array
    {
        if ([] !== $this->extensionConfiguration) {
            return $this->extensionConfiguration;
        }

        try {
            $this->extensionConfiguration = $this->extConfiguration->get(Configuration::EXT_KEY);
        } catch (Exception $e) {
            $this->logger?->warning('Could not load extension configuration: {message}', [
                'message' => $e->getMessage(),
            ]);
            $this->extensionConfiguration = [];
        }

        return $this->extensionConfiguration;
    }

    public function isActive(string $detectorClass): bool
    {
        $extensionConfiguration = $this->getExtensionConfiguration();
        $prefix = $this->getConfigPrefix($detectorClass);

        return (bool) ($extensionConfiguration[$prefix]['active'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $detectorClass): array
    {
        $extensionConfiguration = $this->getExtensionConfiguration();
        $prefix = $this->getConfigPrefix($detectorClass);
        $config = $this->extractConfigForPrefix($prefix, $extensionConfiguration);

        return match ($detectorClass) {
            NewIpDetector::class => $this->buildNewIpConfig($config),
            LongTimeNoSeeDetector::class => $this->buildLongTimeNoSeeConfig($config),
            OutOfOfficeDetector::class => $this->buildOutOfOfficeConfig($config),
            default => $config,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildNotificationConfig(): array
    {
        $extensionConfiguration = $this->getExtensionConfiguration();

        return [
            'recipient' => $extensionConfiguration['notificationRecipients'] ?? $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] ?? '',
        ];
    }

    public function getConfigPrefix(string $detectorClass): string
    {
        return match ($detectorClass) {
            NewIpDetector::class => 'newIp',
            LongTimeNoSeeDetector::class => 'longTimeNoSee',
            OutOfOfficeDetector::class => 'outOfOffice',
            default => '',
        };
    }

    /**
     * @param array<string, mixed> $extensionConfiguration
     *
     * @return array<string, mixed>
     */
    private function extractConfigForPrefix(string $prefix, array $extensionConfiguration): array
    {
        if (!isset($extensionConfiguration[$prefix]) || !is_array($extensionConfiguration[$prefix])) {
            return [];
        }

        $config = $extensionConfiguration[$prefix];

        // Remove 'active' key as it's handled separately
        unset($config['active']);

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function buildNewIpConfig(array $config): array
    {
        return [
            'hashIpAddress' => (bool) ($config['hashIpAddress'] ?? true),
            'fetchGeolocation' => (bool) ($config['fetchGeolocation'] ?? true),
            'affectedUsers' => $config['affectedUsers'] ?? 'all',
            'notificationReceiver' => $config['notificationReceiver'] ?? 'recipients',
            'whitelist' => $this->parseCommaSeparatedList($config['whitelist'] ?? '127.0.0.1'),
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function buildLongTimeNoSeeConfig(array $config): array
    {
        return [
            'thresholdDays' => (int) ($config['thresholdDays'] ?? 365),
            'affectedUsers' => $config['affectedUsers'] ?? 'all',
            'notificationReceiver' => $config['notificationReceiver'] ?? 'recipients',
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function buildOutOfOfficeConfig(array $config): array
    {
        $result = [
            'timezone' => $config['timezone'] ?? $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone'] ?? 'UTC',
            'affectedUsers' => $config['affectedUsers'] ?? 'all',
            'notificationReceiver' => $config['notificationReceiver'] ?? 'recipients',
        ];

        $result['workingHours'] = $this->parseWorkingHours($config);
        $result['holidays'] = $this->parseHolidays($config);
        $result['vacationPeriods'] = $this->parseConfigVacationPeriods($config);

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, array<int, string>>
     */
    private function parseWorkingHours(array $config): array
    {
        if (isset($config['workingHours']) && is_string($config['workingHours']) && '' !== $config['workingHours']) {
            $workingHours = json_decode($config['workingHours'], true);
            if (is_array($workingHours)) {
                return $workingHours;
            }
        }

        return [
            'monday' => ['06:00', '19:00'],
            'tuesday' => ['06:00', '19:00'],
            'wednesday' => ['06:00', '19:00'],
            'thursday' => ['06:00', '19:00'],
            'friday' => ['06:00', '19:00'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<int, string>
     */
    private function parseHolidays(array $config): array
    {
        if (isset($config['holidays']) && is_string($config['holidays']) && '' !== $config['holidays']) {
            return $this->parseCommaSeparatedList($config['holidays']);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<int, array<int, string>>
     */
    private function parseConfigVacationPeriods(array $config): array
    {
        if (isset($config['vacationPeriods']) && is_string($config['vacationPeriods']) && '' !== $config['vacationPeriods']) {
            return $this->parseVacationPeriods($config['vacationPeriods']);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function parseCommaSeparatedList(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        return array_map('trim', explode(',', $value));
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function parseVacationPeriods(string $value): array
    {
        $vacationPeriods = [];
        $periods = explode(',', $value);

        foreach ($periods as $period) {
            $period = trim($period);
            if (str_contains($period, ':')) {
                $vacationPeriods[] = explode(':', $period);
            }
        }

        return $vacationPeriods;
    }
}
