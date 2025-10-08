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
 * IpLogRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
 */
class IpLogRepository
{
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @throws Exception
     */
    public function findByUserAndIp(int $userId, string $ipAddress): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_iplog');

        return (bool) $queryBuilder
            ->select('*')
            ->from('tx_typo3loginwarning_iplog')
            ->where(
                $queryBuilder->expr()->eq('user_id', $queryBuilder->createNamedParameter($userId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('ip_address', $queryBuilder->createNamedParameter($ipAddress, Connection::PARAM_STR)),
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
