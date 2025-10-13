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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Security;

use MoveElevator\Typo3LoginWarning\Configuration;
use MoveElevator\Typo3LoginWarning\Configuration\DetectorConfigurationBuilder;
use MoveElevator\Typo3LoginWarning\Registry\{DetectorRegistry, NotificationRegistry};
use MoveElevator\Typo3LoginWarning\Security\LoginNotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
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
    private LoginNotification $subject;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->configBuilder = $this->createMock(DetectorConfigurationBuilder::class);

        // Use real registries with empty lists
        $this->detectorRegistry = new DetectorRegistry([]);
        $this->notificationRegistry = new NotificationRegistry([]);

        $this->subject = new LoginNotification(
            $this->detectorRegistry,
            $this->configBuilder,
            $this->notificationRegistry,
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
}
