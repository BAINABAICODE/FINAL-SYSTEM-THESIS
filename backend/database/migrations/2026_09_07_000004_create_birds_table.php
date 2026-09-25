<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('birds', function (Blueprint $table) {
            $table->id();
            $table->string('bird_id', 80)->unique();
            $table->unsignedSmallInteger('age_months');
            $table->unsignedInteger('species_id');
            $table->string('sex', 10);
            $table->foreignId('base_color_id')->nullable()->constrained('base_colors')->nullOnDelete();
            $table->foreignId('visual_mutation_id')->nullable()->constrained('visual_mutations')->nullOnDelete();
            $table->foreignId('split_gene_id')->nullable()->constrained('split_genes')->nullOnDelete();
            $table->timestamps();

            $table->foreign('species_id')
                ->references('id')
                ->on('lovebird_species')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('birds');
    }
};
