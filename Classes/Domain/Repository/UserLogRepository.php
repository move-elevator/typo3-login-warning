<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typo3_login_warning".
 *
 * Copyright (C) 2025 Konrad Michalik <km@move-elevator.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace MoveElevator\Typo3LoginWarning\Domain\Repository;

use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * UserLogRepository.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 * @license GPL-2.0
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
                $queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT))
            )
            ->executeQuery()->fetchAssociative();

        return $result !== false ? (int)$result['last_login_check'] : null;
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
                $queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT))
            )
            ->set('last_login_check', $timestamp)
            ->executeStatement();

        // If no record was updated, insert a new one
        if ($affectedRows === 0) {
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
