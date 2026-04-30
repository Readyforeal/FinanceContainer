<?php

namespace Tests\Unit\Services;

use App\Services\OllamaService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    private OllamaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.ollama.host' => 'http://ollama-test:11434',
            'services.ollama.model' => 'test-model',
        ]);
        $this->service = new OllamaService();
    }

    public function test_chat_sends_request_and_returns_response(): void
    {
        Http::fake([
            'http://ollama-test:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello from the model!',
                ],
            ]),
        ]);

        $result = $this->service->chat('You are a helpful assistant.', [
            ['role' => 'user', 'content' => 'Hi there'],
        ]);

        $this->assertEquals('Hello from the model!', $result);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['model'] === 'test-model'
                && $body['stream'] === false
                && $body['messages'][0]['role'] === 'system'
                && $body['messages'][0]['content'] === 'You are a helpful assistant.'
                && $body['messages'][1]['role'] === 'user'
                && $body['messages'][1]['content'] === 'Hi there';
        });
    }

    public function test_chat_json_mode_returns_parsed_array(): void
    {
        Http::fake([
            'http://ollama-test:11434/api/chat' => Http::response([
                'message' => [
                    'role' => 'assistant',
                    'content' => '{"key": "value", "number": 42}',
                ],
            ]),
        ]);

        $result = $this->service->chatJson('You are a JSON generator.', [
            ['role' => 'user', 'content' => 'Give me JSON'],
        ]);

        $this->assertIsArray($result);
        $this->assertEquals('value', $result['key']);
        $this->assertEquals(42, $result['number']);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['format'] === 'json'
                && $body['stream'] === false;
        });
    }

    public function test_generate_sends_single_prompt(): void
    {
        Http::fake([
            'http://ollama-test:11434/api/generate' => Http::response([
                'response' => 'Generated text here.',
            ]),
        ]);

        $result = $this->service->generate('Explain gravity', 'You are a physicist.');

        $this->assertEquals('Generated text here.', $result);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['model'] === 'test-model'
                && $body['prompt'] === 'Explain gravity'
                && $body['system'] === 'You are a physicist.'
                && $body['stream'] === false;
        });
    }

    public function test_stream_chat_yields_tokens(): void
    {
        $line1 = json_encode(['message' => ['role' => 'assistant', 'content' => 'Hello']]);
        $line2 = json_encode(['message' => ['role' => 'assistant', 'content' => ' world']]);
        $line3 = json_encode(['message' => ['role' => 'assistant', 'content' => '!'], 'done' => true]);

        Http::fake([
            'http://ollama-test:11434/api/chat' => Http::response(
                $line1 . "\n" . $line2 . "\n" . $line3 . "\n",
                200
            ),
        ]);

        $tokens = [];
        $this->service->streamChat('You are a helper.', [
            ['role' => 'user', 'content' => 'Say hello'],
        ], function (string $token) use (&$tokens) {
            $tokens[] = $token;
        });

        $this->assertEquals(['Hello', ' world', '!'], $tokens);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $body['stream'] === true;
        });
    }
}
