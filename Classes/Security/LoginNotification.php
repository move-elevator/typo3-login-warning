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
use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;
use MoveElevator\Typo3LoginWarning\Notification\NotifierInterface;
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

        $currentDetector = null;
        $currentDetectorConfiguration = [];

        // Check configured detectors
        foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['detector'] as $detectorClass => $detectorConfiguration) {
            $detectorHasConfiguration = !is_int($detectorClass);
            if ($detectorHasConfiguration) {
                $currentDetectorConfiguration = $detectorConfiguration;
            } else {
                $detectorClass = $detectorConfiguration;
                $currentDetectorConfiguration = [];
            }

            $detector = GeneralUtility::makeInstance($detectorClass);

            if (!$detector instanceof DetectorInterface) {
                $this->logger->warning('Configured detector class "{class}" does not implement MoveElevator\Typo3LoginWarning\Detector\DetectorInterface', [
                    'class' => $detectorClass,
                ]);
                continue;
            }

            // Merge with detector default configuration
            $defaultConfig = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_detector'][$detector::class] ?? [];
            $currentDetectorConfiguration = array_merge($defaultConfig, $currentDetectorConfiguration);

            if ($detector->detect($currentUser, $currentDetectorConfiguration)) {
                $currentDetector = $detector;
                break;
            }
        }

        if ($currentDetector === null) {
            return;
        }

        // Fallback to global notification configuration
        if (!array_key_exists('notification', $currentDetectorConfiguration)) {
            $currentDetectorConfiguration = [
                'notification' => $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'],
            ];
        }

        // Send notifications
        foreach ($currentDetectorConfiguration['notification'] as $notificationClass => $notificationConfiguration) {
            $notifier = GeneralUtility::makeInstance($notificationClass);

            if (!$notifier instanceof NotifierInterface) {
                $this->logger->warning('Configured notification class "{class}" does not implement MoveElevator\Typo3LoginWarning\Notification\NotifierInterface', [
                    'class' => $notificationClass,
                ]);
                continue;
            }

            $additionalData = [];
            if ($currentDetector instanceof NewIpDetector) {
                $additionalData['locationData'] = $currentDetector->getLocationData();
            }
            if ($currentDetector instanceof LongTimeNoSeeDetector) {
                $additionalData['daysSinceLastLogin'] = $currentDetector->getDaysSinceLastLogin();
            }
            if ($currentDetector instanceof OutOfOfficeDetector) {
                $additionalData['violationDetails'] = $currentDetector->getViolationDetails();
            }

            $notifier->notify(
                $currentUser,
                $event->getRequest() ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals()->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
                $currentDetector::class,
                $notificationConfiguration,
                $additionalData
            );
        }
    }

}
