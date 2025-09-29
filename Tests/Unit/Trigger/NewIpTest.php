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
use MoveElevator\Typo3LoginWarning\Service\IpApiGeolocationService;
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
    protected function setUp(): void
    {
        parent::setUp();
        // Clean slate for each test
        unset($GLOBALS['_SERVER']['REMOTE_ADDR']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['_SERVER']['REMOTE_ADDR']);
    }

    public function testImplementsTriggerInterface(): void
    {
        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIp($ipLogRepository);
        self::assertInstanceOf(TriggerInterface::class, $subject);
    }

    public function testIsTriggeredReturnsFalseWhenIpIsWhitelisted(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'whitelist' => ['192.168.1.1'],
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIp($ipLogRepository);
        $result = $subject->isTriggered($user, $configuration);

        self::assertFalse($result);
    }

    public function testIsTriggeredReturnsTrueWhenIpIsNew(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => true];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $subject = new NewIp($ipLogRepository);
        $result = $subject->isTriggered($user, $configuration);

        self::assertTrue($result);
    }

    public function testIsTriggeredReturnsFalseWhenIpExists(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => true];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(true);

        $ipLogRepository
            ->expects(self::never())
            ->method('addUserIp');

        $subject = new NewIp($ipLogRepository);
        $result = $subject->isTriggered($user, $configuration);

        self::assertFalse($result);
    }

    public function testIsTriggeredWithoutHashingWhenConfigured(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => false];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $subject = new NewIp($ipLogRepository);
        $result = $subject->isTriggered($user, $configuration);

        self::assertTrue($result);
    }

    public function testIsTriggeredDefaultsToHashingWhenNotConfigured(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $subject = new NewIp($ipLogRepository);
        $result = $subject->isTriggered($user, $configuration);

        self::assertTrue($result);
    }

    public function testIsTriggeredDoesNotFetchGeolocationWhenDisabled(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'hashIpAddress' => true,
            'fetchGeolocation' => false,
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $geolocationService = $this->createMock(IpApiGeolocationService::class);

        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);

        $geolocationService
            ->expects(self::never())
            ->method('getLocationData');

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIp($ipLogRepository, $geolocationService);
        $result = $subject->isTriggered($user, $configuration);

        self::assertTrue($result);
        self::assertNull($subject->getLocationData());
    }

    public function testGetLocationDataReturnsNullInitially(): void
    {
        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIp($ipLogRepository);
        self::assertNull($subject->getLocationData());
    }

    public function testIsTriggeredDoesNotFetchGeolocationForPrivateIps(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'hashIpAddress' => true,
            'fetchGeolocation' => true,
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $geolocationService = $this->createMock(IpApiGeolocationService::class);

        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);

        $geolocationService
            ->expects(self::never())
            ->method('getLocationData');

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIp($ipLogRepository, $geolocationService);
        $result = $subject->isTriggered($user, $configuration);

        self::assertTrue($result);
        self::assertNull($subject->getLocationData());
    }

    private function createMockUser(array $userData): BackendUserAuthentication&MockObject
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = $userData;
        return $user;
    }
}
