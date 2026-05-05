<?php

namespace App\Command;

use App\Service\DailySummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'test:daily-summary',
    description: 'Test the daily summary service for a user',
)]
class TestDailySummaryCommand extends Command
{
    public function __construct(private DailySummaryService $summaryService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('user-id', InputArgument::OPTIONAL, 'User ID', 8);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userId = (int) $input->getArgument('user-id');
        $output->writeln("<info>Testing daily summary for user {$userId}...</info>");

        try {
            $result = $this->summaryService->getDailySummary($userId);
            
            $output->writeln("<info>✓ Summary generated successfully!</info>");
            $output->writeln("\n<comment>Summary:</comment>");
            $output->writeln($result['summary']);
            
            $output->writeln("\n<comment>Stats:</comment>");
            foreach ($result['stats'] as $key => $value) {
                $output->writeln("  $key: $value");
            }
            
            if (!empty($result['achievements'])) {
                $output->writeln("\n<comment>Achievements:</comment>");
                foreach ($result['achievements'] as $achievement) {
                    $output->writeln("  - $achievement");
                }
            }
            
            if (!empty($result['recommendations'])) {
                $output->writeln("\n<comment>Recommendations:</comment>");
                foreach ($result['recommendations'] as $rec) {
                    $output->writeln("  - $rec");
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            $output->writeln($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
