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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Trigger;

use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use MoveElevator\Typo3LoginWarning\Trigger\NewIp;
use MoveElevator\Typo3LoginWarning\Trigger\TriggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * NewIpTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class NewIpTest extends TestCase
{
    private IpLogRepository&MockObject $ipLogRepository;
    private NewIp $subject;

    protected function setUp(): void
    {
        $this->ipLogRepository = $this->createMock(IpLogRepository::class);
        $this->subject = new NewIp($this->ipLogRepository);
    }

    public function testImplementsTriggerInterface(): void
    {
        self::assertInstanceOf(TriggerInterface::class, $this->subject);
    }

    public function testIsTriggeredReturnsFalseWhenIpIsWhitelisted(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'whitelist' => ['192.168.1.1'],
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $result = $this->subject->isTriggered($user, $configuration);

        self::assertFalse($result);
    }

    public function testIsTriggeredReturnsTrueWhenIpIsNew(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => true];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $this->ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $this->ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $result = $this->subject->isTriggered($user, $configuration);

        self::assertTrue($result);
    }

    public function testIsTriggeredReturnsFalseWhenIpExists(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => true];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $this->ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(true);

        $this->ipLogRepository
            ->expects(self::never())
            ->method('addUserIp');

        $result = $this->subject->isTriggered($user, $configuration);

        self::assertFalse($result);
    }

    public function testIsTriggeredWithoutHashingWhenConfigured(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => false];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $this->ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $this->ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $result = $this->subject->isTriggered($user, $configuration);

        self::assertTrue($result);
    }

    public function testIsTriggeredDefaultsToHashingWhenNotConfigured(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $this->ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $this->ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $result = $this->subject->isTriggered($user, $configuration);

        self::assertTrue($result);
    }

    private function createMockUser(array $userData): BackendUserAuthentication&MockObject
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = $userData;
        return $user;
    }
}
