<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$key = $_ENV['API_FOOTBALL_KEY'];

$url = "https://v3.football.api-sports.io/leagues?search=World%20Cup%20Qualifying";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-apisports-key: ' . $key]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ($httpcode == 200) {
    $data = json_decode($response, true);
    if (empty($data['response'])) {
        echo "Лиги квалификации не найдены.\n";
    } else {
        echo "Найдены лиги квалификации:\n";
        foreach ($data['response'] as $league) {
            echo "ID: {$league['league']['id']} - {$league['league']['name']}\n";
        }
    }
} else {
    echo "Ошибка HTTP: $httpcode\n";
}