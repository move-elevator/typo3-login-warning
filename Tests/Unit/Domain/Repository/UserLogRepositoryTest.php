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
use MoveElevator\Typo3LoginWarning\Domain\Repository\UserLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\{Connection, ConnectionPool};
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * UserLogRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class UserLogRepositoryTest extends TestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private UserLogRepository $subject;

    protected function setUp(): void
    {
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->subject = new UserLogRepository($this->connectionPool);
    }

    public function testGetLastLoginCheckTimestampReturnsTimestamp(): void
    {
        $userId = 123;
        $timestamp = 1234567890;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->connectionPool
            ->expects(self::once())
            ->method('getQueryBuilderForTable')
            ->with('tx_typo3loginwarning_userlog')
            ->willReturn($queryBuilder);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);

        $queryBuilder->expects(self::once())
            ->method('select')
            ->with('last_login_check')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_typo3loginwarning_userlog')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('createNamedParameter')
            ->with($userId, Connection::PARAM_INT)
            ->willReturn(':userId');

        $expressionBuilder->expects(self::once())
            ->method('eq')
            ->with('user_id', ':userId')
            ->willReturn('user_id = :userId');

        $queryBuilder->expects(self::once())
            ->method('where')
            ->with('user_id = :userId')
            ->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(['last_login_check' => $timestamp]);

        $queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $actualTimestamp = $this->subject->getLastLoginCheckTimestamp($userId);

        self::assertSame($timestamp, $actualTimestamp);
    }

    public function testGetLastLoginCheckTimestampReturnsNull(): void
    {
        $userId = 123;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->willReturn($queryBuilder);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('createNamedParameter')->willReturn(':userId');
        $expressionBuilder->method('eq')->willReturn('user_id = :userId');
        $queryBuilder->method('where')->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $queryBuilder->method('executeQuery')->willReturn($result);

        $actualTimestamp = $this->subject->getLastLoginCheckTimestamp($userId);

        self::assertNull($actualTimestamp);
    }

    public function testUpdateLastLoginCheckTimestampUpdatesExisting(): void
    {
        $userId = 123;
        $timestamp = 1234567890;

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->connectionPool
            ->expects(self::once())
            ->method('getQueryBuilderForTable')
            ->with('tx_typo3loginwarning_userlog')
            ->willReturn($queryBuilder);

        $queryBuilder->method('expr')->willReturn($expressionBuilder);

        $queryBuilder->expects(self::once())
            ->method('update')
            ->with('tx_typo3loginwarning_userlog')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('createNamedParameter')
            ->with($userId, Connection::PARAM_INT)
            ->willReturn(':userId');

        $expressionBuilder->expects(self::once())
            ->method('eq')
            ->with('user_id', ':userId')
            ->willReturn('user_id = :userId');

        $queryBuilder->expects(self::once())
            ->method('where')
            ->with('user_id = :userId')
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('set')
            ->with('last_login_check', $timestamp)
            ->willReturnSelf();

        $queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1); // One row updated

        $this->subject->updateLastLoginCheckTimestamp($userId, $timestamp);
    }

    public function testUpdateLastLoginCheckTimestampInsertsNew(): void
    {
        $userId = 123;
        $timestamp = 1234567890;

        $updateQueryBuilder = $this->createMock(QueryBuilder::class);
        $insertQueryBuilder = $this->createMock(QueryBuilder::class);
        $expressionBuilder = $this->createMock(ExpressionBuilder::class);

        $this->connectionPool
            ->expects(self::exactly(2))
            ->method('getQueryBuilderForTable')
            ->with('tx_typo3loginwarning_userlog')
            ->willReturnOnConsecutiveCalls($updateQueryBuilder, $insertQueryBuilder);

        $updateQueryBuilder->method('expr')->willReturn($expressionBuilder);
        $updateQueryBuilder->method('update')->willReturnSelf();
        $updateQueryBuilder->method('createNamedParameter')->willReturn(':userId');
        $expressionBuilder->method('eq')->willReturn('user_id = :userId');
        $updateQueryBuilder->method('where')->willReturnSelf();
        $updateQueryBuilder->method('set')->willReturnSelf();
        $updateQueryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(0); // No rows updated

        $insertQueryBuilder->expects(self::once())
            ->method('insert')
            ->with('tx_typo3loginwarning_userlog')
            ->willReturnSelf();

        $insertQueryBuilder->expects(self::once())
            ->method('values')
            ->with([
                'user_id' => $userId,
                'last_login_check' => $timestamp,
            ])
            ->willReturnSelf();

        $insertQueryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->subject->updateLastLoginCheckTimestamp($userId, $timestamp);
    }
}
