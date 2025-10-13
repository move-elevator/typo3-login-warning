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

namespace MoveElevator\Typo3LoginWarning\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};

/**
 * UserLogRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class UserLogRepository
{
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @throws Exception
     */
    public function getLastLoginCheckTimestamp(int $userId): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_userlog');

        $result = $queryBuilder
            ->select('last_login_check')
            ->from('tx_typo3loginwarning_userlog')
            ->where(
                $queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchAssociative();

        return false !== $result ? (int) $result['last_login_check'] : null;
    }

    /**
     * @throws Exception
     */
    public function updateLastLoginCheckTimestamp(int $userId, int $timestamp): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_userlog');

        // Try to update existing record first
        $affectedRows = $queryBuilder
            ->update('tx_typo3loginwarning_userlog')
            ->where(
                $queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT)),
            )
            ->set('last_login_check', $timestamp)
            ->executeStatement();

        // If no record was updated, insert a new one
        if (0 === $affectedRows) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_userlog');
            $queryBuilder
                ->insert('tx_typo3loginwarning_userlog')
                ->values([
                    'user_id' => $userId,
                    'last_login_check' => $timestamp,
                ])
                ->executeStatement();
        }
    }
}
