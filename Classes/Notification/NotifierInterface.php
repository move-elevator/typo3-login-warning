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

namespace MoveElevator\Typo3LoginWarning\Notification;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * NotifierInterface.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
interface NotifierInterface
{
    /**
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $additionalValues
     */
    public function notify(AbstractUserAuthentication $user, ServerRequestInterface $request, string $triggerClass, array $configuration = [], array $additionalValues = []): void;
}
