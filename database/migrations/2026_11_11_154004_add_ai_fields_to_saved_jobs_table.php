<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quelle und KI-Bewertung gespeicherter Vakanzen. Bestehende Zeilen gelten als `manual` (Default).
     * Unter PostgreSQL sichern CHECK-Constraints die Wertebereiche in der Datenbank ab; das Model prueft
     * dieselben Regeln beim Speichern (auch fuer andere Treiber, z. B. SQLite in Tests).
     */
    public function up(): void
    {
        Schema::table('saved_jobs', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('note');
            $table->string('ai_summary', 255)->nullable()->after('source');
            $table->unsignedTinyInteger('temperature')->nullable()->after('ai_summary');
            $table->unsignedSmallInteger('distance_km')->nullable()->after('temperature');
            $table->timestamp('briefed_at')->nullable()->after('distance_km');

            $table->index(['user_id', 'source', 'briefed_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE saved_jobs ADD CONSTRAINT saved_jobs_source_check CHECK (source IN ('manual', 'ai_matcher'))");
            DB::statement('ALTER TABLE saved_jobs ADD CONSTRAINT saved_jobs_temperature_check CHECK (temperature IS NULL OR temperature BETWEEN 1 AND 10)');
            DB::statement('ALTER TABLE saved_jobs ADD CONSTRAINT saved_jobs_distance_check CHECK (distance_km IS NULL OR distance_km >= 0)');
            DB::statement("ALTER TABLE saved_jobs ADD CONSTRAINT saved_jobs_ai_fields_only_for_matcher_check CHECK (source = 'ai_matcher' OR (ai_summary IS NULL AND temperature IS NULL AND distance_km IS NULL AND briefed_at IS NULL))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE saved_jobs DROP CONSTRAINT IF EXISTS saved_jobs_ai_fields_only_for_matcher_check');
            DB::statement('ALTER TABLE saved_jobs DROP CONSTRAINT IF EXISTS saved_jobs_distance_check');
            DB::statement('ALTER TABLE saved_jobs DROP CONSTRAINT IF EXISTS saved_jobs_temperature_check');
            DB::statement('ALTER TABLE saved_jobs DROP CONSTRAINT IF EXISTS saved_jobs_source_check');
        }

        Schema::table('saved_jobs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'source', 'briefed_at']);
            $table->dropColumn(['source', 'ai_summary', 'temperature', 'distance_km', 'briefed_at']);
        });
    }
};
