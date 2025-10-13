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

namespace MoveElevator\Typo3LoginWarning\Service;

use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\{LoggerAwareInterface, LoggerAwareTrait};
use Throwable;
use TYPO3\CMS\Core\Http\RequestFactory;

use function is_array;

/**
 * IpApiGeolocationService.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
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
        if ('' === $ipAddress || false === filter_var($ipAddress, \FILTER_VALIDATE_IP)) {
            return null;
        }

        try {
            $response = $this->requestFactory->request(
                self::API_URL.$ipAddress,
                'GET',
                [
                    'timeout' => self::TIMEOUT,
                    'headers' => [
                        'User-Agent' => 'TYPO3 Login Warning Extension',
                    ],
                ],
            );

            if (200 !== $response->getStatusCode()) {
                $this->logger?->warning('IP geolocation API returned non-200 status', [
                    'statusCode' => $response->getStatusCode(),
                    'ipAddress' => $ipAddress,
                ]);

                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true, 512, \JSON_THROW_ON_ERROR);

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
        } catch (JsonException $e) {
            $this->logger?->warning('Failed to decode IP geolocation response', [
                'exception' => $e->getMessage(),
                'ipAddress' => $ipAddress,
            ]);

            return null;
        } catch (Throwable $e) {
            $this->logger?->error('Unexpected error during IP geolocation lookup', [
                'exception' => $e->getMessage(),
                'ipAddress' => $ipAddress,
            ]);

            return null;
        }
    }
}
