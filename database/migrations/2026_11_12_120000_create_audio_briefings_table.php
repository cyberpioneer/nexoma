<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audio-Briefings (lokal per Piper erzeugte Sprachdateien). `content_hash` macht die Erzeugung je Benutzer
     * idempotent; `storage_path` ist nur serverseitig relevant und nie fuer den Client bestimmt. Unter PostgreSQL
     * sichert ein CHECK-Constraint den Status ab, das Model kennt dieselben Werte.
     */
    public function up(): void
    {
        Schema::create('audio_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('content_hash', 64);
            $table->text('speech_text');
            $table->string('storage_path', 255)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('error_code', 64)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'content_hash']);
            $table->index(['user_id', 'status', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE audio_briefings ADD CONSTRAINT audio_briefings_status_check CHECK (status IN ('pending', 'processing', 'ready', 'failed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_briefings');
    }
};
