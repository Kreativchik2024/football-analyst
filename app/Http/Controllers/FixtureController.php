<?php

namespace App\Http\Controllers;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Team;
use Illuminate\Http\Request;

class FixtureController extends Controller
{
 public function past(Request $request)
{
    $countries = League::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');

    $country   = $request->input('country');
    $leagueId  = $request->input('league_id');
    $from      = $request->input('from');
    $to        = $request->input('to');
    $teamName  = $request->input('team');

    $leagues = collect();
    if ($country) {
        $leagues = League::where('country', $country)->orderBy('name')->get();
    }

    $fixturesQuery = Fixture::with([
        'homeTeam',
        'awayTeam',
        'league',
        'matchStatistics',   // ← обязательно
        'odds',              // ← обязательно
        'matchEvents'        // ← обязательно
    ])
    ->where('starting_at', '<', now())
    ->whereIn('status', ['FT', 'AET', 'PEN']);

    if ($leagueId) {
        $fixturesQuery->where('league_id', $leagueId);
    }
    if ($from) {
        $fixturesQuery->where('starting_at', '>=', $from);
    }
    if ($to) {
        $fixturesQuery->where('starting_at', '<=', $to);
    }
    if ($teamName) {
        $teamIds = Team::where('name', 'like', '%' . $teamName . '%')->pluck('id');
        if ($teamIds->isNotEmpty()) {
            $fixturesQuery->where(function ($q) use ($teamIds) {
                $q->whereIn('home_team_id', $teamIds)
                  ->orWhereIn('away_team_id', $teamIds);
            });
        } else {
            $fixturesQuery->whereRaw('1 = 0');
        }
    }

    $fixtures = $fixturesQuery->orderBy('starting_at', 'desc')
                ->paginate(20)
                ->withQueryString();

    return view('fixtures.past', compact(
        'countries', 'leagues', 'fixtures',
        'country', 'leagueId', 'from', 'to', 'teamName'
    ));
}
public function upcoming(Request $request)
{
    $countries = League::whereNotNull('country')->distinct()->orderBy('country')->pluck('country');

    $country   = $request->input('country');
    $leagueId  = $request->input('league_id');
    $from      = $request->input('from');
    $to        = $request->input('to');
    $teamName  = $request->input('team');

    $leagues = collect();
    if ($country) {
        $leagues = League::where('country', $country)->orderBy('name')->get();
    }

    $fixturesQuery = Fixture::with([
    'homeTeam',
    'awayTeam',
    'league',
    'matchStatistics',   // ← должно быть
    'odds',              // ← должно быть
    'matchEvents'        // ← должно быть
    ])
        ->where('starting_at', '>=', now())
        ->whereIn('status', ['NS', 'TBD', 'LIVE', '1H', '2H', 'HT'])
        ->orderBy('starting_at');

    if ($leagueId) {
        $fixturesQuery->where('league_id', $leagueId);
    }
    if ($from) {
        $fixturesQuery->where('starting_at', '>=', $from);
    }
    if ($to) {
        $fixturesQuery->where('starting_at', '<=', $to);
    }
    if ($teamName) {
        $teamIds = Team::where('name', 'like', '%' . $teamName . '%')->pluck('id');
        if ($teamIds->isNotEmpty()) {
            $fixturesQuery->where(function ($q) use ($teamIds) {
                $q->whereIn('home_team_id', $teamIds)
                  ->orWhereIn('away_team_id', $teamIds);
            });
        } else {
            $fixturesQuery->whereRaw('1 = 0');
        }
    }

    $fixtures = $fixturesQuery->paginate(20)->withQueryString();

    return view('fixtures.upcoming', compact(
        'countries', 'leagues', 'fixtures',
        'country', 'leagueId', 'from', 'to', 'teamName'
    ));
}
}