<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientBalanceException;
use App\Models\AiBalance;
use App\Models\Fixture;
use App\Models\UserBalance;
use App\Models\UserPrediction;
use App\Models\ValueBet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CapperCornerController extends Controller
{
    public function index()
    {
        $upcomingFixtures = Fixture::with(['homeTeam', 'awayTeam', 'odds'])
            ->where('starting_at', '>=', now())
            ->whereIn('status', ['NS', 'TBD', 'PST'])
            ->orderBy('starting_at')
            ->limit(10)
            ->get();

        $liveFixtures = Fixture::with(['homeTeam', 'awayTeam', 'odds'])
            ->whereIn('status', ['LIVE', '1H', 'HT', '2H', 'ET'])
            ->orderBy('starting_at')
            ->limit(10)
            ->get();

        $aiBets = ValueBet::with('fixture.homeTeam', 'fixture.awayTeam', 'odd')
            ->where('type', 'prematch')
            ->where('status', 'pending')
            ->orderBy('expected_value', 'desc')
            ->limit(10)
            ->get();

        $aiLiveBets = ValueBet::with('fixture.homeTeam', 'fixture.awayTeam', 'odd')
            ->where('type', 'live')
            ->where('status', 'pending')
            ->orderBy('expected_value', 'desc')
            ->limit(10)
            ->get();

        // Топ пользователей (без кэша)
        $topUsers = UserBalance::with('user')
            ->orderBy('balance', 'desc')
            ->limit(10)
            ->get();

        $latestUserBets = UserPrediction::with('user', 'fixture.homeTeam', 'fixture.awayTeam')
            ->latest()
            ->limit(15)
            ->get();

        // Баланс текущего пользователя
        $userBalance = Auth::check() ? Auth::user()->getBalanceResult() : 100000;

        $aiBalance = AiBalance::getBalance();

        return view('capper-corner', compact(
            'upcomingFixtures', 'liveFixtures', 'aiBets', 'aiLiveBets',
            'topUsers', 'latestUserBets', 'userBalance', 'aiBalance'
        ));
    }

    public function placeBet(Request $request)
    {
        $request->validate([
            'fixture_id' => 'required|exists:fixtures,id',
            'market'     => 'required|in:1x2,over_under_2.5,btts',
            'outcome'    => 'required',
            'stake'      => 'required|numeric|min:1',
        ]);

        $fixture = Fixture::findOrFail($request->fixture_id);

        $odds = $fixture->odds()
            ->where('market', $request->market)
            ->where('outcome', $request->outcome)
            ->max('value');

        if (!$odds) {
            return back()->with('error', 'Коэффициент для выбранного исхода недоступен.');
        }

        $userBalance = UserBalance::firstOrCreate(
            ['user_id' => Auth::id()],
            ['balance' => 100000]
        );

        if ($userBalance->balance < $request->stake) {
            throw new InsufficientBalanceException();
        }

        $userBalance->balance -= $request->stake;
        $userBalance->save();

        UserPrediction::create([
            'user_id'    => Auth::id(),
            'fixture_id' => $fixture->id,
            'market'     => $request->market,
            'outcome'    => $request->outcome,
            'stake'      => $request->stake,
            'odds'       => $odds,
            'status'     => 'pending',
        ]);

        return back()->with('success', 'Ставка размещена!');
    }
}