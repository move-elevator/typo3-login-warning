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
use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Detector\{DetectorInterface, LongTimeNoSeeDetector};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Context\Context;

/**
 * LongTimeNoSeeDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class LongTimeNoSeeDetectorTest extends TestCase
{
    public function testImplementsDetectorInterface(): void
    {
        $context = $this->createMock(Context::class);
        $subject = new LongTimeNoSeeDetector($context);
        self::assertInstanceOf(DetectorInterface::class, $subject);
    }

    public function testDetectReturnsFalseWhenNoAspectAvailable(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(false);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsFalseWhenLastLoginIsNull(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn(null);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueWhenLastLoginExceedsDefaultThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];
        $lastLogin = new DateTime('-366 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        self::assertGreaterThanOrEqual(366, $subject->getAdditionalData()['daysSinceLastLogin']);
    }

    public function testDetectReturnsFalseWhenLastLoginWithinDefaultThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];
        $lastLogin = new DateTime('-180 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueWhenLastLoginExceedsCustomThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 30];
        $lastLogin = new DateTime('-31 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        self::assertGreaterThanOrEqual(31, $subject->getAdditionalData()['daysSinceLastLogin']);
    }

    public function testDetectReturnsFalseWhenLastLoginWithinCustomThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 30];
        $lastLogin = new DateTime('-29 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectHandlesZeroThresholdDays(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 0];
        $lastLogin = new DateTime('-1 second');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result); // 1 second is 0 days, not > 0
    }

    public function testDetectHandlesStringThresholdDaysConfiguration(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => '30'];
        $lastLogin = new DateTime('-31 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectHandlesInvalidThresholdDaysConfiguration(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 'invalid'];
        $lastLogin = new DateTime('-366 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        // Invalid value casts to 0, so 366 days > 0 = true
        self::assertTrue($result);
    }

    public function testDetectEdgeCaseExactlyAtThreshold(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 30];
        $lastLogin = new DateTime('-30 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        // 30 days is not > 30, so should be false
        self::assertFalse($result);
    }

    public function testDetectWithLargeThresholdValue(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 10 * 365];
        $lastLogin = new DateTime('-5 years');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testGetAdditionalDataReturnsEmptyArrayInitially(): void
    {
        $context = $this->createMock(Context::class);
        $subject = new LongTimeNoSeeDetector($context);
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataReturnsEmptyArrayWhenNoDetection(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(false);

        $subject = new LongTimeNoSeeDetector($context);
        $subject->detect($user, $configuration);

        self::assertSame([], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataCalculatesCorrectDays(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 5];
        $lastLogin = new DateTime('-10 days');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $subject->detect($user, $configuration);

        $additionalData = $subject->getAdditionalData();
        self::assertArrayHasKey('daysSinceLastLogin', $additionalData);
        self::assertGreaterThanOrEqual(10, $additionalData['daysSinceLastLogin']);
        self::assertLessThanOrEqual(11, $additionalData['daysSinceLastLogin']);
    }

    public function testGetAdditionalDataForLongPeriods(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['thresholdDays' => 365];
        $lastLogin = new DateTime('-2 years');

        $context = $this->createMock(Context::class);
        $context->expects(self::once())
            ->method('hasAspect')
            ->with(Configuration::EXT_KEY)
            ->willReturn(true);

        $context->expects(self::once())
            ->method('getPropertyFromAspect')
            ->with(Configuration::EXT_KEY, 'last_login')
            ->willReturn($lastLogin);

        $subject = new LongTimeNoSeeDetector($context);
        $subject->detect($user, $configuration);

        $additionalData = $subject->getAdditionalData();
        self::assertArrayHasKey('daysSinceLastLogin', $additionalData);
        self::assertGreaterThan(720, $additionalData['daysSinceLastLogin']);
        self::assertLessThan(740, $additionalData['daysSinceLastLogin']);
    }

    public function testShouldDetectForUserReturnsFalseForNonAdmin(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => false]);
        $configuration = ['affectedUsers' => 'admins'];

        $context = $this->createMock(Context::class);
        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertFalse($result);
    }

    public function testShouldDetectForUserReturnsTrueForAdmin(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => true]);
        $configuration = ['affectedUsers' => 'admins'];

        $context = $this->createMock(Context::class);
        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertTrue($result);
    }

    public function testShouldDetectForUserReturnsFalseForNonMaintainer(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [2, 3];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['affectedUsers' => 'maintainers'];

        $context = $this->createMock(Context::class);
        $subject = new LongTimeNoSeeDetector($context);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertFalse($result);

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers']);
    }

    public function testShouldDetectForUserReturnsTrueForMaintainer(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [123, 456];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['affectedUsers' => 'maintainers'];

        $context = $this->createMock(Context::class);
        $subject = new LongTimeNoSeeDetector($context);
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
