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

namespace MoveElevator\Typo3LoginWarning\Tests\Unit\Command;

use MoveElevator\Typo3LoginWarning\Command\CleanupIpLogCommand;
use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function abs;
use function time;

/**
 * CleanupIpLogCommandTest.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class CleanupIpLogCommandTest extends TestCase
{
    private IpLogRepository&MockObject $ipLogRepository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->ipLogRepository = $this->createMock(IpLogRepository::class);
        $this->commandTester = new CommandTester(new CleanupIpLogCommand($this->ipLogRepository));
    }

    public function testDeletesEntriesWithDefaultRetention(): void
    {
        $this->ipLogRepository
            ->expects(self::once())
            ->method('deleteEntriesLastSeenBefore')
            ->with(self::callback(
                static fn (int $threshold): bool => abs($threshold - (time() - 365 * 86400)) < 60,
            ))
            ->willReturn(5);

        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Deleted 5 IP log entries', $this->commandTester->getDisplay());
    }

    public function testDeletesEntriesWithCustomRetention(): void
    {
        $this->ipLogRepository
            ->expects(self::once())
            ->method('deleteEntriesLastSeenBefore')
            ->with(self::callback(
                static fn (int $threshold): bool => abs($threshold - (time() - 30 * 86400)) < 60,
            ))
            ->willReturn(2);

        $exitCode = $this->commandTester->execute(['--days' => '30']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('more than 30 days', $this->commandTester->getDisplay());
    }

    public function testDryRunOnlyCountsEntries(): void
    {
        $this->ipLogRepository
            ->expects(self::once())
            ->method('countEntriesLastSeenBefore')
            ->with(self::callback(static fn (int $threshold): bool => $threshold < time()))
            ->willReturn(7);

        $this->ipLogRepository
            ->expects(self::never())
            ->method('deleteEntriesLastSeenBefore');

        $exitCode = $this->commandTester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('7 IP log entries would be deleted', $this->commandTester->getDisplay());
    }

    public function testFailsForNonPositiveDays(): void
    {
        $this->ipLogRepository
            ->expects(self::never())
            ->method('deleteEntriesLastSeenBefore');

        $exitCode = $this->commandTester->execute(['--days' => '0']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('positive integer', $this->commandTester->getDisplay());
    }
}
