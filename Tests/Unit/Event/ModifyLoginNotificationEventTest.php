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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Event;

use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;
use MoveElevator\Typo3LoginWarning\Event\ModifyLoginNotificationEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * ModifyLoginNotificationEventTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class ModifyLoginNotificationEventTest extends TestCase
{
    private BackendUserAuthentication $user;
    private ServerRequestInterface $request;
    private DetectorInterface $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createMock(BackendUserAuthentication::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->detector = $this->createMock(DetectorInterface::class);
    }

    #[Test]
    public function eventInitializesWithProvidedData(): void
    {
        $notificationConfig = ['recipient' => 'admin@example.com'];
        $detectorConfig = ['threshold' => 365];
        $additionalData = ['ip' => '192.168.1.1'];

        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            $notificationConfig,
            $detectorConfig,
            $additionalData,
        );

        self::assertSame($this->user, $event->getUser());
        self::assertSame($this->request, $event->getRequest());
        self::assertSame($this->detector, $event->getDetector());
        self::assertSame($notificationConfig, $event->getNotificationConfig());
        self::assertSame($detectorConfig, $event->getDetectorConfig());
        self::assertSame($additionalData, $event->getAdditionalData());
    }

    #[Test]
    public function notificationIsNotPreventedByDefault(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            [],
        );

        self::assertFalse($event->isNotificationPrevented());
    }

    #[Test]
    public function preventNotificationWorks(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            [],
        );

        $event->preventNotification();

        self::assertTrue($event->isNotificationPrevented());
    }

    #[Test]
    public function allowNotificationReversesPrevention(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            [],
        );

        $event->preventNotification();
        self::assertTrue($event->isNotificationPrevented());

        $event->allowNotification();
        self::assertFalse($event->isNotificationPrevented());
    }

    #[Test]
    public function setNotificationConfigWorks(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            ['old' => 'config'],
            [],
            [],
        );

        $newConfig = ['new' => 'config', 'recipient' => 'test@example.com'];
        $event->setNotificationConfig($newConfig);

        self::assertSame($newConfig, $event->getNotificationConfig());
    }

    #[Test]
    public function setDetectorConfigWorks(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            ['old' => 'config'],
            [],
        );

        $newConfig = ['new' => 'config', 'threshold' => 180];
        $event->setDetectorConfig($newConfig);

        self::assertSame($newConfig, $event->getDetectorConfig());
    }

    #[Test]
    public function setAdditionalDataWorks(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            ['old' => 'data'],
        );

        $newData = ['new' => 'data', 'custom' => 'value'];
        $event->setAdditionalData($newData);

        self::assertSame($newData, $event->getAdditionalData());
    }

    #[Test]
    public function addAdditionalDataAddsNewKey(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            ['existing' => 'data'],
        );

        $event->addAdditionalData('newKey', 'newValue');

        $additionalData = $event->getAdditionalData();
        self::assertArrayHasKey('existing', $additionalData);
        self::assertArrayHasKey('newKey', $additionalData);
        self::assertSame('data', $additionalData['existing']);
        self::assertSame('newValue', $additionalData['newKey']);
    }

    #[Test]
    public function addAdditionalDataOverwritesExistingKey(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            ['key' => 'oldValue'],
        );

        $event->addAdditionalData('key', 'newValue');

        $additionalData = $event->getAdditionalData();
        self::assertSame('newValue', $additionalData['key']);
    }

    #[Test]
    public function addAdditionalDataAcceptsVariousTypes(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            [],
            [],
            [],
        );

        $event->addAdditionalData('string', 'value');
        $event->addAdditionalData('int', 42);
        $event->addAdditionalData('float', 3.14);
        $event->addAdditionalData('bool', true);
        $event->addAdditionalData('array', ['nested' => 'data']);
        $event->addAdditionalData('null', null);

        $additionalData = $event->getAdditionalData();
        self::assertSame('value', $additionalData['string']);
        self::assertSame(42, $additionalData['int']);
        self::assertSame(3.14, $additionalData['float']);
        self::assertTrue($additionalData['bool']);
        self::assertSame(['nested' => 'data'], $additionalData['array']);
        self::assertNull($additionalData['null']);
    }

    #[Test]
    public function multipleModificationsWork(): void
    {
        $event = new ModifyLoginNotificationEvent(
            $this->user,
            $this->request,
            $this->detector,
            ['recipient' => 'admin@example.com'],
            ['threshold' => 365],
            ['ip' => '192.168.1.1'],
        );

        // Modify notification config
        $event->setNotificationConfig(['recipient' => 'security@example.com', 'cc' => 'admin@example.com']);

        // Modify detector config
        $event->setDetectorConfig(['threshold' => 180, 'active' => true]);

        // Add additional data
        $event->addAdditionalData('location', 'Berlin, Germany');
        $event->addAdditionalData('browser', 'Chrome 120');

        // Verify all modifications
        self::assertSame(['recipient' => 'security@example.com', 'cc' => 'admin@example.com'], $event->getNotificationConfig());
        self::assertSame(['threshold' => 180, 'active' => true], $event->getDetectorConfig());

        $additionalData = $event->getAdditionalData();
        self::assertSame('192.168.1.1', $additionalData['ip']);
        self::assertSame('Berlin, Germany', $additionalData['location']);
        self::assertSame('Chrome 120', $additionalData['browser']);
    }
}
