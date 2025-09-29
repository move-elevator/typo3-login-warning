<?php

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

namespace MoveElevator\Typo3LoginWarning\Notification;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\MailerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * EmailNotification.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class EmailNotification implements NotifierInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function notify(AbstractUserAuthentication $user, ServerRequestInterface $request, string $triggerClass, array $configuration = [], array $additionalValues = []): void
    {
        if (!$user instanceof BackendUserAuthentication) {
            return;
        }

        $headline = 'TYPO3 Backend Login notification';
        $recipients = array_key_exists('recipient', $configuration) ? $configuration['recipient'] : '';

        if ($recipients === '') {
            // Fallback to global configuration
            $recipients = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
        }

        if ($recipients === '' || $recipients === null) {
            $this->logger->info('No recipient configured for login notification email. Please set $GLOBALS[\'TYPO3_CONF_VARS\'][\'BE\'][\'warning_email_addr\'] or configure the recipient via Typo3LoginWarning configuration.');
            return;
        }

        $recipients = explode(',', $recipients);
        $values = [
            'user' => $user->user,
            'prefix' => $user->isAdmin() ? '[AdminLoginWarning]' : '[LoginWarning]',
            'language' => $user->user['lang'] ?? 'default',
            'headline' => $headline,
        ];

        if ($additionalValues !== []) {
            $values = array_merge($values, $additionalValues);
        }

        $email = GeneralUtility::makeInstance(FluidEmail::class)
            ->to(...$recipients)
            ->setRequest($request)
            ->setTemplate(sprintf('LoginNotification/%s', basename(str_replace('\\', '/', $triggerClass))))
            ->assignMultiple($values);
        try {
            $this->mailer->send($email);
        } catch (TransportException $e) {
            $this->logger->warning('Could not send notification email to "{recipient}" due to mailer settings error', [
                'recipient' => $recipients,
                'userId' => $user->user['uid'] ?? 0,
                'exception' => $e,
            ]);
        } catch (RfcComplianceException $e) {
            $this->logger->warning('Could not send notification email to "{recipient}" due to invalid email address', [
                'recipient' => $recipients,
                'userId' => $user->user['uid'] ?? 0,
                'exception' => $e,
            ]);
        } catch (\Exception $e) {
            // Catch all other exceptions, otherwise a failed email login notification will keep
            // a user from logging in. See https://forge.typo3.org/issues/103546
            $this->logger->error('Could not send notification email to "{recipient}" due to a PHP exception', [
                'recipient' => $recipients,
                'userId' => $user->user['uid'] ?? 0,
                'exception' => $e,
            ]);
        }
    }
}
