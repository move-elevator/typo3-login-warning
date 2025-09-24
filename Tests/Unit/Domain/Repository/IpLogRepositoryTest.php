<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "typo3_login_warning".
 *
 * Copyright (C) 2025 Konrad Michalik <hej@konradmichalik.dev>
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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Domain\Repository;

use Doctrine\DBAL\Result;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

final class IpLogRepositoryTest extends TestCase
{
    private ConnectionPool&MockObject $connectionPool;
    private QueryBuilder&MockObject $queryBuilder;
    private ExpressionBuilder&MockObject $expressionBuilder;
    private Result&MockObject $result;
    private IpLogRepository $subject;

    protected function setUp(): void
    {
        $this->connectionPool = $this->createMock(ConnectionPool::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->expressionBuilder = $this->createMock(ExpressionBuilder::class);
        $this->result = $this->createMock(Result::class);

        $this->subject = new IpLogRepository($this->connectionPool);

        $this->connectionPool
            ->method('getQueryBuilderForTable')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->method('expr')->willReturn($this->expressionBuilder);
    }

    public function testFindByUserAndIpReturnsTrueWhenRecordExists(): void
    {
        $userId = 123;
        $ipAddress = 'test-ip-hash';

        $this->setupQueryBuilderForSelect();

        $this->expressionBuilder
            ->expects(self::exactly(2))
            ->method('eq')
            ->willReturnCallback(function (string $field, string $parameter) {
                return match ($field) {
                    'user_id' => 'user_id = :userId',
                    'ip_address' => 'ip_address = :ipAddress',
                    default => throw new \InvalidArgumentException("Unexpected field: $field", 1966443699),
                };
            });

        $this->queryBuilder
            ->expects(self::exactly(2))
            ->method('createNamedParameter')
            ->willReturnCallback(function (mixed $value, int $type) use ($userId, $ipAddress) {
                return match (true) {
                    $value === $userId => ':userId',
                    $value === $ipAddress => ':ipAddress',
                    default => throw new \InvalidArgumentException("Unexpected parameter: $value", 9357791920),
                };
            });

        $this->queryBuilder
            ->expects(self::once())
            ->method('where')
            ->with('user_id = :userId', 'ip_address = :ipAddress')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects(self::once())
            ->method('executeQuery')
            ->willReturn($this->result);

        $this->result
            ->expects(self::once())
            ->method('fetchAssociative')
            ->willReturn(['uid' => 1, 'user_id' => $userId, 'ip_address' => $ipAddress]);

        $result = $this->subject->findByUserAndIp($userId, $ipAddress);

        self::assertTrue($result);
    }

    public function testFindByUserAndIpReturnsFalseWhenRecordDoesNotExist(): void
    {
        $userId = 123;
        $ipAddress = 'test-ip-hash';

        $this->setupQueryBuilderForSelect();

        $this->expressionBuilder
            ->method('eq')
            ->willReturn('mock-expression');

        $this->queryBuilder
            ->method('createNamedParameter')
            ->willReturn(':param');

        $this->queryBuilder
            ->method('where')
            ->willReturnSelf();

        $this->queryBuilder
            ->method('executeQuery')
            ->willReturn($this->result);

        $this->result
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->subject->findByUserAndIp($userId, $ipAddress);

        self::assertFalse($result);
    }

    public function testAddUserIpInsertsRecord(): void
    {
        $userId = 123;
        $ipAddress = 'test-ip-hash';

        $this->queryBuilder
            ->expects(self::once())
            ->method('insert')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects(self::once())
            ->method('values')
            ->with([
                'user_id' => $userId,
                'ip_address' => $ipAddress,
            ])
            ->willReturnSelf();

        $this->queryBuilder
            ->expects(self::once())
            ->method('executeStatement');

        $this->subject->addUserIp($userId, $ipAddress);
    }

    private function setupQueryBuilderForSelect(): void
    {
        $this->queryBuilder
            ->expects(self::once())
            ->method('select')
            ->with('*')
            ->willReturnSelf();

        $this->queryBuilder
            ->expects(self::once())
            ->method('from')
            ->with('tx_typo3loginwarning_iplog')
            ->willReturnSelf();
    }
}
