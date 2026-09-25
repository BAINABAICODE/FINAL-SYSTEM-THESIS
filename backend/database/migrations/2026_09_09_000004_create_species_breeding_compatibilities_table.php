<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('species_breeding_compatibilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('species_1_id');
            $table->unsignedInteger('species_2_id');
            $table->unsignedInteger('species_low_id');
            $table->unsignedInteger('species_high_id');
            $table->boolean('direction_sensitive')->default(false);
            $table->string('compatibility_status', 40);
            $table->string('breeding_type', 80)->nullable();
            $table->string('fertility_status', 80)->nullable();
            $table->string('risk_level', 40)->nullable();
            $table->text('warning_message')->nullable();
            $table->text('scientific_basis')->nullable();
            $table->text('scientific_source')->nullable();
            $table->string('verification_status', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('species_1_id')->references('id')->on('lovebird_species')->restrictOnDelete();
            $table->foreign('species_2_id')->references('id')->on('lovebird_species')->restrictOnDelete();
            $table->unique(['species_low_id', 'species_high_id'], 'species_pair_unique');
        });

        Schema::create('breeding_safety_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lovebird_species_id')->nullable();
            $table->unsignedSmallInteger('minimum_age_months')->nullable();
            $table->unsignedSmallInteger('recommended_min_months')->nullable();
            $table->unsignedSmallInteger('recommended_max_months')->nullable();
            $table->text('warning_below_minimum')->nullable();
            $table->text('warning_outside_range')->nullable();
            $table->text('scientific_source')->nullable();
            $table->string('verification_status', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('lovebird_species_id')->references('id')->on('lovebird_species')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeding_safety_rules');
        Schema::dropIfExists('species_breeding_compatibilities');
    }
};
