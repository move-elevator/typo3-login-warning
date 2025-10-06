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

        // Expect two separate FluidEmail instances (one for each recipient)
        $fluidEmail1 = $this->createMock(FluidEmail::class);
        $fluidEmail1->expects(self::once())->method('to')->with('admin1@example.com')->willReturnSelf();
        $fluidEmail1->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail1->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail1->expects(self::once())->method('assignMultiple')->willReturnSelf();

        $fluidEmail2 = $this->createMock(FluidEmail::class);
        $fluidEmail2->expects(self::once())->method('to')->with('admin2@example.com')->willReturnSelf();
        $fluidEmail2->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail2->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail2->expects(self::once())->method('assignMultiple')->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail1);
        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail2);

        // Expect two separate send calls
        $this->mailer->expects(self::exactly(2))->method('send');

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

    public function testNotifyUserSetsIsUserNotificationFlag(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123, 'email' => 'user@example.com']);
        $configuration = [
            'recipient' => 'admin@example.com',
            'notificationReceiver' => 'both',
        ];

        // Expect two emails: one for admin, one for user
        $adminEmail = $this->createMock(FluidEmail::class);
        $adminEmail->expects(self::once())->method('to')->with('admin@example.com')->willReturnSelf();
        $adminEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $adminEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $adminEmail->expects(self::once())->method('assignMultiple')->with(
            self::callback(fn(array $vars) => $vars['isUserNotification'] === false)
        )->willReturnSelf();

        $userEmail = $this->createMock(FluidEmail::class);
        $userEmail->expects(self::once())->method('to')->with('user@example.com')->willReturnSelf();
        $userEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $userEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $userEmail->expects(self::once())->method('assignMultiple')->with(
            self::callback(fn(array $vars) => $vars['isUserNotification'] === true)
        )->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $adminEmail);
        GeneralUtility::addInstance(FluidEmail::class, $userEmail);

        $this->mailer->expects(self::exactly(2))->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyWithNotificationReceiverUser(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123, 'email' => 'user@example.com']);
        $configuration = [
            'recipient' => 'admin@example.com',
            'notificationReceiver' => 'user', // Only to user
        ];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->with('user@example.com')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->with(
            self::callback(fn(array $vars) => $vars['isUserNotification'] === true)
        )->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer->expects(self::once())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyWithNotificationReceiverUserWithoutEmail(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]); // No email
        $configuration = [
            'recipient' => 'admin@example.com',
            'notificationReceiver' => 'user',
        ];

        $this->logger
            ->expects(self::once())
            ->method('info')
            ->with(self::stringContains('No recipient configured'));

        $this->mailer->expects(self::never())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyMergesAdditionalValues(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = ['recipient' => 'admin@example.com'];
        $additionalValues = [
            'locationData' => ['city' => 'Berlin'],
            'daysSinceLastLogin' => 365,
        ];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->with(
            self::callback(function (array $vars) use ($additionalValues) {
                return isset($vars['locationData'])
                    && $vars['locationData'] === $additionalValues['locationData']
                    && $vars['daysSinceLastLogin'] === $additionalValues['daysSinceLastLogin'];
            })
        )->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer->expects(self::once())->method('send');

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration, $additionalValues);
    }

    public function testNotifyHandlesTransportException(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = ['recipient' => 'admin@example.com'];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new \Symfony\Component\Mailer\Exception\TransportException('SMTP error'));

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('mailer settings error'),
                self::callback(
                    fn(array $context) =>
                    $context['recipient'] === 'admin@example.com'
                    && $context['userId'] === 123
                )
            );

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyHandlesRfcComplianceException(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = ['recipient' => 'invalid-email'];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new \Symfony\Component\Mime\Exception\RfcComplianceException('Invalid email'));

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                self::stringContains('invalid email address'),
                self::callback(
                    fn(array $context) =>
                    $context['recipient'] === 'invalid-email'
                )
            );

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    public function testNotifyHandlesGenericException(): void
    {
        $user = $this->createMockBackendUser(['uid' => 123]);
        $configuration = ['recipient' => 'admin@example.com'];

        $fluidEmail = $this->createMock(FluidEmail::class);
        $fluidEmail->expects(self::once())->method('to')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setRequest')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('setTemplate')->willReturnSelf();
        $fluidEmail->expects(self::once())->method('assignMultiple')->willReturnSelf();

        GeneralUtility::addInstance(FluidEmail::class, $fluidEmail);

        $this->mailer
            ->expects(self::once())
            ->method('send')
            ->willThrowException(new \RuntimeException('Unexpected error'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('PHP exception'),
                self::callback(
                    fn(array $context) =>
                    isset($context['exception']) && $context['exception'] instanceof \RuntimeException
                )
            );

        $this->subject->notify($user, $this->request, 'TestTrigger', $configuration);
    }

    /**
     * @param array<string, mixed> $userData
     */
    private function createMockBackendUser(array $userData, bool $isAdmin = false): BackendUserAuthentication&MockObject
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = $userData;
        $user->method('isAdmin')->willReturn($isAdmin);
        return $user;
    }
}
