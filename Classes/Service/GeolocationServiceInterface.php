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

/**
 * GeolocationServiceInterface.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
interface GeolocationServiceInterface
{
    /**
     * Get location data for an IP address.
     *
     * @return array{city: string, country: string}|null Returns city and country or null if lookup fails
     */
    public function getLocationData(string $ipAddress): ?array;
}
