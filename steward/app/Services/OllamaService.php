<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
    private string $host;
    private string $model;

    public function __construct()
    {
        $this->host = config('services.ollama.host', 'http://ollama:11434');
        $this->model = config('services.ollama.model', 'llama3.1:70b-instruct-q4_K_M');
    }

    /**
     * Send a chat request and return the assistant's response content.
     */
    public function chat(string $systemPrompt, array $messages): string
    {
        $payload = $this->buildChatPayload($systemPrompt, $messages);
        $payload['stream'] = false;

        $response = Http::timeout(300)->post("{$this->host}/api/chat", $payload);

        return $response->json('message.content');
    }

    /**
     * Send a chat request in JSON mode, returning parsed PHP array.
     */
    public function chatJson(string $systemPrompt, array $messages): array
    {
        $payload = $this->buildChatPayload($systemPrompt, $messages);
        $payload['stream'] = false;
        $payload['format'] = 'json';

        $response = Http::timeout(300)->post("{$this->host}/api/chat", $payload);

        $content = $response->json('message.content');

        return json_decode($content, true);
    }

    /**
     * Send a single-turn generate request and return the response text.
     */
    public function generate(string $prompt, string $system = ''): string
    {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'stream' => false,
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }

        $response = Http::timeout(300)->post("{$this->host}/api/generate", $payload);

        return $response->json('response');
    }

    /**
     * Stream a chat request, calling $onToken for each token chunk received.
     */
    public function streamChat(string $systemPrompt, array $messages, callable $onToken): void
    {
        $payload = $this->buildChatPayload($systemPrompt, $messages);
        $payload['stream'] = true;

        $response = Http::timeout(300)->post("{$this->host}/api/chat", $payload);

        $body = $response->body();

        foreach (explode("\n", $body) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);
            if ($data === null) {
                continue;
            }

            $token = $data['message']['content'] ?? null;
            if ($token !== null && $token !== '') {
                $onToken($token);
            }
        }
    }

    /**
     * Build the common chat payload with system message prepended.
     */
    private function buildChatPayload(string $systemPrompt, array $messages): array
    {
        $allMessages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $messages
        );

        return [
            'model' => $this->model,
            'messages' => $allMessages,
        ];
    }
}
