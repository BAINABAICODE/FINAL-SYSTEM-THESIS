<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bird_grandparents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bird_id')->constrained('birds')->cascadeOnDelete();
            $table->string('role', 32);
            $table->unsignedInteger('species_id')->nullable();
            $table->foreignId('base_color_id')->nullable()->constrained('base_colors')->nullOnDelete();
            $table->foreignId('visual_mutation_id')->nullable()->constrained('visual_mutations')->nullOnDelete();
            $table->foreignId('split_gene_id')->nullable()->constrained('split_genes')->nullOnDelete();
            $table->timestamps();

            $table->unique(['bird_id', 'role']);

            $table->foreign('species_id')
                ->references('id')
                ->on('lovebird_species')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_grandparents');
    }
};
