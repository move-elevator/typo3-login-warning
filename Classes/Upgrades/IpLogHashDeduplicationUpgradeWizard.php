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

namespace MoveElevator\Typo3LoginWarning\Upgrades;

use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * IpLogHashDeduplicationUpgradeWizard.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 */
#[UpgradeWizard('typo3LoginWarning_ipLogHashDeduplication')]
final readonly class IpLogHashDeduplicationUpgradeWizard implements UpgradeWizardInterface
{
    private const TABLE_NAME = 'tx_typo3loginwarning_iplog';

    public function __construct(private ConnectionPool $connectionPool) {}

    public function getTitle(): string
    {
        return 'EXT:typo3_login_warning: Remove IP log entries with empty identifier hash';
    }

    public function getDescription(): string
    {
        return 'Removes invalid entries with an empty identifier_hash from the IP log table '
            .'to allow the UNIQUE KEY migration to be applied via database:updateschema.';
    }

    public function updateNecessary(): bool
    {
        return $this->hasEmptyHashRows();
    }

    public function executeUpdate(): bool
    {
        $this->connectionPool
            ->getConnectionForTable(self::TABLE_NAME)
            ->delete(
                self::TABLE_NAME,
                ['identifier_hash' => ''],
                ['identifier_hash' => Connection::PARAM_STR],
            );

        return true;
    }

    public function getPrerequisites(): array
    {
        return [];
    }

    private function hasEmptyHashRows(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_NAME);

        return (bool) $queryBuilder
            ->count('uid')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'identifier_hash',
                    $queryBuilder->createNamedParameter('', Connection::PARAM_STR),
                ),
            )
            ->executeQuery()
            ->fetchOne();
    }
}
