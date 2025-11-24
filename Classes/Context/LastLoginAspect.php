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

namespace MoveElevator\Typo3LoginWarning\Context;

use DateTime;
use TYPO3\CMS\Core\Context\AspectInterface;
use TYPO3\CMS\Core\Context\Exception\AspectPropertyNotFoundException;

/**
 * LastLoginAspect.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class LastLoginAspect implements AspectInterface
{
    public function __construct(protected ?DateTime $lastLogin) {}

    public function get(string $name): mixed
    {
        if ('last_login' === $name) {
            return $this->lastLogin;
        }

        throw new AspectPropertyNotFoundException('Property "'.$name.'" not found in Aspect "'.self::class.'".', 1735135381);
    }
}
