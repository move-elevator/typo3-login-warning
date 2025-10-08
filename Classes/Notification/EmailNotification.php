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

namespace MoveElevator\Typo3LoginWarning\Notification;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use TYPO3\CMS\Core\Authentication\{AbstractUserAuthentication, BackendUserAuthentication};
use TYPO3\CMS\Core\Mail\{FluidEmail, MailerInterface};
use TYPO3\CMS\Core\Utility\GeneralUtility;

use function array_key_exists;
use function sprintf;

/**
 * EmailNotification.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class EmailNotification implements NotifierInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $additionalValues
     */
    public function notify(AbstractUserAuthentication $user, ServerRequestInterface $request, string $triggerClass, array $configuration = [], array $additionalValues = []): void
    {
        if (!$user instanceof BackendUserAuthentication) {
            return;
        }

        $headline = 'TYPO3 Backend Login notification';
        $recipients = array_key_exists('recipient', $configuration) ? $configuration['recipient'] : '';

        if ('' === $recipients) {
            // Fallback to global configuration
            $recipients = $GLOBALS['TYPO3_CONF_VARS']['BE']['warning_email_addr'];
        }

        // Build recipients list based on notificationReceiver setting
        $notificationReceiver = $configuration['notificationReceiver'] ?? 'recipients';
        $recipientsList = [];
        $userEmail = trim($user->user['email'] ?? '');

        switch ($notificationReceiver) {
            case 'user':
                // Only send to logged-in user
                if ('' !== $userEmail) {
                    $recipientsList[] = $userEmail;
                }
                break;

            case 'both':
                // Send to email recipients and logged-in user
                if ('' !== $recipients && null !== $recipients) {
                    $recipientsList = explode(',', $recipients);
                }
                if ('' !== $userEmail) {
                    $recipientsList[] = $userEmail;
                }
                break;

            case 'recipients':
            default:
                // Only send to email recipients
                if ('' !== $recipients && null !== $recipients) {
                    $recipientsList = explode(',', $recipients);
                }
                break;
        }

        // Remove duplicates and clean up
        $recipientsList = array_unique(array_filter(array_map('trim', $recipientsList), static fn (string $email): bool => '' !== $email));

        if ([] === $recipientsList) {
            $this->logger->info('No recipient configured for login notification email. Please set $GLOBALS[\'TYPO3_CONF_VARS\'][\'BE\'][\'warning_email_addr\'], configure the recipient via Typo3LoginWarning configuration, or use notificationReceiver setting with a valid user email.');

            return;
        }

        // Send separate emails for different perspectives
        foreach ($recipientsList as $recipient) {
            $isUserNotification = '' !== $userEmail && $recipient === $userEmail;
            $values = [
                'user' => $user->user,
                'prefix' => $user->isAdmin() ? '[AdminLoginWarning]' : '[LoginWarning]',
                'language' => $user->user['lang'] ?? 'default',
                'headline' => $headline,
                'isUserNotification' => $isUserNotification,
            ];

            if ([] !== $additionalValues) {
                $values = array_merge($values, $additionalValues);
            }

            $email = GeneralUtility::makeInstance(FluidEmail::class)
                ->to($recipient)
                ->setRequest($request)
                ->setTemplate(sprintf('LoginNotification/%s', basename(str_replace('\\', '/', $triggerClass))))
                ->assignMultiple($values);
            try {
                $this->mailer->send($email);
            } catch (TransportException $e) {
                $this->logger->warning('Could not send notification email to "{recipient}" due to mailer settings error', [
                    'recipient' => $recipient,
                    'userId' => $user->user['uid'] ?? 0,
                    'exception' => $e,
                ]);
            } catch (RfcComplianceException $e) {
                $this->logger->warning('Could not send notification email to "{recipient}" due to invalid email address', [
                    'recipient' => $recipient,
                    'userId' => $user->user['uid'] ?? 0,
                    'exception' => $e,
                ]);
            } catch (Exception $e) {
                // Catch all other exceptions, otherwise a failed email login notification will keep
                // a user from logging in. See https://forge.typo3.org/issues/103546
                $this->logger->error('Could not send notification email to "{recipient}" due to a PHP exception', [
                    'recipient' => $recipient,
                    'userId' => $user->user['uid'] ?? 0,
                    'exception' => $e,
                ]);
            }
        }
    }
}
