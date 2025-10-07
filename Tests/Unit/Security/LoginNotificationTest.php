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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Security;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Registry\DetectorRegistry;
use MoveElevator\Typo3LoginWarning\Registry\NotificationRegistry;
use MoveElevator\Typo3LoginWarning\Security\LoginNotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;

/**
 * LoginNotificationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
final class LoginNotificationTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private DetectorRegistry $detectorRegistry;
    private DetectorConfigurationBuilder&MockObject $configBuilder;
    private NotificationRegistry $notificationRegistry;
    private LoginNotification $subject;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configBuilder = $this->createMock(DetectorConfigurationBuilder::class);

        // Use real registries with empty lists
        $this->detectorRegistry = new DetectorRegistry([]);
        $this->notificationRegistry = new NotificationRegistry([]);

        $this->subject = new LoginNotification(
            $this->detectorRegistry,
            $this->configBuilder,
            $this->notificationRegistry
        );
        $this->subject->setLogger($this->logger);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_detector'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);
    }

    public function testWarningAtLoginDoesNothingForNonBackendUsers(): void
    {
        $user = $this->createMock(\TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Should complete without errors for non-backend users
        ($this->subject)($event);
        $this->addToAssertionCount(1);
    }

    public function testWarningAtLoginHandlesNoActiveDetectors(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = [
            'newIp' => ['active' => false],
            'longTimeNoSee' => ['active' => false],
            'outOfOffice' => ['active' => false],
        ];

        ($this->subject)($event);

        // Should complete without errors when no detectors are active
        $this->addToAssertionCount(1);
    }

    public function testNotifiesWhenDetectorTriggered(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123, 'username' => 'testuser'];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Create mock detector that will trigger
        $mockDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $mockDetector->expects(self::once())
            ->method('shouldDetectForUser')
            ->willReturn(true);
        $mockDetector->expects(self::once())
            ->method('detect')
            ->willReturn(true);
        $mockDetector->expects(self::once())
            ->method('getAdditionalData')
            ->willReturn([]);

        // Create mock notifier
        $mockNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $mockNotifier->expects(self::once())
            ->method('notify');

        // Use registries with our mocks
        $this->detectorRegistry = new DetectorRegistry([$mockDetector]);
        $this->notificationRegistry = new NotificationRegistry([$mockNotifier]);
        $this->subject = new LoginNotification(
            $this->detectorRegistry,
            $this->configBuilder,
            $this->notificationRegistry
        );
        $this->subject->setLogger($this->logger);

        // Configure mocks
        $this->configBuilder->expects(self::once())
            ->method('setLogger')
            ->with($this->logger);

        $this->configBuilder->expects(self::once())
            ->method('buildNotificationConfig')
            ->willReturn(['recipients' => 'test@example.com']);

        $this->configBuilder->expects(self::once())
            ->method('isActive')
            ->with(get_class($mockDetector))
            ->willReturn(true);

        $this->configBuilder->expects(self::once())
            ->method('build')
            ->with(get_class($mockDetector))
            ->willReturn(['notificationReceiver' => 'recipients']);

        ($this->subject)($event);
    }
}
