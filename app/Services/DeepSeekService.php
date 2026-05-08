<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DeepSeekService
{
    protected string $baseUrl = 'https://api.deepseek.com/v1';
    protected string $apiKey;
    protected string $model = 'deepseek-chat'; // или deepseek-reasoner для задач с рассуждением

    public function __construct()
    {
        $this->apiKey = env('DEEPSEEK_API_KEY');
    }

    public function ask(string $systemPrompt, string $userMessage): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post($this->baseUrl . '/chat/completions', [
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'temperature' => 0.7,
            'max_tokens'  => 500,
        ]);

        if ($response->successful()) {
            return $response->json('choices.0.message.content');
        }

        \Illuminate\Support\Facades\Log::error('DeepSeek API error: ' . $response->body());
        return null;
    }
}