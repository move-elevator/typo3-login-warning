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

namespace MoveElevator\Typo3LoginWarning\Security;

use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use MoveElevator\Typo3LoginWarning\Event\ModifyLoginNotificationEvent;
use MoveElevator\Typo3LoginWarning\Registry\{DetectorRegistry, NotificationRegistry};
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequestFactory;

use function is_array;

/**
 * LoginNotification.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class LoginNotification implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly DetectorRegistry $detectorRegistry,
        private readonly DetectorConfigurationBuilder $configBuilder,
        private readonly NotificationRegistry $notificationRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function __invoke(AfterUserLoggedInEvent $event): void
    {
        if (!$event->getUser() instanceof BackendUserAuthentication) {
            return;
        }
        $currentUser = $event->getUser();
        $currentUserArray = $currentUser->user;
        if (!is_array($currentUserArray)) {
            return;
        }

        $currentDetector = null;
        $currentDetectorConfiguration = [];
        if (null !== $this->logger) {
            $this->configBuilder->setLogger($this->logger);
        }

        $globalNotificationConfig = $this->configBuilder->buildNotificationConfig();

        foreach ($this->detectorRegistry->getDetectors() as $detector) {
            $detectorClass = $detector::class;

            if (!$this->configBuilder->isActive($detectorClass)) {
                continue;
            }

            $currentDetectorConfiguration = $this->configBuilder->build($detectorClass);

            if (!$detector->shouldDetectForUser($currentUserArray, $currentDetectorConfiguration)) {
                continue;
            }

            if ($detector->detect($currentUserArray, $currentDetectorConfiguration, $event->getRequest())) {
                $currentDetector = $detector;
                break;
            }
        }

        if (null === $currentDetector) {
            return;
        }

        $this->sendNotification(
            $currentUser,
            $event->getRequest() ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals()->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
            $currentDetector,
            $globalNotificationConfig,
            $currentDetectorConfiguration,
        );
    }

    /**
     * @param array<string, mixed> $notificationConfig
     * @param array<string, mixed> $detectorConfig
     */
    private function sendNotification(
        BackendUserAuthentication $user,
        ServerRequestInterface $request,
        DetectorInterface $detector,
        array $notificationConfig,
        array $detectorConfig,
    ): void {
        $mergedConfig = array_merge($notificationConfig, [
            'notificationReceiver' => $detectorConfig['notificationReceiver'] ?? 'recipients',
        ]);

        $additionalData = $detector->getAdditionalData() ?? [];

        $event = new ModifyLoginNotificationEvent(
            $user,
            $request,
            $detector,
            $mergedConfig,
            $detectorConfig,
            $additionalData,
        );

        /** @var ModifyLoginNotificationEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        if ($event->isNotificationPrevented()) {
            $this->logger?->info('Login notification was prevented by event listener', [
                'detector' => $detector::class,
                'userId' => $user->user['uid'] ?? 0,
            ]);

            return;
        }

        foreach ($this->notificationRegistry->getNotifiers() as $notifier) {
            $notifier->notify(
                $user,
                $request,
                $detector::class,
                $event->getNotificationConfig(),
                $event->getAdditionalData(),
            );
        }
    }
}
