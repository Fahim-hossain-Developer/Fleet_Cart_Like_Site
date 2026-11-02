<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Actions\Tracking;
use Illuminate\Http\Request;

final class TrackingController extends Controller
{
    public function consent(Request $r): mixed
    {
        $d = $r->validate(['accepted' => 'required|boolean']);
        app(Tracking::class)->choose((bool) $d['accepted']);

        return response()->json(['accepted' => (bool) app(Tracking::class)->consent()])->header('Cache-Control', 'no-store');
    }

    public function events(): mixed
    {
        return response()->json(['config' => app(Tracking::class)->consent() ? app(Tracking::class)->publicConfig() : [], 'events' => app(Tracking::class)->browserEvents()])->header('Cache-Control', 'no-store');
    }
}
