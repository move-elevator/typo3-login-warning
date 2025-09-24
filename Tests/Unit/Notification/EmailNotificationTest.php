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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Notification;

use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;
use MoveElevator\Typo3LoginWarning\Notification\NotifierInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EmailNotificationTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class EmailNotificationTest extends TestCase
{
    private MailerInterface&MockObject $mailer;
    private LoggerInterface&MockObject $logger;
    private ServerRequestInterface&MockObject $request;
    private EmailNotification $subject;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->request = $this->createMock(ServerRequestInterface::class);

        $this->subject = new EmailNotification($this->mailer);
        $this->subject->setLogger($this->logger);

        // Initialize TYPO3_CONF_VARS to prevent warnings
        $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] = '';
    }

    public function testImplementsNotifierInterface(): void
    {
        self::assertInstanceOf(NotifierInterface::class, $this->subject);
    }

    public function testNotifyDoesNothingForNonBackendUsers(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->method('isAdmin')->willReturn(false);

        $this->mailer->expects(self::never())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', []);
    }

    public function testNotifyLogsInfoWhenNoRecipientConfigured(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = [];

        $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] = '';

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(self::stringContains('No recipient configured'));

        $this->mailer->expects(self::never())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyUsesConfiguredRecipient(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123, 'lang' => 'en']);
        $configuration = ['recipient' => 'admin@example.com'];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->with('admin@example.com')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->with($this->request)->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->with('LoginNotification/TestTrigger')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->with(self::callback(function (array $variables) {
            return isset($variables['user'], $variables['prefix'], $variables['language'], $variables['headline']);
        }))->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer->expects(self::once())->method('send')->with($fluidEmail);

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyFallsBackToGlobalConfiguration(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = [];

        $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'] = 'global@example.com';

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->with('global@example.com')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer->expects(self::once())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyHandlesMultipleRecipients(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = ['recipient' => 'admin1@example.com,admin2@example.com'];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->with('admin1@example.com', 'admin2@example.com')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer->expects(self::once())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyAddsAdminPrefixForAdminUsers(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123], true);
        $configuration = ['recipient' => 'admin@example.com'];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->with(
            self::callback(fn(array $vars) => $vars['prefix'] === '[AdminLoginWarning]')
        )->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer->expects(self::once())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    private function createMockBackendUser(array $userData, bool $isAdmin = false): BackendUserAuthentication&MockObject
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = $userData;
        $user->method('isAdmin')->willReturn($isAdmin);
        return $user;
    }
}
