<?php

namespace App\Command;

use App\Service\AiContentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test:social-ai-image-caption',
    description: 'Test Social Media AI Image Captioning with Groq Vision'
)]
class TestSocialAiImageCaptionCommand extends Command
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

        $io->title('🖼️  Testing Social Media AI Image Captioning with Groq Vision');

        // Find an existing test image
        $projectDir = $this->getApplication()->getKernel()->getProjectDir();
        $testImagePath = $projectDir . '/public/images/assets/ghrami-logo.png';

        // Fallback: create simple 1x1 test image
        if (!file_exists($testImagePath)) {
            $testImagePath = sys_get_temp_dir() . '/test_image_' . time() . '.jpg';
            if (!$this->createMinimalTestImage($testImagePath)) {
                $io->error('Could not create test image.');
                return Command::FAILURE;
            }
        }

        $io->section('Test: Image Captioning');
        $io->writeln("<info>Test Image:</> " . basename($testImagePath));

        try {
            $io->writeln("<fg=yellow>Sending image to Groq vision model...</>");
            $caption = $this->aiContentService->analyzeImageForCaption($testImagePath);
            
            $io->writeln("<fg=cyan>Generated Caption:</>");
            $io->writeln($caption);
            
            $io->success('✅ Image captioning successful!');
            
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Create minimal test image using raw JPEG data
     */
    private function createMinimalTestImage(string $path): bool
    {
        // Minimal 1x1 red JPEG (base64 encoded)
        $jpegData = base64_decode(
            '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8VAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCwAA8A/9k='
        );

        if ($jpegData === false || !file_put_contents($path, $jpegData)) {
            return false;
        }

        return file_exists($path);
    }
}
