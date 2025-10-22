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
 * @license GPL-2.0-or-later
 */
class IpLogRepository
{
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * @throws Exception
     */
    public function findByHash(string $identifierHash): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_iplog');

        $result = $queryBuilder
            ->select('*')
            ->from('tx_typo3loginwarning_iplog')
            ->where(
                $queryBuilder->expr()->eq('identifier_hash', $queryBuilder->createNamedParameter($identifierHash, Connection::PARAM_STR)),
            )
            ->executeQuery()->fetchAssociative();

        if (false !== $result) {
            $this->updateTimestamp($identifierHash);

            return true;
        }

        return false;
    }

    public function addHash(string $identifierHash): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_iplog');
        $queryBuilder
            ->insert('tx_typo3loginwarning_iplog')
            ->values([
                'identifier_hash' => $identifierHash,
            ])
            ->executeStatement();
    }

    private function updateTimestamp(string $identifierHash): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_typo3loginwarning_iplog');
        $queryBuilder
            ->update('tx_typo3loginwarning_iplog')
            ->set('tstamp', time())
            ->where(
                $queryBuilder->expr()->eq('identifier_hash', $queryBuilder->createNamedParameter($identifierHash, Connection::PARAM_STR)),
            )
            ->executeStatement();
    }
}
