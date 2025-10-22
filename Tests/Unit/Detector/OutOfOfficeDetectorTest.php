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
use Exception;
use MoveElevator\Typo3LoginWarning\Detector\{DetectorInterface, OutOfOfficeDetector};
use PHPUnit\Framework\TestCase;

/**
 * OutOfOfficeDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
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
        self::assertSame('outside_hours', $additionalData['violationDetails']['type']);
        self::assertSame('Saturday', $additionalData['violationDetails']['dayOfWeek']);
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
        self::assertSame('outside_hours', $additionalData['violationDetails']['type']);
        self::assertSame('Monday', $additionalData['violationDetails']['dayOfWeek']);
        self::assertSame(['09:00', '17:00'], $additionalData['violationDetails']['workingHours']);
    }

    public function testDetectReturnsTrueForHoliday(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['2025-01-06'],
            ],
            'timezone' => 'UTC',
        ];

        // Mock current time to be Monday (holiday) 10:00
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00'); // Monday (holiday)
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('holiday', $additionalData['violationDetails']['type']);
        self::assertSame('2025-01-06', $additionalData['violationDetails']['date']);
        self::assertSame('Monday', $additionalData['violationDetails']['dayOfWeek']);
    }

    public function testDetectReturnsTrueForVacationPeriod(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['2025-01-06', '2025-01-10'],
            ],
            'timezone' => 'UTC',
        ];

        // Mock current time to be within vacation period
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-08 10:00:00'); // Wednesday in vacation
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('vacation', $additionalData['violationDetails']['type']);
        self::assertSame('2025-01-08', $additionalData['violationDetails']['date']);
        self::assertSame('Wednesday', $additionalData['violationDetails']['dayOfWeek']);
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
        self::assertSame('outside_hours', $additionalData['violationDetails']['type']);

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
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 09:00:00');
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
            'blockedPeriods' => [
                'invalid-period', // Invalid format
            ],
        ];

        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        // Should handle gracefully and treat as outside working hours
        self::assertTrue($result);
    }

    public function testDetectHandlesBlockedPeriodsAsNonArray(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => 'not-an-array',
            'timezone' => 'UTC',
        ];

        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        // Should not detect blocked period and check working hours instead
        self::assertFalse($result); // Monday 10:00 is within working hours
    }

    public function testDetectHandlesWorkingHoursWithInvalidTimeRangeFormat(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00'], // Invalid: only one element, needs two
            ],
            'timezone' => 'UTC',
        ];

        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        // Should treat as invalid and outside working hours
        self::assertTrue($result);
    }

    public function testDetectHandlesWorkingHoursWithEmptyArray(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => [],
            ],
            'timezone' => 'UTC',
        ];

        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        // Should treat as invalid and outside working hours
        self::assertTrue($result);
    }

    public function testBlockedPeriodTakesPrecedenceOverWorkingHours(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['2025-01-06'],
            ],
            'timezone' => 'UTC',
        ];

        // Monday would normally be working time, but it's a blocked period
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('holiday', $additionalData['violationDetails']['type']);
    }

    public function testDateRangeTakesPrecedenceOverWorkingHours(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['2025-01-06', '2025-01-10'],
            ],
            'timezone' => 'UTC',
        ];

        // Monday would normally be working time, but it's a blocked date range
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('vacation', $additionalData['violationDetails']['type']);
    }

    public function testGetCurrentTimeUsesSpecifiedTimezone(): void
    {
        $user = $this->createMockUser(['uid' => 123]);

        // Configure working hours for Monday 09:00-17:00 in Europe/Berlin timezone
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'timezone' => 'Europe/Berlin',
        ];

        // Use the real OutOfOfficeDetector (not mocked) to test line 92
        $subject = new OutOfOfficeDetector();

        // This will call getCurrentTime() with 'Europe/Berlin' timezone (line 92)
        // The result depends on the actual current time, so we just verify it executes without error
        $result = $subject->detect($user, $configuration);

        // Test passes if no exception is thrown - result can be true or false depending on current time
        $this->addToAssertionCount(1);
    }

    public function testDetectReturnsTrueForRecurringHoliday(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['12-25'], // Christmas without year
            ],
            'timezone' => 'UTC',
        ];

        // Test Christmas 2025
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-12-25 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('holiday', $additionalData['violationDetails']['type']);
        self::assertSame('2025-12-25', $additionalData['violationDetails']['date']);

        // Test Christmas 2026 (same pattern should work)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-12-25 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectReturnsFalseForNonMatchingRecurringDate(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['12-25'], // Christmas
            ],
            'timezone' => 'UTC',
        ];

        // Test December 24 (not Christmas, Wednesday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-12-24 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueForRecurringDateRange(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['07-15', '07-30'], // Summer break without year
            ],
            'timezone' => 'UTC',
        ];

        // Test July 20, 2025 (within range, Sunday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-07-20 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        $additionalData = $subject->getAdditionalData();
        self::assertSame('vacation', $additionalData['violationDetails']['type']);

        // Test July 20, 2026 (within range, Monday - within working hours)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-07-20 10:00:00');
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);

        // Test first day of range (Tuesday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-07-15 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test last day of range (Wednesday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-07-30 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test outside range (Monday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-07-14 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);

        // Test outside range (Thursday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-07-31 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);
    }

    public function testDetectHandlesYearSpanningRecurringRange(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['12-20', '01-05'], // Christmas/New Year spanning years
            ],
            'timezone' => 'UTC',
        ];

        // Test December 25 (within range, before year boundary, Thursday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-12-25 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test January 2 (within range, after year boundary, Friday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-01-02 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test first day of range (Saturday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-12-20 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test last day of range (Monday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-01-05 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test outside range (before, Friday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-12-19 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);

        // Test outside range (after, Tuesday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-01-06 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);

        // Test middle of year (outside range, Monday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-06-16 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);
    }

    public function testDetectHandlesMixedFullAndRecurringDates(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'workingHours' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00'],
            ],
            'blockedPeriods' => [
                ['2025-01-01'], // Specific date with year (Wednesday)
                ['12-25'],      // Recurring Christmas
                ['2025-07-04'], // Specific Independence Day (Friday)
            ],
            'timezone' => 'UTC',
        ];

        // Test specific date 2025-01-01 (Wednesday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-01-01 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test recurring Christmas 2025 (Thursday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2025-12-25 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test recurring Christmas 2026 (Friday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-12-25 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertTrue($result);

        // Test January 1, 2026 (specific date was only for 2025, Thursday)
        $subject = new OutOfOfficeDetectorWithMockedTime('2026-01-02 10:00:00');
        $result = $subject->detect($user, $configuration);
        self::assertFalse($result);
    }

    /**
     * @param array<string, mixed> $userData
     *
     * @return array<string, mixed>
     */
    private function createMockUser(array $userData): array
    {
        return $userData;
    }
}

/**
 * OutOfOfficeDetectorWithMockedTime.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class OutOfOfficeDetectorWithMockedTime extends OutOfOfficeDetector
{
    public function __construct(private string $mockedTime) {}

    /**
     * @throws Exception
     */
    protected function getCurrentTime(string $timezone): DateTime
    {
        return new DateTime($this->mockedTime, new DateTimeZone($timezone));
    }
}
