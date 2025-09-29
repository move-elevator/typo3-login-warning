<?php

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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Service;

use MoveElevator\Typo3LoginWarning\Service\IpApiGeolocationService;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\RequestFactory;


/**
 * IpApiGeolocationServiceTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
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
            'country' => 'Germany',
            'countryCode' => 'DE',
            'region' => 'North Rhine-Westphalia',
            'regionCode' => 'NW',
            'city' => 'Düsseldorf',
            'timezone' => 'Europe/Berlin',
            'isp' => 'Test ISP',
            'org' => 'Test Organization',
            'as' => 'AS12345 Test AS',
            'lat' => 51.2217,
            'lon' => 6.7762,
        ];

        self::assertEquals($expected, $result);
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
            'country' => 'Germany',
            'countryCode' => '',
            'region' => '',
            'regionCode' => '',
            'city' => '',
            'timezone' => '',
            'isp' => '',
            'org' => '',
            'as' => '',
            'lat' => null,
            'lon' => null,
        ];

        self::assertEquals($expected, $result);
    }
}
