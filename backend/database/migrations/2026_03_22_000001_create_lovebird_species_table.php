<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lovebird_species', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            $table->string('common_name', 100);
            $table->string('alternate_names', 150)->nullable();
            $table->string('scientific_name', 100)->unique();
            $table->string('species_group', 20);
            $table->boolean('has_eye_ring');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lovebird_species');
    }
};
