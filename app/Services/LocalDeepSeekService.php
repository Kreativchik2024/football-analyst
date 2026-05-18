<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LocalDeepSeekService
{
    protected string $baseUrl = 'http://localhost:11434/api/chat';
    protected string $model = 'deepseek-r1:14b';

    public function ask(string $systemPrompt, string $userMessage): ?string
    {
        $response = Http::timeout(120)->post($this->baseUrl, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'stream' => false,
        ]);

        if ($response->successful()) {
            return $response->json('message.content');
        }

        Log::error('Local DeepSeek error: ' . $response->body());
        return null;
    }
}