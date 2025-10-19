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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Registry;

use MoveElevator\Typo3LoginWarning\Notification\NotifierInterface;
use MoveElevator\Typo3LoginWarning\Registry\NotificationRegistry;
use PHPUnit\Framework\TestCase;

/**
 * NotificationRegistryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class NotificationRegistryTest extends TestCase
{
    public function testGetNotifiersReturnsEmptyIterableWhenNoNotifiersRegistered(): void
    {
        $registry = new NotificationRegistry([]);

        $notifiers = $registry->getNotifiers();

        self::assertCount(0, $notifiers);
    }

    public function testGetNotifiersReturnsSingleNotifier(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $registry = new NotificationRegistry([$notifier]);

        $notifiers = $registry->getNotifiers();

        self::assertCount(1, $notifiers);
        self::assertSame($notifier, iterator_to_array($notifiers)[0]);
    }

    public function testGetNotifiersReturnsMultipleNotifiersInOrder(): void
    {
        $notifier1 = $this->createMock(NotifierInterface::class);
        $notifier2 = $this->createMock(NotifierInterface::class);
        $notifier3 = $this->createMock(NotifierInterface::class);

        $registry = new NotificationRegistry([$notifier1, $notifier2, $notifier3]);

        $notifiers = iterator_to_array($registry->getNotifiers());

        self::assertCount(3, $notifiers);
        self::assertSame($notifier1, $notifiers[0]);
        self::assertSame($notifier2, $notifiers[1]);
        self::assertSame($notifier3, $notifiers[2]);
    }

    public function testGetNotifiersReturnsIteratorThatCanBeIteratedMultipleTimes(): void
    {
        $notifier = $this->createMock(NotifierInterface::class);
        $registry = new NotificationRegistry([$notifier]);

        $notifiers1 = $registry->getNotifiers();
        $notifiers2 = $registry->getNotifiers();

        self::assertCount(1, $notifiers1);
        self::assertCount(1, $notifiers2);
    }
}
