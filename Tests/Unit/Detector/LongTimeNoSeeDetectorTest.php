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
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Domain\Repository\UserLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * LongTimeNoSeeDetectorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class LongTimeNoSeeDetectorTest extends TestCase
{
    private UserLogRepository&MockObject $userLogRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userLogRepository = $this->createMock(UserLogRepository::class);
    }

    public function testImplementsDetectorInterface(): void
    {
        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        self::assertInstanceOf(DetectorInterface::class, $subject);
    }

    public function testDetectReturnsTrueForFirstTimeLogin(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn(null);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectReturnsTrueWhenLastLoginExceedsDefaultThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $oneYearAgo = time() - (366 * 24 * 60 * 60); // 366 days ago
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($oneYearAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectReturnsFalseWhenLastLoginWithinDefaultThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $sixMonthsAgo = time() - (180 * 24 * 60 * 60); // 180 days ago
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($sixMonthsAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueWhenLastLoginExceedsCustomThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $thirtyOneDaysAgo = time() - (31 * 24 * 60 * 60); // 31 days ago
        $configuration = ['thresholdDays' => 30]; // 30 days threshold

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($thirtyOneDaysAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectReturnsFalseWhenLastLoginWithinCustomThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $twentyNineDaysAgo = time() - (29 * 24 * 60 * 60); // 29 days ago
        $configuration = ['thresholdDays' => 30]; // 30 days threshold

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($twentyNineDaysAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectHandlesZeroThresholdDays(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $oneSecondAgo = time() - 1;
        $configuration = ['thresholdDays' => 0];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($oneSecondAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectHandlesStringThresholdDaysConfiguration(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $thirtyOneDaysAgo = time() - (31 * 24 * 60 * 60);
        $configuration = ['thresholdDays' => '30']; // String instead of int

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($thirtyOneDaysAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectHandlesInvalidThresholdDaysConfiguration(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $oneYearAgo = time() - (366 * 24 * 60 * 60);
        $configuration = ['thresholdDays' => 'invalid']; // Invalid value should default to 365

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($oneYearAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectEdgeCaseExactlyAtThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $exactlyThirtyDaysAgo = time() - (30 * 24 * 60 * 60);
        $configuration = ['thresholdDays' => 30];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($exactlyThirtyDaysAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectWithLargeThresholdValue(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $fiveYearsAgo = time() - (5 * 365 * 24 * 60 * 60);
        $configuration = ['thresholdDays' => 10 * 365]; // 10 years

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($fiveYearsAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::anything());

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectAlwaysUpdatesTimestamp(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $currentTime = time();
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn(null);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp')
            ->with(123, self::greaterThanOrEqual($currentTime));

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $subject->detect($user, $configuration);
    }

    public function testGetDaysSinceLastLoginReturnsNullInitially(): void
    {
        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        self::assertNull($subject->getDaysSinceLastLogin());
    }

    public function testGetDaysSinceLastLoginReturnsNullForFirstTimeLogin(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn(null);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp');

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $subject->detect($user, $configuration);

        self::assertNull($subject->getDaysSinceLastLogin());
    }

    public function testGetDaysSinceLastLoginCalculatesCorrectDays(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $tenDaysAgo = time() - (10 * 24 * 60 * 60);
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($tenDaysAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp');

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $subject->detect($user, $configuration);

        self::assertSame(10, $subject->getDaysSinceLastLogin());
    }

    public function testGetDaysSinceLastLoginHandlesPartialDays(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $oneAndAHalfDaysAgo = time() - (36 * 60 * 60); // 1.5 days = 36 hours
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($oneAndAHalfDaysAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp');

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $subject->detect($user, $configuration);

        // Should floor to 1 day
        self::assertSame(1, $subject->getDaysSinceLastLogin());
    }

    public function testGetDaysSinceLastLoginForLongPeriods(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $twoYearsAgo = time() - (2 * 365 * 24 * 60 * 60);
        $configuration = [];

        $this->userLogRepository
            ->expects(self::once())
            ->method('getLastLoginCheckTimestamp')
            ->with(123)
            ->willReturn($twoYearsAgo);

        $this->userLogRepository
            ->expects(self::once())
            ->method('updateLastLoginCheckTimestamp');

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $subject->detect($user, $configuration);

        // Should be approximately 730 days (2 years)
        $days = $subject->getDaysSinceLastLogin();
        self::assertGreaterThan(720, $days);
        self::assertLessThan(740, $days);
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
