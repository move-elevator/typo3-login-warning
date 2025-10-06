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
use MoveElevator\Typo3LoginWarning\Registry\DetectorRegistry;
use MoveElevator\Typo3LoginWarning\Registry\NotificationRegistry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequestFactory;

/**
 * LoginNotification.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
final class LoginNotification implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly DetectorRegistry $detectorRegistry,
        private readonly DetectorConfigurationBuilder $configBuilder,
        private readonly NotificationRegistry $notificationRegistry,
    ) {}

    public function __invoke(AfterUserLoggedInEvent $event): void
    {
        if (!$event->getUser() instanceof BackendUserAuthentication) {
            return;
        }
        $currentUser = $event->getUser();

        $currentDetector = null;
        $currentDetectorConfiguration = [];
        $this->configBuilder->setLogger($this->logger);

        $globalNotificationConfig = $this->configBuilder->buildNotificationConfig();

        foreach ($this->detectorRegistry->getDetectors() as $detector) {
            $detectorClass = $detector::class;

            if (!$this->configBuilder->isActive($detectorClass)) {
                continue;
            }

            $currentDetectorConfiguration = $this->configBuilder->build($detectorClass);

            if ($detector->detect($currentUser, $currentDetectorConfiguration)) {
                $currentDetector = $detector;
                break;
            }
        }

        if ($currentDetector === null) {
            return;
        }

        $this->sendNotification(
            $currentUser,
            $event->getRequest() ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals()->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
            $currentDetector,
            $globalNotificationConfig,
            $currentDetectorConfiguration
        );
    }

    /**
     * @param array<string, mixed> $notificationConfig
     * @param array<string, mixed> $detectorConfig
     */
    private function sendNotification(
        BackendUserAuthentication $user,
        mixed $request,
        DetectorInterface $detector,
        array $notificationConfig,
        array $detectorConfig
    ): void {
        // ToDo: Consider more generic way to pass additional data from detectors to notifiers
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

        $mergedConfig = array_merge($notificationConfig, [
            'notificationReceiver' => $detectorConfig['notificationReceiver'] ?? 'recipients',
        ]);

        foreach ($this->notificationRegistry->getNotifiers() as $notifier) {
            $notifier->notify(
                $user,
                $request,
                $detector::class,
                $mergedConfig,
                $additionalData
            );
        }
    }

}
