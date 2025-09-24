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

namespace MoveElevator\Typo3LoginWarning\Security;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Notification\NotifierInterface;
use MoveElevator\Typo3LoginWarning\Trigger\TriggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * LoginNotification.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class LoginNotification implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    #[AsEventListener('move-elevator/typo3-login-warning/login-notification')]
    public function emailAtLogin(AfterUserLoggedInEvent $event): void
    {
        if (!$event->getUser() instanceof BackendUserAuthentication) {
            return;
        }
        $currentUser = $event->getUser();

        $currentTrigger = null;
        $currentTriggerConfiguration = [];

        // Check configured triggers
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['trigger'] as $triggerClass => $triggerConfiguration) {

            $triggerHasConfiguraton = !is_int($triggerClass);
            if ($triggerHasConfiguraton) {
                $currentTriggerConfiguration = $triggerConfiguration;
            } else {
                // Default configuration
                $triggerClass = $triggerConfiguration;
                $currentTriggerConfiguration = [];
            }

            $trigger = GeneralUtility::makeInstance($triggerClass);

            if (!$trigger instanceof TriggerInterface) {
                $this->logger->warning('Configured trigger class "{class}" does not implement MoveElevator\Typo3LoginWarning\Security\TriggerInterface', [
                    'class' => $triggerClass,
                ]);
                continue;
            }

            if ($trigger->isTriggered($currentUser, $currentTriggerConfiguration)) {
                $currentTrigger = $triggerClass;
                break;
            }
        }

        if ($currentTrigger === null) {
            return;
        }

        if (!array_key_exists('notification', $currentTriggerConfiguration)) {
            $currentTriggerConfiguration = [
                'notification' => $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'],
            ];
        }

        // Send notifications
        foreach ($currentTriggerConfiguration['notification'] as $notificationClass => $notificationConfiguration) {
            $notifier = GeneralUtility::makeInstance($notificationClass);

            if (!$notifier instanceof NotifierInterface) {
                $this->logger->warning('Configured notification class "{class}" does not implement MoveElevator\Typo3LoginWarning\Notification\NotifierInterface', [
                    'class' => $notificationClass,
                ]);
                continue;
            }

            $notifier->notify(
                $currentUser,
                $event->getRequest() ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals()->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
                $currentTrigger,
                $notificationConfiguration
            );
        }
    }

}
