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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Configuration;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * DetectorConfigurationBuilderTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class DetectorConfigurationBuilderTest extends TestCase
{
    private ExtensionConfiguration&MockObject $extensionConfiguration;
    private DetectorConfigurationBuilder $subject;

    protected function setUp(): void
    {
        $this->extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $this->subject = new DetectorConfigurationBuilder($this->extensionConfiguration);
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);
    }

    public function testIsActiveReturnsTrueWhenDetectorIsActive(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['newIp' => ['active' => true]]);

        self::assertTrue($this->subject->isActive(NewIpDetector::class));
    }

    public function testIsActiveReturnsFalseWhenDetectorIsInactive(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['newIp' => ['active' => false]]);

        self::assertFalse($this->subject->isActive(NewIpDetector::class));
    }

    public function testIsActiveReturnsFalseWhenDetectorConfigMissing(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([]);

        self::assertFalse($this->subject->isActive(NewIpDetector::class));
    }

    public function testGetExtensionConfigurationHandlesException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($logger);

        $this->extensionConfiguration
            ->expects(self::once())
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willThrowException(new \Exception('Configuration not found'));

        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Could not load extension configuration: {message}',
                ['message' => 'Configuration not found']
            );

        $result = $this->subject->getExtensionConfiguration();

        self::assertSame([], $result);
    }

    public function testBuildNewIpConfigWithDefaults(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['newIp' => ['active' => true]]);

        $result = $this->subject->build(NewIpDetector::class);

        self::assertSame([
            'hashIpAddress' => true,
            'fetchGeolocation' => true,
            'affectedUsers' => 'all',
            'notificationReceiver' => 'recipients',
            'whitelist' => ['127.0.0.1'],
        ], $result);
    }

    public function testBuildNewIpConfigWithCustomValues(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'newIp' => [
                    'active' => true,
                    'hashIpAddress' => false,
                    'fetchGeolocation' => false,
                    'affectedUsers' => 'admins',
                    'notificationReceiver' => 'both',
                    'whitelist' => '192.168.1.1, 10.0.0.1',
                ],
            ]);

        $result = $this->subject->build(NewIpDetector::class);

        self::assertSame([
            'hashIpAddress' => false,
            'fetchGeolocation' => false,
            'affectedUsers' => 'admins',
            'notificationReceiver' => 'both',
            'whitelist' => ['192.168.1.1', '10.0.0.1'],
        ], $result);
    }

    public function testBuildLongTimeNoSeeConfigWithDefaults(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['longTimeNoSee' => ['active' => true]]);

        $result = $this->subject->build(LongTimeNoSeeDetector::class);

        self::assertSame([
            'thresholdDays' => 365,
            'affectedUsers' => 'all',
            'notificationReceiver' => 'recipients',
        ], $result);
    }

    public function testBuildLongTimeNoSeeConfigWithCustomValues(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'longTimeNoSee' => [
                    'active' => true,
                    'thresholdDays' => 180,
                    'affectedUsers' => 'maintainers',
                    'notificationReceiver' => 'user',
                ],
            ]);

        $result = $this->subject->build(LongTimeNoSeeDetector::class);

        self::assertSame([
            'thresholdDays' => 180,
            'affectedUsers' => 'maintainers',
            'notificationReceiver' => 'user',
        ], $result);
    }

    public function testBuildOutOfOfficeConfigWithDefaults(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['outOfOffice' => ['active' => true]]);

        $GLOBALS['TYPO3_CONF_VARS']['SYS']['phpTimeZone'] = 'Europe/Berlin';

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame('Europe/Berlin', $result['timezone']);
        self::assertSame('all', $result['affectedUsers']);
        self::assertSame('recipients', $result['notificationReceiver']);
        self::assertSame([
            'monday' => ['06:00', '19:00'],
            'tuesday' => ['06:00', '19:00'],
            'wednesday' => ['06:00', '19:00'],
            'thursday' => ['06:00', '19:00'],
            'friday' => ['06:00', '19:00'],
        ], $result['workingHours']);
        self::assertSame([], $result['holidays']);
        self::assertSame([], $result['vacationPeriods']);
    }

    public function testBuildOutOfOfficeConfigWithCustomWorkingHours(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'outOfOffice' => [
                    'active' => true,
                    'workingHours' => '{"monday":["09:00","17:00"],"friday":["09:00","15:00"]}',
                    'timezone' => 'America/New_York',
                ],
            ]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame('America/New_York', $result['timezone']);
        self::assertSame([
            'monday' => ['09:00', '17:00'],
            'friday' => ['09:00', '15:00'],
        ], $result['workingHours']);
    }

    public function testBuildOutOfOfficeConfigWithHolidaysAndVacations(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'outOfOffice' => [
                    'active' => true,
                    'holidays' => '2025-01-01, 2025-12-25',
                    'vacationPeriods' => '2025-07-15:2025-07-30, 2025-12-20:2025-12-31',
                ],
            ]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame(['2025-01-01', '2025-12-25'], $result['holidays']);
        self::assertSame([
            ['2025-07-15', '2025-07-30'],
            ['2025-12-20', '2025-12-31'],
        ], $result['vacationPeriods']);
    }

    public function testBuildNotificationConfigWithDefaults(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([]);

        $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] = 'admin@example.com';

        $result = $this->subject->buildNotificationConfig();

        self::assertSame('admin@example.com', $result['recipient']);
    }

    public function testBuildNotificationConfigWithCustomValues(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'notificationRecipients' => 'security@example.com',
            ]);

        $result = $this->subject->buildNotificationConfig();

        self::assertSame('security@example.com', $result['recipient']);
    }

    public function testGetConfigPrefixReturnsCorrectPrefix(): void
    {
        self::assertSame('newIp', $this->subject->getConfigPrefix(NewIpDetector::class));
        self::assertSame('longTimeNoSee', $this->subject->getConfigPrefix(LongTimeNoSeeDetector::class));
        self::assertSame('outOfOffice', $this->subject->getConfigPrefix(OutOfOfficeDetector::class));
    }

    public function testGetConfigPrefixReturnsEmptyStringForUnknownDetector(): void
    {
        self::assertSame('', $this->subject->getConfigPrefix('UnknownDetector'));
    }
}
