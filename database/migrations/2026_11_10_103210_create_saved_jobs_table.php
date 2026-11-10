<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gespeicherte Vakanzen je Benutzer: Referenznummer, Auszug (Titel, Arbeitgeber, Ort) und persoenlicher Vermerk.
     */
    public function up(): void
    {
        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('refnr', 100);
            $table->string('title', 300)->nullable();
            $table->string('employer', 300)->nullable();
            $table->string('location', 300)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'refnr']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_jobs');
    }
};
