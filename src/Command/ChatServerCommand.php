<?php

namespace App\Command;

use App\Service\ChatServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:chat:server',
    description: 'Start/stop the real-time chat server (TCP port 9090)',
)]
class ChatServerCommand extends Command
{
    public function __construct(private readonly ChatServer $chatServer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('stop', 's', InputOption::VALUE_NONE, 'Stop the running server')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('stop')) {
            $io->info('Stopping chat server...');
            $this->chatServer->stop();
            $io->success('Chat server stopped');
            return Command::SUCCESS;
        }

        $io->section('🚀 Starting Ghrami Chat Server');
        $io->info('Protocol: TCP on port 9090');
        $io->info('Format: MSG:<fromId>:<toId>:<content>');
        $io->info('');
        $io->info('Press Ctrl+C to stop the server');
        $io->info('');

        $this->chatServer->start();

        if (!$this->chatServer->isRunning()) {
            $io->error('Failed to start chat server');
            return Command::FAILURE;
        }

        $io->success('Chat server started successfully!');

        return Command::SUCCESS;
    }
}
