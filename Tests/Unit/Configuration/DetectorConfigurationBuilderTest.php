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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Configuration;

use Exception;
use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Detector\{LongTimeNoSeeDetector, NewIpDetector, OutOfOfficeDetector};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * DetectorConfigurationBuilderTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
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
            ->willThrowException(new Exception('Configuration not found'));

        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Could not load extension configuration: {message}',
                ['message' => 'Configuration not found'],
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
            'monday' => ['06:00', '20:00'],
            'tuesday' => ['06:00', '20:00'],
            'wednesday' => ['06:00', '20:00'],
            'thursday' => ['06:00', '20:00'],
            'friday' => ['06:00', '20:00'],
        ], $result['workingHours']);
        self::assertSame([], $result['blockedPeriods']);
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

    public function testBuildOutOfOfficeConfigWithBlockedPeriods(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'outOfOffice' => [
                    'active' => true,
                    'blockedPeriods' => '2025-01-01, 2025-12-25, 2025-07-15:2025-07-30',
                ],
            ]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame([
            ['2025-01-01'],
            ['2025-12-25'],
            ['2025-07-15', '2025-07-30'],
        ], $result['blockedPeriods']);
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

    public function testGetExtensionConfigurationReturnsCachedValue(): void
    {
        // First call should fetch from ExtensionConfiguration
        $this->extensionConfiguration
            ->expects(self::once()) // Should only be called once
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['newIp' => ['active' => true]]);

        // First call - fetches and caches
        $result1 = $this->subject->getExtensionConfiguration();
        self::assertSame(['newIp' => ['active' => true]], $result1);

        // Second call - should return cached value (line 50)
        $result2 = $this->subject->getExtensionConfiguration();
        self::assertSame(['newIp' => ['active' => true]], $result2);
    }

    public function testBuildReturnsEmptyArrayWhenPrefixDoesNotExist(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['someOtherConfig' => ['key' => 'value']]);

        // Test with NewIpDetector - prefix 'newIp' doesn't exist in config
        $result = $this->subject->build(NewIpDetector::class);

        // Should get defaults from buildNewIpConfig
        self::assertSame([
            'hashIpAddress' => true,
            'fetchGeolocation' => true,
            'affectedUsers' => 'all',
            'notificationReceiver' => 'recipients',
            'whitelist' => ['127.0.0.1'],
        ], $result);
    }

    public function testBuildReturnsEmptyArrayWhenPrefixIsNotArray(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['newIp' => 'invalid-not-an-array']); // Tests line 120

        // Should still build config with defaults
        $result = $this->subject->build(NewIpDetector::class);

        self::assertSame([
            'hashIpAddress' => true,
            'fetchGeolocation' => true,
            'affectedUsers' => 'all',
            'notificationReceiver' => 'recipients',
            'whitelist' => ['127.0.0.1'],
        ], $result);
    }

    public function testBuildNewIpConfigHandlesEmptyWhitelist(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'newIp' => [
                    'active' => true,
                    'whitelist' => '', // Tests line 238
                ],
            ]);

        $result = $this->subject->build(NewIpDetector::class);

        // Empty whitelist should return empty array
        self::assertSame([], $result['whitelist']);
    }

    public function testBuildOutOfOfficeConfigExpandsWorkdayShortcut(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'outOfOffice' => [
                    'active' => true,
                    'workingHours' => '{"workday":["09:00","17:00"]}',
                ],
            ]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame([
            'monday' => ['09:00', '17:00'],
            'tuesday' => ['09:00', '17:00'],
            'wednesday' => ['09:00', '17:00'],
            'thursday' => ['09:00', '17:00'],
            'friday' => ['09:00', '17:00'],
        ], $result['workingHours']);
    }

    public function testBuildOutOfOfficeConfigExpandsWeekendShortcut(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'outOfOffice' => [
                    'active' => true,
                    'workingHours' => '{"weekend":["10:00","14:00"]}',
                ],
            ]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame([
            'saturday' => ['10:00', '14:00'],
            'sunday' => ['10:00', '14:00'],
        ], $result['workingHours']);
    }

    public function testBuildOutOfOfficeConfigCombinesShortcutsAndSpecificDays(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn([
                'outOfOffice' => [
                    'active' => true,
                    'workingHours' => '{"workday":["09:00","17:00"],"weekend":["10:00","14:00"],"friday":["09:00","15:00"]}',
                ],
            ]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame([
            'monday' => ['09:00', '17:00'],
            'tuesday' => ['09:00', '17:00'],
            'wednesday' => ['09:00', '17:00'],
            'thursday' => ['09:00', '17:00'],
            'friday' => ['09:00', '15:00'], // Specific override
            'saturday' => ['10:00', '14:00'],
            'sunday' => ['10:00', '14:00'],
        ], $result['workingHours']);
    }

    public function testBuildOutOfOfficeConfigDefaultsUse20Hours(): void
    {
        $this->extensionConfiguration
            ->method('get')
            ->with(Configuration::EXT_KEY)
            ->willReturn(['outOfOffice' => ['active' => true]]);

        $result = $this->subject->build(OutOfOfficeDetector::class);

        self::assertSame([
            'monday' => ['06:00', '20:00'],
            'tuesday' => ['06:00', '20:00'],
            'wednesday' => ['06:00', '20:00'],
            'thursday' => ['06:00', '20:00'],
            'friday' => ['06:00', '20:00'],
        ], $result['workingHours']);
    }
}
