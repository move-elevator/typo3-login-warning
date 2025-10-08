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

use Doctrine\DBAL\Exception;
use MoveElevator\Typo3LoginWarning\Domain\Repository\UserLogRepository;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;

/**
 * LongTimeNoSeeDetector.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class LongTimeNoSeeDetector extends AbstractDetector
{
    private const DEFAULT_THRESHOLD_DAYS = 365;

    public function __construct(
        private UserLogRepository $userLogRepository,
    ) {}

    /**
     * Unfortunately, I was unable to use the existing “lastlogin” field in the backend user. In the AfterUserLoggedInEvent,
     * the field is already overwritten with the current time, so that the required time span can no longer be accessed.
     * Unfortunately, I was also unable to find another event or hook to access this information “earlier” during
     * authentication. For the time being, the only option was to store this information in a separate table.
     *
     * @param array<string, mixed> $configuration
     *
     * @throws Exception
     */
    public function detect(AbstractUserAuthentication $user, array $configuration = [], ?ServerRequestInterface $request = null): bool
    {
        $userArray = $user->user;
        $userId = (int) $userArray['uid'];
        $currentTimestamp = time();

        $thresholdDays = (int) ($configuration['thresholdDays'] ?? self::DEFAULT_THRESHOLD_DAYS);
        $thresholdTimestamp = $currentTimestamp - ($thresholdDays * 24 * 60 * 60);

        $lastLoginCheckTimestamp = $this->userLogRepository->getLastLoginCheckTimestamp($userId);

        if (null !== $lastLoginCheckTimestamp) {
            $this->additionalData = [
                'daysSinceLastLogin' => (int) floor(($currentTimestamp - $lastLoginCheckTimestamp) / (24 * 60 * 60)),
            ];
        }

        $this->userLogRepository->updateLastLoginCheckTimestamp($userId, $currentTimestamp);

        return null !== $lastLoginCheckTimestamp && $lastLoginCheckTimestamp <= $thresholdTimestamp;
    }
}
