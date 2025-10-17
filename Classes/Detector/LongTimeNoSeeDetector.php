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

use DateTime;
use Doctrine\DBAL\Exception;
use MoveElevator\Typo3LoginWarning\Configuration;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;

/**
 * LongTimeNoSeeDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class LongTimeNoSeeDetector extends AbstractDetector
{
    private const DEFAULT_THRESHOLD_DAYS = 365;

    public function __construct(
        private readonly Context $context,
    ) {}

    /**
     * @param array<string, mixed> $userArray
     * @param array<string, mixed> $configuration
     *
     * @throws Exception
     */
    public function detect(array $userArray, array $configuration = [], ?ServerRequestInterface $request = null): bool
    {
        if (!$this->context->hasAspect(Configuration::EXT_KEY)) {
            return false;
        }
        $lastLogin = $this->context->getPropertyFromAspect(Configuration::EXT_KEY, 'last_login');

        if (null === $lastLogin) {
            return false;
        }

        $currentDate = new DateTime();

        $interval = $lastLogin->diff($currentDate);
        $thresholdDays = (int) ($configuration['thresholdDays'] ?? self::DEFAULT_THRESHOLD_DAYS);

        if ($interval->days <= $thresholdDays) {
            return false;
        }

        $this->additionalData = [
            'daysSinceLastLogin' => $interval->days,
        ];

        return true;
    }
}
