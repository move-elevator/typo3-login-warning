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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Service;

use Exception;
use JsonException;
use MoveElevator\Typo3LoginWarning\Service\IpApiGeolocationService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\{ResponseInterface, StreamInterface};
use Psr\Log\{LoggerInterface, NullLogger};
use RuntimeException;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * IpApiGeolocationServiceTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class IpApiGeolocationServiceTest extends TestCase
{
    private IpApiGeolocationService $subject;
    private RequestFactory&\PHPUnit\Framework\MockObject\MockObject $requestFactory;

    protected function setUp(): void
    {
        $this->requestFactory = $this->createMock(RequestFactory::class);
        $this->subject = new IpApiGeolocationService($this->requestFactory);
        $this->subject->setLogger(new NullLogger());
    }

    public function testGetLocationDataWithInvalidIpReturnsNull(): void
    {
        $result = $this->subject->getLocationData('invalid-ip');
        self::assertNull($result);
    }

    public function testGetLocationDataWithEmptyIpReturnsNull(): void
    {
        $result = $this->subject->getLocationData('');
        self::assertNull($result);
    }

    public function testGetLocationDataWithSuccessfulResponse(): void
    {
        $responseData = [
            'status' => 'success',
            'country' => 'Germany',
            'countryCode' => 'DE',
            'regionName' => 'North Rhine-Westphalia',
            'region' => 'NW',
            'city' => 'Düsseldorf',
            'timezone' => 'Europe/Berlin',
            'isp' => 'Test ISP',
            'org' => 'Test Organization',
            'as' => 'AS12345 Test AS',
            'lat' => 51.2217,
            'lon' => 6.7762,
        ];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode($responseData));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $result = $this->subject->getLocationData('8.8.8.8');

        $expected = [
            'city' => 'Düsseldorf',
            'country' => 'Germany',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetLocationDataWithFailedApiResponse(): void
    {
        $responseData = [
            'status' => 'fail',
            'message' => 'private range',
        ];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode($responseData));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $result = $this->subject->getLocationData('192.168.1.1');

        self::assertNull($result);
    }

    public function testGetLocationDataWithNon200StatusCode(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);

        $this->requestFactory
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataWithClientException(): void
    {
        $this->requestFactory
            ->method('request')
            ->willThrowException($this->createMock(ClientExceptionInterface::class));

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataWithInvalidJson(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('invalid-json');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataWithMissingFields(): void
    {
        $responseData = [
            'status' => 'success',
            'country' => 'Germany',
        ];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode($responseData));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $result = $this->subject->getLocationData('8.8.8.8');

        $expected = [
            'city' => '',
            'country' => 'Germany',
        ];

        self::assertSame($expected, $result);
    }

    public function testGetLocationDataLogsWarningOnClientException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($logger);

        $exception = new class('Network error') extends Exception implements ClientExceptionInterface {};

        $this->requestFactory
            ->method('request')
            ->willThrowException($exception);

        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to fetch IP geolocation data',
                self::callback(function (array $context): bool {
                    return 'Network error' === $context['exception']
                        && '8.8.8.8' === $context['ipAddress'];
                }),
            );

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataLogsWarningOnJsonException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($logger);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willThrowException(new JsonException('Invalid JSON'));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->method('request')
            ->willReturn($response);

        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to decode IP geolocation response',
                self::callback(function (array $context): bool {
                    return 'Invalid JSON' === $context['exception']
                        && '8.8.8.8' === $context['ipAddress'];
                }),
            );

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataLogsErrorOnUnexpectedException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($logger);

        $this->requestFactory
            ->method('request')
            ->willThrowException(new RuntimeException('Unexpected error'));

        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Unexpected error during IP geolocation lookup',
                self::callback(function (array $context): bool {
                    return 'Unexpected error' === $context['exception']
                        && '8.8.8.8' === $context['ipAddress'];
                }),
            );

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataLogsWarningOnNon200Status(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($logger);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(429);

        $this->requestFactory
            ->method('request')
            ->willReturn($response);

        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'IP geolocation API returned non-200 status',
                [
                    'statusCode' => 429,
                    'ipAddress' => '8.8.8.8',
                ],
            );

        $result = $this->subject->getLocationData('8.8.8.8');

        self::assertNull($result);
    }

    public function testGetLocationDataLogsInfoOnUnsuccessfulResponse(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $this->subject->setLogger($logger);

        $responseData = [
            'status' => 'fail',
            'message' => 'private range',
        ];

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode($responseData));

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->requestFactory
            ->method('request')
            ->willReturn($response);

        $logger->expects(self::once())
            ->method('info')
            ->with(
                'IP geolocation API returned unsuccessful response',
                [
                    'response' => $responseData,
                    'ipAddress' => '192.168.1.1',
                ],
            );

        $result = $this->subject->getLocationData('192.168.1.1');

        self::assertNull($result);
    }
}
