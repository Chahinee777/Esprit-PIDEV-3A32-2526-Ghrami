<?php

namespace App\Command;

use App\Service\HobbyCoachService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:hobby-coach',
    description: 'Test HobbyCoachService with Groq API'
)]
class TestHobbyCoachCommand extends Command
{
    private HobbyCoachService $coachService;

    public function __construct(HobbyCoachService $coachService)
    {
        parent::__construct();
        $this->coachService = $coachService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🎓 Testing HobbyCoachService with Groq');

        // Test case 1: Request motivation
        $io->section('Test 1: Motivation Request');
        $systemContext = "You are an enthusiastic AI hobby coach called 'Hobby Coach'. Help users with personalized tips and motivation. Be friendly and encouraging. Keep responses under 80 words.";
        
        $message1 = "I'm feeling tired of my hobby practice. Can you motivate me?";
        $io->writeln("<info>User:</info> {$message1}");
        
        $response1 = $this->coachService->chat($message1, $systemContext);
        $io->writeln("<fg=cyan>Coach:</> {$response1}");

        // Test case 2: Request a tip
        $io->section('Test 2: Skill Improvement Tip');
        $message2 = "I want to improve my drawing skills faster. What's a good strategy?";
        $io->writeln("<info>User:</info> {$message2}");
        
        $response2 = $this->coachService->chat($message2, $systemContext);
        $io->writeln("<fg=cyan>Coach:</> {$response2}");

        // Test case 3: Achievement celebration
        $io->section('Test 3: Achievement Celebration');
        $message3 = "I just completed my first 30-day challenge in guitar! 🎸";
        $io->writeln("<info>User:</info> {$message3}");
        
        $response3 = $this->coachService->chat($message3, $systemContext);
        $io->writeln("<fg=cyan>Coach:</> {$response3}");

        $io->success('✅ All HobbyCoachService tests completed!');

        return Command::SUCCESS;
    }
}
