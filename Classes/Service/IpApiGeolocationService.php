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

namespace MoveElevator\Typo3LoginWarning\Service;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * IpApiGeolocationService.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
 */
class IpApiGeolocationService implements GeolocationServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const API_URL = 'http://ip-api.com/json/';
    private const TIMEOUT = 3;

    public function __construct(
        private readonly RequestFactory $requestFactory,
    ) {}

    public function getLocationData(string $ipAddress): ?array
    {
        if ($ipAddress === '' || filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        try {
            $response = $this->requestFactory->request(
                self::API_URL . $ipAddress,
                'GET',
                [
                    'timeout' => self::TIMEOUT,
                    'headers' => [
                        'User-Agent' => 'TYPO3 Login Warning Extension',
                    ],
                ]
            );

            if ($response->getStatusCode() !== 200) {
                $this->logger?->warning('IP geolocation API returned non-200 status', [
                    'statusCode' => $response->getStatusCode(),
                    'ipAddress' => $ipAddress,
                ]);
                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
                $this->logger?->info('IP geolocation API returned unsuccessful response', [
                    'response' => $data,
                    'ipAddress' => $ipAddress,
                ]);
                return null;
            }

            return [
                'city' => $data['city'] ?? '',
                'country' => $data['country'] ?? '',
            ];
        } catch (ClientExceptionInterface $e) {
            $this->logger?->warning('Failed to fetch IP geolocation data', [
                'exception' => $e->getMessage(),
                'ipAddress' => $ipAddress,
            ]);
            return null;
        } catch (\JsonException $e) {
            $this->logger?->warning('Failed to decode IP geolocation response', [
                'exception' => $e->getMessage(),
                'ipAddress' => $ipAddress,
            ]);
            return null;
        } catch (\Throwable $e) {
            $this->logger?->error('Unexpected error during IP geolocation lookup', [
                'exception' => $e->getMessage(),
                'ipAddress' => $ipAddress,
            ]);
            return null;
        }
    }
}
