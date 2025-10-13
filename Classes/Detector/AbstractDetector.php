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

use function in_array;

/**
 * AbstractDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
abstract class AbstractDetector implements DetectorInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $additionalData = [];

    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     */
    public function shouldDetectForUser(array $userArray, array $configuration = []): bool
    {
        $affectedUsers = $configuration['affectedUsers'] ?? 'all';

        return match ($affectedUsers) {
            'admins' => (bool) ($userArray['admin'] ?? false),
            'maintainers' => in_array((int) ($userArray['uid'] ?? 0), $GLOBALS['TYPO3_CONF_VARS']['SYS']['systemMaintainers'] ?? [], true),
            default => true, // 'all'
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }
}
