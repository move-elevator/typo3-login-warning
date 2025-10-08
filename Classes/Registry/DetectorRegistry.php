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

namespace MoveElevator\Typo3LoginWarning\Registry;

use MoveElevator\Typo3LoginWarning\Detector\DetectorInterface;

/**
 * DetectorRegistry.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class DetectorRegistry
{
    /**
     * @param iterable<DetectorInterface> $detectors
     */
    public function __construct(
        private readonly iterable $detectors,
    ) {}

    /**
     * Get all registered detectors in priority order.
     *
     * @return iterable<DetectorInterface>
     */
    public function getDetectors(): iterable
    {
        return $this->detectors;
    }
}
