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

namespace MoveElevator\Typo3LoginWarning\Detector;

use Psr\Http\Message\ServerRequestInterface;

/**
 * DetectorInterface.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
interface DetectorInterface
{
    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     */
    public function shouldDetectForUser(array $userArray, array $configuration = []): bool;

    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     */
    public function detect(array $userArray, array $configuration = [], ?ServerRequestInterface $request = null): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function getAdditionalData(): ?array;
}
