<?php

namespace App\Http\Controllers;

use App\Http\Requests\CareerEntryRequest;
use App\Models\CareerEntry;
use App\Models\User;
use App\Policies\CareerEntryPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Werdegang-Eintraege (Premium). Fremde Eintraege werden ueber {@see CareerEntryPolicy} mit 403 abgewiesen.
 */
class CareerEntryController extends Controller
{
    public function store(CareerEntryRequest $request): RedirectResponse
    {
        $user = $this->user($request);

        // Das Limit wird unter Zeilensperre erneut geprueft, damit parallele Anfragen es nicht ueberschreiten.
        DB::transaction(function () use ($user, $request): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            abort_unless(Gate::forUser($user)->allows('create', CareerEntry::class), 403);

            $user->careerEntries()->create($request->entryAttributes());
        });

        return back();
    }

    public function update(CareerEntryRequest $request, CareerEntry $careerEntry): RedirectResponse
    {
        $careerEntry->update($request->entryAttributes());

        return back();
    }

    public function destroy(Request $request, CareerEntry $careerEntry): RedirectResponse
    {
        Gate::authorize('delete', $careerEntry);

        $careerEntry->delete();

        return back();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
