<?php

namespace App\Http\Controllers;

use App\Http\Requests\OptimizeBioRequest;
use App\Http\Requests\UpdateCareerProfileRequest;
use App\Http\Resources\CareerEntryResource;
use App\Http\Resources\CareerProfileResource;
use App\Models\CareerEntry;
use App\Models\User;
use App\Services\Ai\BioOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Profil und Werdegang des angemeldeten Benutzers (Premium, Middleware `subscribed`). Es gibt keine ID in
 * der Route: Gelesen und geschrieben wird immer nur der eigene Datensatz.
 */
class CareerProfileController extends Controller
{
    /**
     * Eigenes Profil und eigene Werdegang-Eintraege (neueste zuerst), nur Whitelist-Felder.
     */
    public function show(Request $request): Response
    {
        $user = $this->user($request);

        return Inertia::render('Profile/Edit', [
            'profile' => CareerProfileResource::make($user)->resolve($request),
            'careerEntries' => CareerEntryResource::collection(
                $user->careerEntries()->orderByDesc('from_date')->orderByDesc('id')->limit(CareerEntry::MAX_PER_USER)->get(),
            )->resolve($request),
            'maxCareerEntries' => CareerEntry::MAX_PER_USER,
            'maxBioLength' => User::BIO_MAX_LENGTH,
            'searchRadii' => User::SEARCH_RADII,
        ]);
    }

    /**
     * Speichert das Profil; die Spalten sind nicht massenzuweisbar und werden gezielt aus den validierten Feldern gesetzt.
     */
    public function update(UpdateCareerProfileRequest $request): RedirectResponse
    {
        $this->user($request)->forceFill($request->profileAttributes())->save();

        return back();
    }

    /**
     * Liefert eine KI-optimierte Fassung des uebergebenen Profiltexts (wird nicht gespeichert).
     */
    public function optimizeBio(OptimizeBioRequest $request, BioOptimizer $optimizer): JsonResponse
    {
        try {
            $bio = $optimizer->optimize(
                (string) $request->validated('bio'),
                (string) $request->validated('preferred_language'),
            );
        } catch (Throwable $exception) {
            // Nur die Klasse protokollieren: Anfrage, Antwort und Zugangsdaten gehoeren nicht ins Log.
            Log::error('KI-Optimierung des Profiltexts fehlgeschlagen.', ['exception' => $exception::class]);

            return response()->json(['message' => __('ui.profile.ai.error')], 503);
        }

        return response()->json(['bio' => $bio]);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
