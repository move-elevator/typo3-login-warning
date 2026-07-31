<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_login_warning" TYPO3 CMS extension.
 *
 * (c) 2025-2026 Konrad Michalik <km@move-elevator.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MoveElevator\Typo3LoginWarning\Domain\Repository;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};

/**
 * IpLogRepository.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
class IpLogRepository
{
    private const TABLE_NAME = 'tx_typo3loginwarning_iplog';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * Registers a sighting of the given identifier and returns whether it was seen
     * for the first time. Updates the timestamp first and only inserts when no row
     * was touched, so concurrent logins racing on the same identifier cannot fail
     * on the unique identifier_hash constraint.
     *
     * @throws Exception
     */
    public function registerIdentifier(string $identifierHash): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);

        $affectedRows = $connection->update(
            self::TABLE_NAME,
            ['tstamp' => time()],
            ['identifier_hash' => $identifierHash],
        );

        if ($affectedRows > 0) {
            return false;
        }

        try {
            $connection->insert(self::TABLE_NAME, [
                'identifier_hash' => $identifierHash,
                'tstamp' => time(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A concurrent login registered the identifier in the meantime.
            return false;
        }

        return true;
    }

    /**
     * Entries with tstamp = 0 stem from extension versions that did not track the
     * last sighting. Their real age is unknown, so they are excluded here and must
     * be initialized via initializeMissingTimestamps() before any cleanup.
     *
     * @throws Exception
     */
    public function countEntriesLastSeenBefore(int $timestamp): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int) $queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('tstamp', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchOne();
    }

    public function deleteEntriesLastSeenBefore(int $timestamp): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt('tstamp', $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('tstamp', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function countEntriesWithMissingTimestamp(): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (int) $queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('tstamp', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->executeQuery()->fetchOne();
    }

    /**
     * Backfills the last-seen timestamp of legacy entries with the current time,
     * granting them a full retention period before they become cleanup candidates.
     *
     * @throws Exception
     */
    public function initializeMissingTimestamps(): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE_NAME);

        return $connection->update(
            self::TABLE_NAME,
            ['tstamp' => time()],
            ['tstamp' => 0],
        );
    }
}
