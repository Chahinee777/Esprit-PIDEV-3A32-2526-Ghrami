<?php

namespace App\Command;

use App\Service\WeeklyDigestService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-weekly-digests',
    description: 'Generates and delivers weekly digests for active users. Schedule for every Monday at 08:00 UTC.'
)]
final class SendWeeklyDigestsCommand extends Command
{
    public function __construct(
        private readonly WeeklyDigestService $weeklyDigestService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Generate the digest without sending email or in-app notifications')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'Limit execution to a single user ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = $input->getOption('user-id');

        $this->weeklyDigestService->sendWeeklyDigests(
            static function (string $message) use ($output): void {
                $output->writeln($message);
            },
            $userId !== null ? (int) $userId : null,
            (bool) $input->getOption('dry-run')
        );

        return Command::SUCCESS;
    }
}
