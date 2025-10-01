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

use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use MoveElevator\Typo3LoginWarning\Detector\LongTimeNoSeeDetector;
use MoveElevator\Typo3LoginWarning\Detector\NewIpDetector;
use MoveElevator\Typo3LoginWarning\Detector\OutOfOfficeDetector;
use MoveElevator\Typo3LoginWarning\Notification\EmailNotification;
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
    public function warningAtLogin(AfterUserLoggedInEvent $event): void
    {
        if (!$event->getUser() instanceof BackendUserAuthentication) {
            return;
        }
        $currentUser = $event->getUser();

        $currentDetector = null;
        $configBuilder = GeneralUtility::makeInstance(DetectorConfigurationBuilder::class);
        $configBuilder->setLogger($this->logger);

        // Get global notification configuration
        $globalNotificationConfig = $configBuilder->buildNotificationConfig();

        foreach ($this->getDetectors() as $detectorClass) {
            // Check if detector is active
            if (!$configBuilder->isActive($detectorClass)) {
                continue;
            }

            // Build detector configuration from extension config
            $currentDetectorConfiguration = $configBuilder->build($detectorClass);

            $detector = GeneralUtility::makeInstance($detectorClass);

            if ($detector->detect($currentUser, $currentDetectorConfiguration)) {
                $currentDetector = $detector;
                break;
            }
        }

        if ($currentDetector === null) {
            return;
        }

        // Send notification
        $this->sendNotification(
            $currentUser,
            $event->getRequest() ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals()->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
            $currentDetector,
            $globalNotificationConfig
        );
    }

    /**
     * @param array<string, mixed> $notificationConfig
     */
    private function sendNotification(
        BackendUserAuthentication $user,
        mixed $request,
        DetectorInterface $detector,
        array $notificationConfig
    ): void {
        $notifier = GeneralUtility::makeInstance(EmailNotification::class);

        $additionalData = [];
        if ($detector instanceof NewIpDetector) {
            $additionalData['locationData'] = $detector->getLocationData();
        }
        if ($detector instanceof LongTimeNoSeeDetector) {
            $additionalData['daysSinceLastLogin'] = $detector->getDaysSinceLastLogin();
        }
        if ($detector instanceof OutOfOfficeDetector) {
            $additionalData['violationDetails'] = $detector->getViolationDetails();
        }

        $notifier->notify(
            $user,
            $request,
            $detector::class,
            $notificationConfig,
            $additionalData
        );
    }

    /**
     * @return array<int, class-string<DetectorInterface>>
     */
    private function getDetectors(): array
    {
        return [
            NewIpDetector::class,
            LongTimeNoSeeDetector::class,
            OutOfOfficeDetector::class,
        ];
    }

}
