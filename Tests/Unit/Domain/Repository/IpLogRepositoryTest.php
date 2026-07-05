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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};

use function is_int;

/**
 * IpLogRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class IpLogRepositoryTest extends TestCase
{
    private Connection&MockObject $connection;
    private IpLogRepository $subject;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);

        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool
            ->method('getConnectionForTable')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturn($this->connection);

        $this->subject = new IpLogRepository($connectionPool);
    }

    public function testRegisterIdentifierReturnsFalseAndUpdatesTimestampForKnownIdentifier(): void
    {
        $identifierHash = 'abc123def456';

        $this->connection->expects(self::once())
            ->method('update')
            ->with(
                'tx_typo3loginwarning_iplog',
                self::callback(static fn (array $data): bool => is_int($data['tstamp']) && $data['tstamp'] > 0),
                ['identifier_hash' => $identifierHash],
            )
            ->willReturn(1);

        $this->connection->expects(self::never())->method('insert');

        self::assertFalse($this->subject->registerIdentifier($identifierHash));
    }

    public function testRegisterIdentifierInsertsAndReturnsTrueForNewIdentifier(): void
    {
        $identifierHash = 'abc123def456';

        $this->connection->expects(self::once())
            ->method('update')
            ->willReturn(0);

        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'tx_typo3loginwarning_iplog',
                self::callback(static fn (array $data): bool => $data['identifier_hash'] === $identifierHash
                    && is_int($data['tstamp'])
                    && $data['tstamp'] > 0),
            )
            ->willReturn(1);

        self::assertTrue($this->subject->registerIdentifier($identifierHash));
    }

    public function testRegisterIdentifierReturnsFalseWhenConcurrentLoginInsertedFirst(): void
    {
        $identifierHash = 'abc123def456';

        $this->connection->expects(self::once())
            ->method('update')
            ->willReturn(0);

        $this->connection->expects(self::once())
            ->method('insert')
            ->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        self::assertFalse($this->subject->registerIdentifier($identifierHash));
    }
}
