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

/**
 * IpLogRepositoryTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0
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

    public function testFindByUserAndIpReturnsTrue(): void
    {
        $userId = 123;
        $ipAddress = '192.168.1.1';

        $this->queryBuilder->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $this->queryBuilder->expects(self::once())
            ->method('from')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();

        $this->queryBuilder->expects(self::exactly(2))
            ->method('createNamedParameter')
            ->willReturnCallback(function (mixed $value, mixed $type) use ($userId, $ipAddress): string {
                if ($value === $userId && Connection::PARAM_INT === $type) {
                    return ':userId';
                }
                if ($value === $ipAddress && Connection::PARAM_STR === $type) {
                    return ':ipAddress';
                }

                return ':param';
            });

        $this->expressionBuilder->expects(self::exactly(2))
            ->method('eq')
            ->willReturnCallback(function (string $field, string $param): string {
                return "$field = $param";
            });

        $this->queryBuilder->expects(self::once())
            ->method('where')
            ->with('user_id = :userId', 'ip_address = :ipAddress')
            ->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(['user_id' => $userId, 'ip_address' => $ipAddress]);

        $this->queryBuilder->expects(self::once())
            ->method('executeQuery')
            ->willReturn($result);

        $found = $this->subject->findByUserAndIp($userId, $ipAddress);

        self::assertTrue($found);
    }

    public function testFindByUserAndIpReturnsFalse(): void
    {
        $userId = 123;
        $ipAddress = '192.168.1.1';

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

        $found = $this->subject->findByUserAndIp($userId, $ipAddress);

        self::assertFalse($found);
    }

    public function testAddUserIp(): void
    {
        $userId = 123;
        $ipAddress = '192.168.1.1';

        $this->queryBuilder->expects(self::once())
            ->method('insert')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();

        $this->queryBuilder->expects(self::once())
            ->method('values')
            ->with([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ])
            ->willReturnSelf();

        $this->queryBuilder->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->subject->addUserIp($userId, $ipAddress);
    }
}
