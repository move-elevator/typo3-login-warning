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
use TYPO3\CMS\Core\Database\ConnectionPool;

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
}
