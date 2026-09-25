<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('computation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bird_pair_id')->constrained('bird_pairs')->cascadeOnDelete();
            $table->unsignedBigInteger('prediction_id')->nullable();
            $table->string('status', 40);
            $table->json('compatibility_result');
            $table->json('genetic_compatibility');
            $table->json('overall_compatibility');
            $table->string('fertility_result', 80)->nullable();
            $table->string('risk_level', 80)->nullable();
            $table->json('warnings');
            $table->unsignedSmallInteger('estimated_clutch_count')->nullable();
            $table->json('offspring_probability');
            $table->json('predicted_base_colors');
            $table->json('predicted_visual_mutations');
            $table->json('predicted_split_genes');
            $table->json('predicted_phenotypes');
            $table->text('predicted_appearance')->nullable();
            $table->string('predicted_offspring_image', 255)->nullable();
            $table->json('rbgia_data');
            $table->json('genetic_calculation');
            $table->json('scientific_information');
            $table->json('parent_snapshot');
            $table->timestamps();

            $table->index('prediction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('computation_results');
    }
};
