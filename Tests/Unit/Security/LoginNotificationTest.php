<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typo3_login_warning".
 *
 * Copyright (C) 2025 Konrad Michalik <hej@konradmichalik.dev>
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
use MoveElevator\Typo3LoginWarning\Notification\NotifierInterface;
use MoveElevator\Typo3LoginWarning\Security\LoginNotification;
use MoveElevator\Typo3LoginWarning\Trigger\TriggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class LoginNotificationTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private LoginNotification $subject;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->subject = new LoginNotification();
        $this->subject->setLogger($this->logger);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'] = [];
    }

    public function testEmailAtLoginDoesNothingForNonBackendUsers(): void
    {
        $user = $this->createMock(\TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Should complete without errors for non-backend users
        $this->subject->emailAtLogin($event);
        $this->addToAssertionCount(1);
    }

    public function testEmailAtLoginHandlesNoConfiguredTriggers(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [];

        $this->subject->emailAtLogin($event);

        // Should complete without errors when no triggers are configured
        $this->addToAssertionCount(1);
    }

    public function testEmailAtLoginLogsWarningForInvalidTrigger(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $invalidTrigger = new class () {};
        GeneralUtility::addInstance(get_class($invalidTrigger), $invalidTrigger);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [
            get_class($invalidTrigger) => [],
        ];

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('does not implement'),
                self::callback(fn(array $context) => array_key_exists('class', $context))
            );

        $this->subject->emailAtLogin($event);
    }

    public function testEmailAtLoginProcessesValidTriggerThatIsNotTriggered(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $trigger = $this->createMock(TriggerInterface::class);
        $trigger->expects(self::once())->method('isTriggered')->willReturn(false);

        GeneralUtility::addInstance(TriggerInterface::class, $trigger);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [
            TriggerInterface::class => [],
        ];

        $this->subject->emailAtLogin($event);

        // Should complete without sending notifications
        $this->addToAssertionCount(1);
    }

    public function testEmailAtLoginProcessesValidTriggeredTriggerWithNotification(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $trigger = $this->createMock(TriggerInterface::class);
        $trigger->expects(self::once())->method('isTriggered')->willReturn(true);

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects(self::once())->method('notify')->with($user, $request, TriggerInterface::class, []);

        GeneralUtility::addInstance(TriggerInterface::class, $trigger);
        GeneralUtility::addInstance(NotifierInterface::class, $notifier);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [
            TriggerInterface::class => [],
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'] = [
            NotifierInterface::class => [],
        ];

        $this->subject->emailAtLogin($event);
    }

    public function testEmailAtLoginLogsWarningForInvalidNotifier(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $trigger = $this->createMock(TriggerInterface::class);
        $trigger->expects(self::once())->method('isTriggered')->willReturn(true);

        $invalidNotifier = new class () {};

        GeneralUtility::addInstance(TriggerInterface::class, $trigger);
        GeneralUtility::addInstance(get_class($invalidNotifier), $invalidNotifier);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [
            TriggerInterface::class => [],
        ];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'] = [
            get_class($invalidNotifier) => [],
        ];

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('does not implement'),
                self::callback(fn(array $context) => array_key_exists('class', $context))
            );

        $this->subject->emailAtLogin($event);
    }

    public function testEmailAtLoginHandlesTriggerWithCustomNotificationConfiguration(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $trigger = $this->createMock(TriggerInterface::class);
        $trigger->expects(self::once())->method('isTriggered')->willReturn(true);

        $notifier = $this->createMock(NotifierInterface::class);
        $customNotificationConfig = ['recipient' => 'custom@example.com'];
        $notifier->expects(self::once())->method('notify')->with(
            $user,
            $request,
            TriggerInterface::class,
            $customNotificationConfig
        );

        GeneralUtility::addInstance(TriggerInterface::class, $trigger);
        GeneralUtility::addInstance(NotifierInterface::class, $notifier);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] = [
            TriggerInterface::class => [
                'notification' => [
                    NotifierInterface::class => $customNotificationConfig,
                ],
            ],
        ];

        $this->subject->emailAtLogin($event);
    }
}
