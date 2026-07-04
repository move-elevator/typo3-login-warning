<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_login_warning" TYPO3 CMS extension.
 *
 * (c) 2025-2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Security;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Event\ModifyLoginNotificationEvent;
use MoveElevator\Typo3LoginWarning\Registry\{DetectorRegistry, NotificationRegistry};
use MoveElevator\Typo3LoginWarning\Security\LoginNotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;

/**
 * LoginNotificationTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class LoginNotificationTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private DetectorRegistry $detectorRegistry;
    private DetectorConfigurationBuilder&MockObject $configBuilder;
    private NotificationRegistry $notificationRegistry;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private LoginNotification $subject;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configBuilder = $this->createMock(DetectorConfigurationBuilder::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        // Event dispatcher returns event unchanged by default
        $this->eventDispatcher->method('dispatch')
            ->willReturnArgument(0);

        // Use real registries with empty lists
        $this->detectorRegistry = new DetectorRegistry([]);
        $this->notificationRegistry = new NotificationRegistry([]);

        $this->subject = new LoginNotification(
            $this->detectorRegistry,
            $this->configBuilder,
            $this->notificationRegistry,
            $this->eventDispatcher,
        );
        $this->subject->setLogger($this->logger);

        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_notification'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]['_detector'] = [];
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][Configuration::EXT_KEY]);
        unset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY]);
    }

    public function testWarningAtLoginDoesNothingForNonBackendUsers(): void
    {
        $user = $this->createMock(\TYPO3\CMS\Core\Authentication\AbstractUserAuthentication::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Should complete without errors for non-backend users
        ($this->subject)($event);
        $this->addToAssertionCount(1);
    }

    public function testWarningAtLoginDoesNothingWhenUserArrayIsNotArray(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        // @phpstan-ignore assign.propertyType
        $user->user = 'not-an-array'; // Tests line 51 - user is not an array
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Should complete without errors when user is not an array
        ($this->subject)($event);
        $this->addToAssertionCount(1);
    }

    public function testWarningAtLoginHandlesNoActiveDetectors(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS'][Configuration::EXT_KEY] = [
            'newIp' => ['active' => false],
            'longTimeNoSee' => ['active' => false],
            'outOfOffice' => ['active' => false],
        ];

        ($this->subject)($event);

        // Should complete without errors when no detectors are active
        $this->addToAssertionCount(1);
    }

    public function testNotifiesWhenDetectorTriggered(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123, 'username' => 'testuser'];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Create mock detector that will trigger
        $mockDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $mockDetector->expects(self::once())
            ->method('shouldDetectForUser')
            ->willReturn(true);
        $mockDetector->expects(self::once())
            ->method('detect')
            ->willReturn(true);
        $mockDetector->expects(self::once())
            ->method('getAdditionalData')
            ->willReturn([]);

        // Create mock notifier
        $mockNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $mockNotifier->expects(self::once())
            ->method('notify');

        // Use registries with our mocks
        $this->detectorRegistry = new DetectorRegistry([$mockDetector]);
        $this->notificationRegistry = new NotificationRegistry([$mockNotifier]);
        $this->subject = new LoginNotification(
            $this->detectorRegistry,
            $this->configBuilder,
            $this->notificationRegistry,
            $this->eventDispatcher,
        );
        $this->subject->setLogger($this->logger);

        // Configure mocks
        $this->configBuilder->expects(self::once())
            ->method('setLogger')
            ->with($this->logger);

        $this->configBuilder->expects(self::once())
            ->method('buildNotificationConfig')
            ->willReturn(['recipients' => 'test@example.com']);

        $this->configBuilder->expects(self::once())
            ->method('isActive')
            ->with($mockDetector::class)
            ->willReturn(true);

        $this->configBuilder->expects(self::once())
            ->method('build')
            ->with($mockDetector::class)
            ->willReturn(['notificationReceiver' => 'recipients']);

        ($this->subject)($event);
    }

    public function testDetectorExceptionDoesNotBreakLoginAndNextDetectorRuns(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123, 'username' => 'testuser'];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $throwingDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $throwingDetector->method('shouldDetectForUser')->willReturn(true);
        $throwingDetector->expects(self::once())
            ->method('detect')
            ->willThrowException(new RuntimeException('Detector failure'));

        $matchingDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $matchingDetector->method('shouldDetectForUser')->willReturn(true);
        $matchingDetector->expects(self::once())->method('detect')->willReturn(true);
        $matchingDetector->method('getAdditionalData')->willReturn([]);

        $mockNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $mockNotifier->expects(self::once())->method('notify');

        $this->subject = new LoginNotification(
            new DetectorRegistry([$throwingDetector, $matchingDetector]),
            $this->configBuilder,
            new NotificationRegistry([$mockNotifier]),
            $this->eventDispatcher,
        );
        $this->subject->setLogger($this->logger);

        $this->configBuilder->method('buildNotificationConfig')->willReturn([]);
        $this->configBuilder->method('isActive')->willReturn(true);
        $this->configBuilder->method('build')->willReturn([]);

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Login warning detector "{detector}" failed', self::callback(
                static fn (array $context): bool => $context['exception'] instanceof RuntimeException,
            ));

        ($this->subject)($event);
    }

    public function testNotifierExceptionDoesNotBreakLoginAndOtherNotifiersRun(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123, 'username' => 'testuser'];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $mockDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $mockDetector->method('shouldDetectForUser')->willReturn(true);
        $mockDetector->method('detect')->willReturn(true);
        $mockDetector->method('getAdditionalData')->willReturn([]);

        $throwingNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $throwingNotifier->expects(self::once())
            ->method('notify')
            ->willThrowException(new RuntimeException('Notifier failure'));

        $workingNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $workingNotifier->expects(self::once())->method('notify');

        $this->subject = new LoginNotification(
            new DetectorRegistry([$mockDetector]),
            $this->configBuilder,
            new NotificationRegistry([$throwingNotifier, $workingNotifier]),
            $this->eventDispatcher,
        );
        $this->subject->setLogger($this->logger);

        $this->configBuilder->method('buildNotificationConfig')->willReturn([]);
        $this->configBuilder->method('isActive')->willReturn(true);
        $this->configBuilder->method('build')->willReturn([]);

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Login warning notifier "{notifier}" failed', self::callback(
                static fn (array $context): bool => $context['exception'] instanceof RuntimeException,
            ));

        ($this->subject)($event);
    }

    public function testEventListenerExceptionDoesNotBreakLogin(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 123, 'username' => 'testuser'];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        $mockDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $mockDetector->method('shouldDetectForUser')->willReturn(true);
        $mockDetector->method('detect')->willReturn(true);
        $mockDetector->method('getAdditionalData')->willReturn([]);

        $mockNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $mockNotifier->expects(self::never())->method('notify');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willThrowException(new RuntimeException('Listener failure'));

        $this->subject = new LoginNotification(
            new DetectorRegistry([$mockDetector]),
            $this->configBuilder,
            new NotificationRegistry([$mockNotifier]),
            $eventDispatcher,
        );
        $this->subject->setLogger($this->logger);

        $this->configBuilder->method('buildNotificationConfig')->willReturn([]);
        $this->configBuilder->method('isActive')->willReturn(true);
        $this->configBuilder->method('build')->willReturn([]);

        $this->logger->expects(self::once())
            ->method('error')
            ->with('Login warning notification failed', self::callback(
                static fn (array $context): bool => $context['exception'] instanceof RuntimeException,
            ));

        ($this->subject)($event);
    }

    public function testNotificationIsPreventedByEventListener(): void
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = ['uid' => 456, 'username' => 'testuser'];
        $request = $this->createMock(ServerRequestInterface::class);
        $event = new AfterUserLoggedInEvent($user, $request);

        // Create mock detector that will trigger
        $mockDetector = $this->createMock(\MoveElevator\Typo3LoginWarning\Detector\DetectorInterface::class);
        $mockDetector->expects(self::once())
            ->method('shouldDetectForUser')
            ->willReturn(true);
        $mockDetector->expects(self::once())
            ->method('detect')
            ->willReturn(true);
        $mockDetector->expects(self::once())
            ->method('getAdditionalData')
            ->willReturn([]);

        // Create mock notifier - should NOT be called when notification is prevented
        $mockNotifier = $this->createMock(\MoveElevator\Typo3LoginWarning\Notification\NotifierInterface::class);
        $mockNotifier->expects(self::never())
            ->method('notify');

        // Use registries with our mocks
        $this->detectorRegistry = new DetectorRegistry([$mockDetector]);
        $this->notificationRegistry = new NotificationRegistry([$mockNotifier]);

        // Create a new event dispatcher that prevents the notification
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (ModifyLoginNotificationEvent $event) {
                $event->preventNotification();

                return $event;
            });

        $this->subject = new LoginNotification(
            $this->detectorRegistry,
            $this->configBuilder,
            $this->notificationRegistry,
            $eventDispatcher,
        );
        $this->subject->setLogger($this->logger);

        // Configure mocks
        $this->configBuilder->expects(self::once())
            ->method('setLogger')
            ->with($this->logger);

        $this->configBuilder->expects(self::once())
            ->method('buildNotificationConfig')
            ->willReturn(['recipients' => 'test@example.com']);

        $this->configBuilder->expects(self::once())
            ->method('isActive')
            ->with($mockDetector::class)
            ->willReturn(true);

        $this->configBuilder->expects(self::once())
            ->method('build')
            ->with($mockDetector::class)
            ->willReturn(['notificationReceiver' => 'recipients']);

        // Logger should log that notification was prevented
        $this->logger->expects(self::once())
            ->method('info')
            ->with(
                'Login notification was prevented by event listener',
                [
                    'detector' => $mockDetector::class,
                    'userId' => 456,
                ],
            );

        ($this->subject)($event);
    }
}
