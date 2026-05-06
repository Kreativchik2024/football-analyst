<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ApiFootballService
{
    protected string $baseUrl = 'https://v3.football.api-sports.io';
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('API_FOOTBALL_KEY');
    }

    private function request(string $endpoint, array $params = [])
    {
        return Http::withHeaders([
            'x-apisports-key' => $this->apiKey,
        ])
            ->timeout(120)
            ->connectTimeout(10)
            ->retry(3, 2000)
            ->get($this->baseUrl . $endpoint, $params);
    }

    public function getLeagues()
    {
        return Cache::remember('api_football_leagues', 86400, function () {
            return $this->request('/leagues');
        });
    }

    public function getTeams(array $params = [])
    {
        $cacheKey = 'api_football_teams_' . md5(serialize($params));
        return Cache::remember($cacheKey, 86400, function () use ($params) {
            return $this->request('/teams', $params);
        });
    }

    public function getFixtures(array $params = [])
    {
        return $this->request('/fixtures', $params);
    }

    public function getFixtureStatistics(int $fixtureId)
    {
        return $this->request('/fixtures/statistics', [
            'fixture' => $fixtureId,
        ]);
    }

    public function getPredictions(int $fixtureId)
    {
        return $this->request('/predictions', [
            'fixture' => $fixtureId,
        ]);
    }

    // Только ОДИН метод getOdds
    public function getOdds(int $fixtureId)
    {
        return $this->request('/odds', [
            'fixture' => $fixtureId,
        ]);
    }

  public function getBookmakers()
{
    return Cache::remember('api_football_bookmakers', 86400, function () {
        $response = $this->request('/odds/bookmakers');
        if ($response->successful()) {
            return $response->json();       // кэшируем только массив
        }
        return null;
    });
}

    // Новый метод для событий матча
    public function getFixtureEvents(int $fixtureId)
    {
        return $this->request('/fixtures/events', [
            'fixture' => $fixtureId,
        ]);
    }
    public function getInjuries(array $params = [])
{
    return $this->request('/injuries', $params);
}
}