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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

use function is_int;

/**
 * IpLogRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class IpLogRepositoryTest extends TestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private QueryBuilder&MockObject $queryBuilder;
    private ExpressionBuilder&MockObject $expressionBuilder;
    private IpLogRepository $subject;

    protected function setUp(): void
    {
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder
            ->method('expr')
            ->willReturn($this->expressionBuilder);

        $this->subject = new IpLogRepository($this->connectionPool);
    }

    public function testFindByHashReturnsTrueAndUpdatesTimestamp(): void
    {
        $identifierHash = 'abc123def456';

        // First call for SELECT query
        $selectQueryBuilder = $this->createMock(QueryBuilder::class);
        $selectQueryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $selectQueryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();

        $selectQueryBuilder->expects(self::once())
            ->method('createNamedParameter')
            ->with($identifierHash, Connection::PARAM_STR)
            ->willReturn(':hash');

        $selectQueryBuilder->method('expr')
            ->willReturn($this->expressionBuilder);

        $this->expressionBuilder->method('eq')
            ->with('identifier_hash', ':hash')
            ->willReturn('identifier_hash = :hash');

        $selectQueryBuilder->expects(self::once())
            ->method('where')
            ->with('identifier_hash = :hash')
            ->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(['identifier_hash' => $identifierHash]);

        $selectQueryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        // Second call for UPDATE query
        $updateQueryBuilder = $this->createMock(QueryBuilder::class);
        $updateQueryBuilder->expects(self::once())
            ->method('update')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();

        $updateQueryBuilder->expects(self::once())
            ->method('set')
            ->with('tstamp', self::callback(static fn ($value): bool => is_int($value) && $value > 0))
            ->willReturnSelf();

        $updateQueryBuilder->expects(self::once())
            ->method('createNamedParameter')
            ->with($identifierHash, Connection::PARAM_STR)
            ->willReturn(':hash');

        $updateQueryBuilder->method('expr')
            ->willReturn($this->expressionBuilder);

        $updateQueryBuilder->expects(self::once())
            ->method('where')
            ->with('identifier_hash = :hash')
            ->willReturnSelf();

        $updateQueryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1);

        // Mock ConnectionPool to return different query builders
        $connectionPool = $this->createMock(ConnectionPool::class);
        $connectionPool
            ->expects(self::exactly(2))
            ->method('getQueryBuilderForTable')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnCallback(static function () use ($selectQueryBuilder, $updateQueryBuilder): QueryBuilder {
                static $callCount = 0;
                ++$callCount;

                return 1 === $callCount ? $selectQueryBuilder : $updateQueryBuilder;
            });

        $subject = new IpLogRepository($connectionPool);

        $found = $subject->findByHash($identifierHash);

        self::assertTrue($found);
    }

    public function testFindByHashReturnsFalse(): void
    {
        $identifierHash = 'abc123def456';

        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('createNamedParameter')->willReturn(':param');
        $this->expressionBuilder->method('eq')->willReturn('field = :param');
        $this->queryBuilder->method('where')->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $found = $this->subject->findByHash($identifierHash);

        self::assertFalse($found);
    }

    public function testAddHash(): void
    {
        $identifierHash = 'abc123def456';

        $this->queryBuilder->expects(self::once())
            ->method('insert')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();

        $this->queryBuilder->expects(self::once())
            ->method('values')
            ->with([
                'identifier_hash' => $identifierHash,
            ])
            ->willReturnSelf();

        $this->queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->subject->addHash($identifierHash);
    }
}
