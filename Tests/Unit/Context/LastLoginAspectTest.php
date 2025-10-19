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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Context;

use DateTime;
use MoveElevator\Typo3LoginWarning\Context\LastLoginAspect;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;

/**
 * LastLoginAspectTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class LastLoginAspectTest extends TestCase
{
    public function testImplementsAspectInterface(): void
    {
        $lastLogin = new DateTime();
        $subject = new LastLoginAspect($lastLogin);

        self::assertInstanceOf(AspectInterface::class, $subject);
    }

    public function testGetReturnsLastLoginForLastLoginProperty(): void
    {
        $lastLogin = new DateTime('2025-01-15 10:30:00');
        $subject = new LastLoginAspect($lastLogin);

        $result = $subject->get('last_login');

        self::assertSame($lastLogin, $result);
    }

    public function testGetThrowsExceptionForUnknownProperty(): void
    {
        $lastLogin = new DateTime();
        $subject = new LastLoginAspect($lastLogin);

        $this->expectException(AspectPropertyNotFoundException::class);
        $this->expectExceptionMessage('Property "unknown" not found in Aspect');
        $this->expectExceptionCode(1735135381);

        $subject->get('unknown');
    }

    public function testGetThrowsExceptionForEmptyProperty(): void
    {
        $lastLogin = new DateTime();
        $subject = new LastLoginAspect($lastLogin);

        $this->expectException(AspectPropertyNotFoundException::class);

        $subject->get('');
    }

    public function testConstructorAcceptsDateTime(): void
    {
        $lastLogin = new DateTime('2024-12-01 14:25:30');
        $subject = new LastLoginAspect($lastLogin);

        $result = $subject->get('last_login');

        self::assertInstanceOf(DateTime::class, $result);
        self::assertSame('2024-12-01 14:25:30', $result->format('Y-m-d H:i:s'));
    }

    public function testMultipleCallsToGetReturnSameInstance(): void
    {
        $lastLogin = new DateTime();
        $subject = new LastLoginAspect($lastLogin);

        $result1 = $subject->get('last_login');
        $result2 = $subject->get('last_login');

        self::assertSame($result1, $result2);
    }
}
