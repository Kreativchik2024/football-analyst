<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class RapidApiService
{
    protected string $baseUrl = 'https://free-football-soccer-videos.p.rapidapi.com';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('RAPIDAPI_KEY');
    }

    /**
     * Получить последние видео (хайлайты или голы) по всем лигам.
     */
    public function getLatestVideos()
    {
        $response = Http::withHeaders([
            'X-RapidAPI-Host' => 'free-football-soccer-videos.p.rapidapi.com',
            'X-RapidAPI-Key' => $this->apiKey,
        ])->get($this->baseUrl);

        if ($response->successful()) {
            return $response->json();
        }

        return [];
    }
}