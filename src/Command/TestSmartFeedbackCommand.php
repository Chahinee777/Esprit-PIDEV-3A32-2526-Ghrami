<?php

namespace App\Command;

use App\Service\SmartFeedbackService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:smart-feedback',
    description: 'Test SmartFeedbackService with Groq API'
)]
class TestSmartFeedbackCommand extends Command
{
    public function __construct(
        private readonly SmartFeedbackService $feedbackService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('🧠 Testing Smart Feedback with Groq API');

        // Test case 1: Incorrect answer
        $io->section('Test 1: Incorrect Answer');
        $feedback1 = $this->feedbackService->generateFeedback(
            'What is Docker?',
            'Docker is a database management system.',
            'Learning Docker - Lesson 1',
            'Docker is a containerization platform...'
        );
        $io->info("Question: What is Docker?");
        $io->info("Answer: Docker is a database management system.");
        $io->block($feedback1['feedback'], 'FEEDBACK', 'fg=red;bg=black', ' ', true);
        $io->text("Score: {$feedback1['score']}/100");
        $io->text("Is Correct: " . ($feedback1['isCorrect'] ? 'Yes ✅' : 'No ❌'));

        // Test case 2: Partially correct answer
        $io->section('Test 2: Partially Correct Answer');
        $feedback2 = $this->feedbackService->generateFeedback(
            'What is a Git commit?',
            'A commit saves changes to your local repository.',
            'Learning Git - Version Control',
            'A commit captures a snapshot of changes and saves them to your repository history.'
        );
        $io->info("Question: What is a Git commit?");
        $io->info("Answer: A commit saves changes to your local repository.");
        $io->block($feedback2['feedback'], 'FEEDBACK', 'fg=green;bg=black', ' ', true);
        $io->text("Score: {$feedback2['score']}/100");

        // Test case 3: Good answer
        $io->section('Test 3: Good Answer');
        $feedback3 = $this->feedbackService->generateFeedback(
            'Explain REST APIs.',
            'REST APIs use HTTP methods (GET, POST, PUT, DELETE) to interact with resources identified by URLs.',
            'Learning Web APIs'
        );
        $io->info("Question: Explain REST APIs.");
        $io->info("Answer: REST APIs use HTTP methods (GET, POST, PUT, DELETE) to interact with resources identified by URLs.");
        $io->block($feedback3['feedback'], 'FEEDBACK', 'fg=green;bg=black', ' ', true);
        $io->text("Score: {$feedback3['score']}/100");

        // Show suggestions
        $io->section('Suggestions from Test 1');
        foreach ($feedback1['suggestions'] as $i => $suggestion) {
            $io->text(($i + 1) . ". " . $suggestion);
        }

        $io->success('✅ Smart Feedback test completed!');
        return Command::SUCCESS;
    }
}
