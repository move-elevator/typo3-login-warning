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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Detector;

use MoveElevator\Typo3LoginWarning\Detector\{DetectorInterface, NewIpDetector};
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use MoveElevator\Typo3LoginWarning\Service\GeolocationServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * NewIpDetectorTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
final class NewIpDetectorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean slate for each test
        unset($GLOBALS['_SERVER']['REMOTE_ADDR']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['_SERVER']['REMOTE_ADDR']);
    }

    public function testImplementsDetectorInterface(): void
    {
        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        self::assertInstanceOf(DetectorInterface::class, $subject);
    }

    public function testDetectReturnsFalseWhenIpIsWhitelisted(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'whitelist' => ['192.168.1.1'],
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectReturnsTrueWhenIpIsNew(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => true];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectReturnsFalseWhenIpExists(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => true];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(true);

        $ipLogRepository
            ->expects(self::never())
            ->method('addUserIp');

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertFalse($result);
    }

    public function testDetectWithoutHashingWhenConfigured(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['hashIpAddress' => false];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectDefaultsToHashingWhenNotConfigured(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.100';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->with(123, self::matchesRegularExpression('/.*/'))
            ->willReturn(false);

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp')
            ->with(123, self::matchesRegularExpression('/.*/'));

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
    }

    public function testDetectDoesNotFetchGeolocationWhenDisabled(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'hashIpAddress' => true,
            'fetchGeolocation' => false,
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $geolocationService = $this->createMock(GeolocationServiceInterface::class);

        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);

        $geolocationService
            ->expects(self::never())
            ->method('getLocationData');

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIpDetector($ipLogRepository, $geolocationService);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testGetAdditionalDataReturnsEmptyArrayInitially(): void
    {
        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testDetectDoesNotFetchGeolocationForPrivateIps(): void
    {
        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [
            'hashIpAddress' => true,
            'fetchGeolocation' => true,
        ];

        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '192.168.1.1';

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $geolocationService = $this->createMock(GeolocationServiceInterface::class);

        $ipLogRepository
            ->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);

        $geolocationService
            ->expects(self::never())
            ->method('getLocationData');

        $ipLogRepository
            ->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIpDetector($ipLogRepository, $geolocationService);
        $result = $subject->detect($user, $configuration);

        self::assertTrue($result);
        self::assertSame([], $subject->getAdditionalData());
    }

    public function testShouldDetectForUserReturnsFalseForNonAdmin(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => false]);
        $configuration = ['affectedUsers' => 'admins'];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertFalse($result);
    }

    public function testShouldDetectForUserReturnsTrueForAdmin(): void
    {
        $user = $this->createMockUser(['uid' => 123, 'admin' => true]);
        $configuration = ['affectedUsers' => 'admins'];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertTrue($result);
    }

    public function testShouldDetectForUserReturnsFalseForNonMaintainer(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [2, 3];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['affectedUsers' => 'maintainers'];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertFalse($result);

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers']);
    }

    public function testShouldDetectForUserReturnsTrueForMaintainer(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] = [123, 456];

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['affectedUsers' => 'maintainers'];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->shouldDetectForUser($user, $configuration);

        self::assertTrue($result);

        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers']);
    }

    /**
     * @param array<string, mixed> $userData
     */
    private function createMockUser(array $userData): BackendUserAuthentication&MockObject
    {
        $user = $this->createMock(BackendUserAuthentication::class);
        $user->user = $userData;

        return $user;
    }
}
