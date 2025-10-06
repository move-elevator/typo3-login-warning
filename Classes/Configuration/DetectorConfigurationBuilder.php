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

namespace MoveElevator\Typo3LoginWarning\Configuration;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * DetectorConfigurationBuilder.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
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
        private readonly ExtensionConfiguration $extConfiguration
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getExtensionConfiguration(): array
    {
        if ($this->extensionConfiguration !== []) {
            return $this->extensionConfiguration;
        }

        try {
            $this->extensionConfiguration = $this->extConfiguration->get(Configuration::EXT_KEY);
        } catch (\Exception $e) {
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
        return (bool)($extensionConfiguration[$prefix]['active'] ?? false);
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

    /**
     * @param array<string, mixed> $extensionConfiguration
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
     * @return array<string, mixed>
     */
    private function buildNewIpConfig(array $config): array
    {
        return [
            'hashIpAddress' => (bool)($config['hashIpAddress'] ?? true),
            'fetchGeolocation' => (bool)($config['fetchGeolocation'] ?? true),
            'affectedUsers' => $config['affectedUsers'] ?? 'all',
            'notificationReceiver' => $config['notificationReceiver'] ?? 'recipients',
            'whitelist' => $this->parseCommaSeparatedList($config['whitelist'] ?? '127.0.0.1'),
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildLongTimeNoSeeConfig(array $config): array
    {
        return [
            'thresholdDays' => (int)($config['thresholdDays'] ?? 365),
            'affectedUsers' => $config['affectedUsers'] ?? 'all',
            'notificationReceiver' => $config['notificationReceiver'] ?? 'recipients',
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function buildOutOfOfficeConfig(array $config): array
    {
        $result = [
            'timezone' => $config['timezone'] ?? $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone'] ?? 'UTC',
            'affectedUsers' => $config['affectedUsers'] ?? 'all',
            'notificationReceiver' => $config['notificationReceiver'] ?? 'recipients',
        ];

        // Parse working hours JSON
        if (isset($config['workingHours']) && is_string($config['workingHours']) && $config['workingHours'] !== '') {
            $workingHours = json_decode($config['workingHours'], true);
            if (is_array($workingHours)) {
                $result['workingHours'] = $workingHours;
            }
        }

        // Default working hours if not set
        if (!isset($result['workingHours'])) {
            $result['workingHours'] = [
                'monday' => ['06:00', '19:00'],
                'tuesday' => ['06:00', '19:00'],
                'wednesday' => ['06:00', '19:00'],
                'thursday' => ['06:00', '19:00'],
                'friday' => ['06:00', '19:00'],
            ];
        }

        // Parse holidays
        if (isset($config['holidays']) && is_string($config['holidays']) && $config['holidays'] !== '') {
            $result['holidays'] = $this->parseCommaSeparatedList($config['holidays']);
        } else {
            $result['holidays'] = [];
        }

        // Parse vacation periods
        if (isset($config['vacationPeriods']) && is_string($config['vacationPeriods']) && $config['vacationPeriods'] !== '') {
            $result['vacationPeriods'] = $this->parseVacationPeriods($config['vacationPeriods']);
        } else {
            $result['vacationPeriods'] = [];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function parseCommaSeparatedList(string $value): array
    {
        if ($value === '') {
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

    public function getConfigPrefix(string $detectorClass): string
    {
        return match ($detectorClass) {
            NewIpDetector::class => 'newIp',
            LongTimeNoSeeDetector::class => 'longTimeNoSee',
            OutOfOfficeDetector::class => 'outOfOffice',
            default => '',
        };
    }
}
