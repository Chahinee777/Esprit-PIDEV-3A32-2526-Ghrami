<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'test:groq-api',
    description: 'Test Groq API connection and authentication'
)]
class TestGroqApiCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $apiKey = $_ENV['GROQ_API_KEY'] ?? '';

        if (empty($apiKey)) {
            $output->writeln('<error>❌ GROQ_API_KEY not set in .env</error>');
            return Command::FAILURE;
        }

        $output->writeln('<info>Testing Groq API Connection...</info>');
        $output->writeln("API Key: " . substr($apiKey, 0, 10) . "...");
        $output->writeln('');

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => 'llama-3.1-8b-instant',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a helpful assistant. Respond with WORKING.'
                            ],
                            [
                                'role' => 'user',
                                'content' => 'Test if you can respond.'
                            ]
                        ],
                        'max_tokens' => 50,
                    ],
                    'timeout' => 15,
                ]
            );

            $statusCode = $response->getStatusCode();
            $output->writeln("<info>✅ HTTP Status: {$statusCode}</info>");

            $data = $response->toArray();

            if (isset($data['error'])) {
                $output->writeln('<error>❌ API Error Response:</error>');
                $output->writeln(json_encode($data['error'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                return Command::FAILURE;
            }

            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                $output->writeln('<info>✅ API Response:</info>');
                $output->writeln($content);
                return Command::SUCCESS;
            }

            $output->writeln('<error>❌ Unexpected response format:</error>');
            $output->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return Command::FAILURE;
        } catch (\Exception $e) {
            $output->writeln('<error>❌ Exception occurred:</error>');
            $output->writeln("Message: " . $e->getMessage());
            $output->writeln("Code: " . $e->getCode());
            $output->writeln("File: " . $e->getFile() . ":" . $e->getLine());

            if (method_exists($e, 'getResponse')) {
                $response = $e->getResponse();
                if ($response) {
                    $output->writeln("\n<error>Response Body:</error>");
                    $output->writeln($response->getContent(false));
                }
            }

            return Command::FAILURE;
        }
    }
}
