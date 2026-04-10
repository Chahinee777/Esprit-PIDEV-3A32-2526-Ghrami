<?php

namespace App\Command;

use App\Service\AiContentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:social-ai-completer',
    description: 'Test Social Media AI Completer with Groq API'
)]
class TestSocialAiCompleterCommand extends Command
{
    private AiContentService $aiContentService;

    public function __construct(AiContentService $aiContentService)
    {
        parent::__construct();
        $this->aiContentService = $aiContentService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🤖 Testing Social Media AI Completer with Groq');

        // Test case 1: Short hobby post
        $io->section('Test 1: Hobby Post Completion');
        $text1 = "J'adore la photographie et je viens de découvrir un nouveau parc magnifique";
        $io->writeln("<info>Original:</info> {$text1}");
        
        try {
            $completion1 = $this->aiContentService->completePostText($text1);
            $io->writeln("<fg=cyan>Completion:</> {$completion1}");
            $io->writeln("<fg=green>Full Text:</> {$text1} {$completion1}");
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test case 2: Technology post
        $io->section('Test 2: Technology Post Completion');
        $text2 = "Je suis en train d'apprendre Docker et c'est vraiment utile";
        $io->writeln("<info>Original:</info> {$text2}");
        
        try {
            $completion2 = $this->aiContentService->completePostText($text2);
            $io->writeln("<fg=cyan>Completion:</> {$completion2}");
            $io->writeln("<fg=green>Full Text:</> {$text2} {$completion2}");
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        // Test case 3: Social post
        $io->section('Test 3: Social Post Completion');
        $text3 = "Que pensez-vous de cette nouvelle fonctionnalité";
        $io->writeln("<info>Original:</info> {$text3}");
        
        try {
            $completion3 = $this->aiContentService->completePostText($text3);
            $io->writeln("<fg=cyan>Completion:</> {$completion3}");
            $io->writeln("<fg=green>Full Text:</> {$text3} {$completion3}");
        } catch (\Throwable $e) {
            $io->error("Error: " . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('✅ All AI Completer tests completed!');

        return Command::SUCCESS;
    }
}
