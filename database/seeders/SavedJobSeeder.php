<?php

namespace Database\Seeders;

use App\Models\SavedJob;
use App\Models\User;
use Illuminate\Database\Seeder;

class SavedJobSeeder extends Seeder
{
    /**
     * Legt fuer einen vorhandenen Benutzer (oder einen neuen) einige gespeicherte Vakanzen an. Wird nicht automatisch aufgerufen.
     */
    public function run(): void
    {
        $user = User::query()->first() ?? User::factory()->create();

        SavedJob::factory()->count(3)->for($user)->create();
        SavedJob::factory()->withNote()->for($user)->create();
    }
}
