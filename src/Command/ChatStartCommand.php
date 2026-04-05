<?php

namespace App\Command;

use App\Service\ChatSocketServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:chat:start',
    description: 'Start the real-time chat TCP socket server (port 9090)',
    hidden: false,
)]
class ChatStartCommand extends Command
{
    public function __construct(private ChatSocketServer $chatServer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>[ChatServer] Starting socket server...</info>');
        $output->writeln('<comment>Press Ctrl+C to stop.</comment>');

        try {
            $this->chatServer->start();
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }
}
