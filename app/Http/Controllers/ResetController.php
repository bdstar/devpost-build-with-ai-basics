<?php

namespace App\Http\Controllers;

use App\Services\DemoDataReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResetController extends Controller
{
    public const CONFIRMATION = 'RESET';

    public function reset(Request $request, DemoDataReset $reset): RedirectResponse
    {
        if ($request->input('confirmation') !== self::CONFIRMATION) {
            return redirect()->route('dashboard')
                ->with('error', 'Nothing was deleted. Type RESET in capitals to confirm the reset.');
        }

        $removed = collect($reset->reset())
            ->filter()
            ->map(fn ($n, $label) => "{$n} {$label}")
            ->implode(', ');

        return redirect()->route('dashboard')->with('status', 'All project data was reset'
            .($removed ? " ({$removed} removed)" : ' (there was nothing to remove)')
            .'. Your login and the synthetic dataset source files were kept. Start again at Step 1.');
    }
}
