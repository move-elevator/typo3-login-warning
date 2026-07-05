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

namespace MoveElevator\Typo3LoginWarning\Command;

use MoveElevator\Typo3LoginWarning\Domain\Repository\IpLogRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function sprintf;

/**
 * CleanupIpLogCommand.
 *
 * @author Konrad Michalik <km@move-elevator.de>
 * @license GPL-2.0-or-later
 */
final class CleanupIpLogCommand extends Command
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(private readonly IpLogRepository $ipLogRepository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('typo3loginwarning:iplog:cleanup')
            ->setDescription('Deletes IP log entries that have not been seen for a given number of days.')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Delete entries whose last sighting is older than this number of days', '365')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report how many entries would be deleted');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('The --days option must be a positive integer.');

            return Command::FAILURE;
        }

        $threshold = time() - $days * self::SECONDS_PER_DAY;

        if (true === $input->getOption('dry-run')) {
            $count = $this->ipLogRepository->countEntriesLastSeenBefore($threshold);
            $io->writeln(sprintf('%d IP log entries would be deleted (not seen for more than %d days).', $count, $days));

            return Command::SUCCESS;
        }

        $deleted = $this->ipLogRepository->deleteEntriesLastSeenBefore($threshold);
        $io->writeln(sprintf('Deleted %d IP log entries (not seen for more than %d days).', $deleted, $days));

        return Command::SUCCESS;
    }
}
