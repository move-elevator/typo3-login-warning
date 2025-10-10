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

    public function testDetectAddsDeviceInfoWhenEnabled(): void
    {
        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '203.0.113.42';
        $request = $this->createMockRequest(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        );

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['includeDeviceInfo' => true];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);
        $ipLogRepository->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration, $request);

        self::assertTrue($result);

        $additionalData = $subject->getAdditionalData();
        self::assertArrayHasKey('deviceInfo', $additionalData);
        self::assertArrayHasKey('browser', $additionalData['deviceInfo']);
        self::assertArrayHasKey('os', $additionalData['deviceInfo']);
        self::assertArrayHasKey('userAgent', $additionalData['deviceInfo']);
        self::assertArrayHasKey('date', $additionalData['deviceInfo']);
        self::assertStringContainsString('Chrome', $additionalData['deviceInfo']['browser']);
        self::assertStringContainsString('macOS', $additionalData['deviceInfo']['os']);
    }

    public function testDetectDoesNotAddDeviceInfoWhenDisabled(): void
    {
        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '203.0.113.42';
        $request = $this->createMockRequest('Mozilla/5.0');

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = ['includeDeviceInfo' => false];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);
        $ipLogRepository->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration, $request);

        self::assertTrue($result);

        $additionalData = $subject->getAdditionalData();
        self::assertArrayNotHasKey('deviceInfo', $additionalData);
    }

    public function testDetectAddsDeviceInfoByDefaultWhenNotConfigured(): void
    {
        $GLOBALS['_SERVER']['REMOTE_ADDR'] = '203.0.113.42';
        $request = $this->createMockRequest(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0',
        );

        $user = $this->createMockUser(['uid' => 123]);
        $configuration = [];

        $ipLogRepository = $this->createMock(IpLogRepository::class);
        $ipLogRepository->expects(self::once())
            ->method('findByUserAndIp')
            ->willReturn(false);
        $ipLogRepository->expects(self::once())
            ->method('addUserIp');

        $subject = new NewIpDetector($ipLogRepository);
        $result = $subject->detect($user, $configuration, $request);

        self::assertTrue($result);

        $additionalData = $subject->getAdditionalData();
        self::assertArrayHasKey('deviceInfo', $additionalData);
        self::assertStringContainsString('Firefox', $additionalData['deviceInfo']['browser']);
        self::assertStringContainsString('Windows', $additionalData['deviceInfo']['os']);
    }

    /**
     * @param array<string, mixed> $userData
     *
     * @return array<string, mixed>
     */
    private function createMockUser(array $userData): array
    {
        return $userData;
    }

    private function createMockRequest(string $userAgent): \Psr\Http\Message\ServerRequestInterface&MockObject
    {
        $request = $this->createMock(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('User-Agent')
            ->willReturn($userAgent);

        return $request;
    }
}
