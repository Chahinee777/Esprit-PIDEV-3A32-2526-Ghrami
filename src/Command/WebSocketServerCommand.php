<?php

namespace App\Command;

use App\WebSocket\ChatServer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Worker;

#[AsCommand(name: 'app:websocket:server', description: 'Start the real-time chat WebSocket server')]
class WebSocketServerCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct();
        $this->em = $em;
    }

    protected function configure(): void
    {
        $this->addOption(
            'host',
            'H',
            InputOption::VALUE_OPTIONAL,
            'The host to bind to',
            '0.0.0.0'
        );
        $this->addOption(
            'port',
            'p',
            InputOption::VALUE_OPTIONAL,
            'The port to listen on',
            '9090'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $output->writeln([
            '',
            '╔══════════════════════════════════════════════════════════════╗',
            '║         Ghrami Real-Time Chat WebSocket Server              ║',
            '╚══════════════════════════════════════════════════════════════╝',
            '',
            "<info>Starting WebSocket server on ws://{$host}:{$port}</info>",
            '<comment>Protocol: REGISTER:{userId} | MSG:{fromId}:{toId}:{content}</comment>',
            '<comment>Press Ctrl+C to stop</comment>',
            '',
        ]);

        try {
            // Create WebSocket worker using Workerman
            $worker = new Worker("websocket://{$host}:{$port}");
            
            // Create ChatServer instance
            $chatServer = new ChatServer($this->em);
            
            // Bind event handlers
            $worker->onConnect = [$chatServer, 'onConnect'];
            $worker->onMessage = [$chatServer, 'onMessage'];
            $worker->onClose = [$chatServer, 'onClose'];
            $worker->onError = [$chatServer, 'onError'];

            $output->writeln('<fg=green>✓ WebSocket server started successfully</fg=green>');
            $output->writeln('');

            // Run the worker
            Worker::runAll();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: {$e->getMessage()}</error>");
            return Command::FAILURE;
        }
    }
}
