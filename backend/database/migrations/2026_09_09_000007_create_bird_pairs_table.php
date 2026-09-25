<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bird_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_1_bird_id')->constrained('birds')->restrictOnDelete();
            $table->foreignId('parent_2_bird_id')->constrained('birds')->restrictOnDelete();
            $table->string('pairing_type', 80)->nullable();
            $table->unsignedInteger('species_1_id');
            $table->unsignedInteger('species_2_id');
            $table->string('pairing_status', 40);
            $table->string('compatibility_status', 80)->nullable();
            $table->string('fertility_status', 80)->nullable();
            $table->string('risk_level', 80)->nullable();
            $table->text('warning_summary')->nullable();
            $table->timestamps();

            $table->foreign('species_1_id')->references('id')->on('lovebird_species')->restrictOnDelete();
            $table->foreign('species_2_id')->references('id')->on('lovebird_species')->restrictOnDelete();
            $table->index(['parent_1_bird_id', 'parent_2_bird_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_pairs');
    }
};
