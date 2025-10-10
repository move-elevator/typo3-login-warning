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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Detector;

use DateTime;
use DateTimeZone;
use MoveElevator\Typo3LoginWarning\Detector\{DetectorInterface, OutOfOfficeDetector};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * OutOfOfficeDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
final class OutOfOfficeDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Set a fixed timezone for consistent testing
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Reset timezone
        date_default_timezone_set('UTC');
    }

    public function testImplementsDetectorInterface(): void
    {
        $subject = new OutOfOfficeDetector();
        self::assertInstanceOf(DetectorInterface::class, $subject);
    }

    public function testGetAdditionalDataReturnsEmptyArrayInitially(): void
    {
        $subject = new OutOfOfficeDetector();
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testDetectReturnsFalseForWorkingHours(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'timezone' => 'UTC',
        ];

        // Mock current time to be Monday 10:00
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00'); // Monday
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testDetectReturnsTrueForWeekend(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'timezone' => 'UTC',
        ];

        // Mock current time to be Saturday
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-04 10:00:00'); // Saturday
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('outside_hours', $additionalData['type']);
        self::assertSame('Saturday', $additionalData['dayOfWeek']);
    }

    public function testDetectReturnsTrueForOutsideWorkingHours(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'timezone' => 'UTC',
        ];

        // Mock current time to be Monday 18:00 (after working hours)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 18:00:00'); // Monday
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('outside_hours', $additionalData['type']);
        self::assertSame('Monday', $additionalData['dayOfWeek']);
        self::assertSame(['09:00', '17:00'], $additionalData['workingHours']);
    }

    public function testDetectReturnsTrueForHoliday(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'holidays' => ['2025-01-06'],
            'timezone' => 'UTC',
        ];

        // Mock current time to be Monday (holiday) 10:00
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00'); // Monday (holiday)
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('holiday', $additionalData['type']);
        self::assertSame('2025-01-06', $additionalData['date']);
        self::assertSame('Monday', $additionalData['dayOfWeek']);
    }

    public function testDetectReturnsTrueForVacationPeriod(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'vacationPeriods' => [
                ['2025-01-06', '2025-01-10'],
            ],
            'timezone' => 'UTC',
        ];

        // Mock current time to be within vacation period
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-08 10:00:00'); // Wednesday in vacation
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('vacation', $additionalData['type']);
        self::assertSame('2025-01-08', $additionalData['date']);
        self::assertSame('Wednesday', $additionalData['dayOfWeek']);
    }

    public function testDetectHandlesMultipleTimeRanges(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => [['09:00', '12:00'], ['13:00', '17:00']], // Lunch break
            ],
            'timezone' => 'UTC',
        ];

        // Test during lunch break (outside)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 12:30:00'); // Monday lunch
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('outside_hours', $additionalData['type']);

        // Test during working hours (morning)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);

        // Test during working hours (afternoon)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 14:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);
    }

    public function testDetectHandlesTimezone(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'timezone' => 'Europe/Berlin', // CET/CEST
        ];

        // 09:00 Berlin time = within working hours
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 09:00:00', 'Europe/Berlin');
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectHandlesEdgeTimesCorrectly(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'timezone' => 'UTC',
        ];

        // Exactly at start time
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 09:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);

        // Exactly at end time
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 17:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);

        // One minute before start
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 08:59:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // One minute after end
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 17:01:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);
    }

    public function testDetectWithEmptyConfigurationReturnsFalse(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = []; // Empty configuration - should return false

        // Mock current time to be Monday 10:00 UTC
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result); // Should return false when no working hours configured

        // Mock current time to be Saturday
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-04 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result); // Should also return false when no working hours configured
    }

    public function testDetectHandlesInvalidConfiguration(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => 'invalid', // Invalid format
            ],
            'holidays' => 'not-array', // Invalid format
            'vacationPeriods' => [
                'invalid-period', // Invalid format
            ],
        ];

        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        // Should handle gracefully and treat as outside working hours
        self::assertTrue($result);
    }

    public function testHolidayTakesPrecedenceOverWorkingHours(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'holidays' => ['2025-01-06'],
            'timezone' => 'UTC',
        ];

        // Monday would normally be working time, but it's a holiday
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('holiday', $additionalData['type']);
    }

    public function testVacationTakesPrecedenceOverWorkingHours(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'vacationPeriods' => [
                ['2025-01-06', '2025-01-10'],
            ],
            'timezone' => 'UTC',
        ];

        // Monday would normally be working time, but it's vacation
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('vacation', $additionalData['type']);
    }

    public function testDetectReturnsFalseForNonAdminWhenAffectedUsersIsAdmins(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => false]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'affectedUsers' => 'admins',
            'timezone' => 'UTC',
        ];

        // Saturday - would normally trigger, but user is not admin
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-04 10:00:00'); // Saturday
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueForAdminWhenAffectedUsersIsAdmins(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => true]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'affectedUsers' => 'admins',
            'timezone' => 'UTC',
        ];

        // Saturday - should trigger for admin
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-04 10:00:00'); // Saturday
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectReturnsFalseForNonSystemMaintainerWhenAffectedUsersIsMaintainers(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [2, 3];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'affectedUsers' => 'maintainers',
            'timezone' => 'UTC',
        ];

        // Saturday - would normally trigger, but user is not system maintainer
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-04 10:00:00'); // Saturday
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueForSystemMaintainerWhenAffectedUsersIsMaintainers(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [123, 456];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'affectedUsers' => 'maintainers',
            'timezone' => 'UTC',
        ];

        // Saturday - should trigger for system maintainer
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-04 10:00:00'); // Saturday
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    /**
     * @param array<string, mixed> $userData
     */
    private function createMockUser(array $userData): BackendUserAuthentication&MockObject
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = $userData;

        return $user;
    }
}

/**
 * OutOfOfficeDetectorWithMockedTime.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class OutOfOfficeDetectorWithMockedTime extends OutOfOfficeDetector
{
    public function __construct(private string $mockedTime, private string $defaultTimezone = 'UTC') {}

    /**
     * @param array<string, mixed> $configuration
     */
    public function detect(\TYPO3\CMS\Core\Authentication\AbstractUserAuthentication $user, array $configuration = [], ?\Psr\Http\Message\ServerRequestInterface $request = null): bool
    {
        // Check user role filtering
        if (!$this->shouldDetectForUser($user, $configuration)) {
            return false;
        }

        $timezone = $configuration['timezone'] ?? $this->defaultTimezone;
        $currentTime = new DateTime($this->mockedTime, new DateTimeZone($this->defaultTimezone));

        // Convert to the configuration timezone if different
        if ($timezone !== $this->defaultTimezone) {
            $currentTime->setTimezone(new DateTimeZone($timezone));
        }

        // Copy of original detect method with mocked time
        if ($this->isHoliday($currentTime, $configuration)) {
            $this->additionalData = [
                'type' => 'holiday',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
            ];

            return true;
        }

        if ($this->isVacationPeriod($currentTime, $configuration)) {
            $this->additionalData = [
                'type' => 'vacation',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
            ];

            return true;
        }

        $workingHours = $configuration['workingHours'] ?? [];
        if ([] === $workingHours) {
            return false;
        }

        if (!$this->isWithinWorkingHours($currentTime, $workingHours)) {
            $dayOfWeek = strtolower($currentTime->format('l'));
            $this->additionalData = [
                'type' => 'outside_hours',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
                'workingHours' => $workingHours[$dayOfWeek] ?? null,
            ];

            return true;
        }

        return false;
    }
}
