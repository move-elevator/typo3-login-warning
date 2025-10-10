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

use MoveElevator\Typo3LoginWarning\Detector\{DetectorInterface, LongTimeNoSeeDetector};
use MoveElevator\Typo3LoginWarning\Domain\Repository\UserLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * LongTimeNoSeeDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
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

    public function testDetectReturnsFalseForFirstTimeLogin(): void
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

        // First time login should NOT trigger (no history to compare against)
        self::assertFalse($result);
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

    public function testGetAdditionalDataReturnsEmptyArrayInitially(): void
    {
        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataReturnsEmptyArrayForFirstTimeLogin(): void
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

        self::assertSame([], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataCalculatesCorrectDays(): void
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

        self::assertSame(['daysSinceLastLogin' => 10], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataHandlesPartialDays(): void
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
        self::assertSame(['daysSinceLastLogin' => 1], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataForLongPeriods(): void
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
        $additionalData = $subject->getAdditionalData();
        self::assertArrayHasKey('daysSinceLastLogin', $additionalData);
        self::assertGreaterThan(720, $additionalData['daysSinceLastLogin']);
        self::assertLessThan(740, $additionalData['daysSinceLastLogin']);
    }

    public function testShouldDetectForUserReturnsFalseForNonAdmin(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => false]);
        $configuration = ['affectedUsers' => 'admins'];

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertFalse($result);
    }

    public function testShouldDetectForUserReturnsTrueForAdmin(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => true]);
        $configuration = ['affectedUsers' => 'admins'];

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertTrue($result);
    }

    public function testShouldDetectForUserReturnsFalseForNonMaintainer(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [2, 3];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['affectedUsers' => 'maintainers'];

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertFalse($result);

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers']);
    }

    public function testShouldDetectForUserReturnsTrueForMaintainer(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [123, 456];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['affectedUsers' => 'maintainers'];

        $subject = new LongTimeNoSeeDetector($this->userLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertTrue($result);

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers']);
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
