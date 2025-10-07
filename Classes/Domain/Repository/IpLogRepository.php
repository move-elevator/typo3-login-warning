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
 * IpLogRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class IpLogRepository
{
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @param int $userId
     * @param string $ipAddress
     * @return bool
     * @throws Exception
     */
    public function findByUserAndIp(int $userId, string $ipAddress): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_iplog');

        return (bool)$queryBuilder
            ->select('*')
            ->from('tx_typo3loginwarning_iplog')
            ->where(
                $queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('ip_address', $queryBuilder->createNamedParameter($ipAddress, Connection::PARAM_STR))
            )
            ->executeQuery()->fetchAssociative();
    }

    public function addUserIp(int $userId, string $ipAddress): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_iplog');
        $queryBuilder
            ->insert('tx_typo3loginwarning_iplog')
            ->values([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ])
            ->executeStatement();
    }
}
