<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

class OpenAiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1/chat/completions';
    protected string $model;
    protected int $maxRetries = 3;
    protected int $timeout = 30;

    public function __construct()
    {
        $this->apiKey = env('OPENAI_API_KEY');
        $this->model  = env('OPENAI_MODEL', 'gpt-3.5-turbo');
    }

    /**
     * Отправить запрос к ChatGPT с retry логикой
     *
     * @param string $systemPrompt Инструкция для модели
     * @param string $userMessage Вопрос или данные
     * @return string|null
     */
    public function ask(string $systemPrompt, string $userMessage): ?string
    {
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->sendRequest($systemPrompt, $userMessage);
            } catch (ConnectionException $e) {
                Log::warning("OpenAI API connection error (attempt {$attempt}): {$e->getMessage()}");
                
                if ($attempt === $this->maxRetries) {
                    Log::error("OpenAI API failed after {$this->maxRetries} attempts");
                    return null;
                }
                
                // Экспоненциальная задержка
                sleep(2 ** $attempt);
            } catch (\Exception $e) {
                Log::error("OpenAI API error: {$e->getMessage()}");
                return null;
            }
        }

        return null;
    }

    /**
     * Отправить одиночный запрос к API
     */
    protected function sendRequest(string $systemPrompt, string $userMessage): ?string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout($this->timeout)->post($this->baseUrl, [
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

        if ($response->status() === 429) {
            throw new ConnectionException('Rate limited by OpenAI API');
        }

        if ($response->status() >= 500) {
            throw new ConnectionException('OpenAI API server error: ' . $response->status());
        }

        Log::error('OpenAI API error: ' . $response->body());
        return null;
    }
}
