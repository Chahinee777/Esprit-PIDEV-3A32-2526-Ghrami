<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class StoriesCleanupCommand extends Command
{
    protected static $defaultName = 'stories:cleanup';
    protected static $defaultDescription = 'Delete expired stories (older than 24 hours)';

    public function __construct(
        private Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('stories:cleanup')
            ->setDescription('Delete expired stories (older than 24 hours)')
            ->setHelp('This command removes all stories that have expired (exceeded 24 hours)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        try {
            $now = new \DateTime();
            
            // Delete stories that have expired (expires_at is in the past)
            $result = $this->connection->executeStatement(
                'DELETE FROM stories WHERE expires_at IS NOT NULL AND expires_at <= ?',
                [$now->format('Y-m-d H:i:s')]
            );
            
            $io->success("✓ Deleted $result expired stories");
            
            // Also check for stories without expires_at set (set default 24h from creation)
            $updated = $this->connection->executeStatement(
                'UPDATE stories SET expires_at = DATE_ADD(created_at, INTERVAL 24 HOUR) WHERE expires_at IS NULL'
            );
            
            if ($updated > 0) {
                $io->info("✓ Updated $updated stories with missing expiry dates");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error during stories cleanup: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
