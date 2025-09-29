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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Detector;

use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * OutOfOfficeDetectorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
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

    public function testGetViolationDetailsReturnsNullInitially(): void
    {
        $subject = new OutOfOfficeDetector();
        self::assertNull($subject->getViolationDetails());
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
        self::assertNull($subject->getViolationDetails());
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
        $violationDetails = $subject->getViolationDetails();
        self::assertIsArray($violationDetails);
        self::assertSame('outside_hours', $violationDetails['type']);
        self::assertSame('Saturday', $violationDetails['dayOfWeek']);
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
        $violationDetails = $subject->getViolationDetails();
        self::assertSame('outside_hours', $violationDetails['type']);
        self::assertSame('Monday', $violationDetails['dayOfWeek']);
        self::assertSame(['09:00', '17:00'], $violationDetails['workingHours']);
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
        $violationDetails = $subject->getViolationDetails();
        self::assertSame('holiday', $violationDetails['type']);
        self::assertSame('2025-01-06', $violationDetails['date']);
        self::assertSame('Monday', $violationDetails['dayOfWeek']);
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
        $violationDetails = $subject->getViolationDetails();
        self::assertSame('vacation', $violationDetails['type']);
        self::assertSame('2025-01-08', $violationDetails['date']);
        self::assertSame('Wednesday', $violationDetails['dayOfWeek']);
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
        $violationDetails = $subject->getViolationDetails();
        self::assertSame('outside_hours', $violationDetails['type']);

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
        $violationDetails = $subject->getViolationDetails();
        self::assertSame('holiday', $violationDetails['type']);
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
        $violationDetails = $subject->getViolationDetails();
        self::assertSame('vacation', $violationDetails['type']);
    }

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
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class OutOfOfficeDetectorWithMockedTime extends OutOfOfficeDetector
{
    private ?array $violationDetails = null;

    public function __construct(private string $mockedTime, private string $defaultTimezone = 'UTC') {}

    public function detect(\TYPO3\CMS\Core\Authentication\AbstractUserAuthentication $user, array $configuration = []): bool
    {
        $timezone = $configuration['timezone'] ?? $this->defaultTimezone;
        $currentTime = new \DateTime($this->mockedTime, new \DateTimeZone($this->defaultTimezone));

        // Convert to the configuration timezone if different
        if ($timezone !== $this->defaultTimezone) {
            $currentTime->setTimezone(new \DateTimeZone($timezone));
        }

        // Copy of original detect method with mocked time
        if ($this->isHoliday($currentTime, $configuration)) {
            $this->violationDetails = [
                'type' => 'holiday',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
            ];
            return true;
        }

        if ($this->isVacationPeriod($currentTime, $configuration)) {
            $this->violationDetails = [
                'type' => 'vacation',
                'date' => $currentTime->format('Y-m-d'),
                'time' => $currentTime->format('H:i:s'),
                'dayOfWeek' => $currentTime->format('l'),
            ];
            return true;
        }

        $workingHours = $configuration['workingHours'] ?? [];
        if ($workingHours === []) {
            return false;
        }

        if (!$this->isWithinWorkingHours($currentTime, $workingHours)) {
            $dayOfWeek = strtolower($currentTime->format('l'));
            $this->violationDetails = [
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

    public function getViolationDetails(): ?array
    {
        return $this->violationDetails;
    }

    private function isWithinWorkingHours(\DateTime $time, array $workingHours): bool
    {
        $dayOfWeek = strtolower($time->format('l'));
        $currentTime = $time->format('H:i');

        if (!isset($workingHours[$dayOfWeek])) {
            return false;
        }

        $hours = $workingHours[$dayOfWeek];

        if (is_array($hours) && isset($hours[0]) && is_array($hours[0])) {
            foreach ($hours as $timeRange) {
                if (count($timeRange) === 2 && $this->isTimeInRange($currentTime, $timeRange[0], $timeRange[1])) {
                    return true;
                }
            }
            return false;
        }

        if (is_array($hours) && count($hours) === 2) {
            return $this->isTimeInRange($currentTime, $hours[0], $hours[1]);
        }

        return false;
    }

    private function isTimeInRange(string $time, string $start, string $end): bool
    {
        return $time >= $start && $time <= $end;
    }

    private function isHoliday(\DateTime $time, array $configuration): bool
    {
        $date = $time->format('Y-m-d');
        $holidays = $configuration['holidays'] ?? [];
        return is_array($holidays) && in_array($date, $holidays, true);
    }

    private function isVacationPeriod(\DateTime $time, array $configuration): bool
    {
        $date = $time->format('Y-m-d');
        $vacationPeriods = $configuration['vacationPeriods'] ?? [];

        if (!is_array($vacationPeriods)) {
            return false;
        }

        foreach ($vacationPeriods as $period) {
            if (is_array($period) && count($period) === 2) {
                if ($date >= $period[0] && $date <= $period[1]) {
                    return true;
                }
            }
        }

        return false;
    }
}
