<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vom automatischen Matcher bereits gefundene Referenznummern je Benutzer. Bleibt bestehen, auch wenn der
     * Benutzer die gespeicherte Vakanz loescht, damit sie nie erneut vorgeschlagen oder im Briefing erwaehnt wird.
     */
    public function up(): void
    {
        Schema::create('seen_job_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('refnr', 100);
            $table->timestamps();

            $table->unique(['user_id', 'refnr']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seen_job_references');
    }
};
