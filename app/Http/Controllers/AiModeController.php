<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AiModeController extends Controller
{
    public function fixtures(Request $request): RedirectResponse
    {
        $request->session()->put(AiMode::SESSION_KEY, 'fixtures');

        return back()->with('status', 'Switched to Demo Fixtures for this session. New AI output will be labelled Demo fixture; nothing already saved was changed.');
    }

    public function live(Request $request): RedirectResponse
    {
        $request->session()->forget(AiMode::SESSION_KEY);
        if (AiMode::configured() !== 'live') {
            return back()->with('error', 'Live AI is not configured. Set AI_MODE=live and GEMINI_API_KEY in .env, then restart the server.');
        }

        return back()->with('status', 'Switched back to Live AI (Gemini).');
    }
}
