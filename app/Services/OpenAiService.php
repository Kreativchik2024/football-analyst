<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OpenAiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1/chat/completions';
    protected string $model;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->model  = env('OPENAI_MODEL', 'gpt-3.5-turbo');
    }

    /**
     * Отправить запрос к ChatGPT и получить ответ.
     *
     * @param string $systemPrompt Инструкция для модели
     * @param string $userMessage Вопрос или данные
     * @return string|null
     */
    public function ask(string $systemPrompt, string $userMessage): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post($this->baseUrl, [
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

        \Illuminate\Support\Facades\Log::error('OpenAI API error: ' . $response->body());
        return null;
    }
}