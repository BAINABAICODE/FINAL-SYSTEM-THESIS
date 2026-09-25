<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visual_mutations', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('visual_mutations', function (Blueprint $table) {
            $table->unsignedInteger('lovebird_species_id')->nullable()->after('id');
            $table->string('species_name', 100)->nullable()->after('lovebird_species_id');
            $table->string('scientific_name', 100)->nullable()->after('species_name');
            $table->string('series', 80)->nullable()->after('name');
            $table->text('sf')->nullable()->after('series');
            $table->text('df')->nullable()->after('sf');
            $table->text('allele')->nullable()->after('df');
            $table->text('genotype')->nullable()->after('allele');
            $table->string('genetic_code', 64)->nullable()->after('genotype');
            $table->string('inheritance_type', 80)->nullable()->after('genetic_code');
            $table->text('phenotype')->nullable()->after('inheritance_type');
            $table->string('verification_status', 64)->nullable()->after('phenotype');
            $table->text('scientific_source')->nullable()->after('verification_status');
            $table->boolean('computable')->nullable()->after('scientific_source');

            $table->foreign('lovebird_species_id')
                ->references('id')
                ->on('lovebird_species')
                ->restrictOnDelete();

            $table->unique(['lovebird_species_id', 'name'], 'visual_mutations_species_name_unique');
        });

        Schema::create('bird_visual_mutation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bird_id')->constrained('birds')->cascadeOnDelete();
            $table->foreignId('visual_mutation_id')->constrained('visual_mutations')->restrictOnDelete();
            $table->unique(['bird_id', 'visual_mutation_id'], 'bird_visual_mutation_unique');
        });

        Schema::create('bird_grandparent_visual_mutation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bird_grandparent_id')->constrained('bird_grandparents')->cascadeOnDelete();
            $table->foreignId('visual_mutation_id')->constrained('visual_mutations')->restrictOnDelete();
            $table->unique(['bird_grandparent_id', 'visual_mutation_id'], 'bird_grandparent_visual_mutation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_grandparent_visual_mutation');
        Schema::dropIfExists('bird_visual_mutation');

        Schema::table('visual_mutations', function (Blueprint $table) {
            $table->dropUnique('visual_mutations_species_name_unique');
            $table->dropForeign(['lovebird_species_id']);
            $table->dropColumn([
                'lovebird_species_id',
                'species_name',
                'scientific_name',
                'series',
                'sf',
                'df',
                'allele',
                'genotype',
                'genetic_code',
                'inheritance_type',
                'phenotype',
                'verification_status',
                'scientific_source',
                'computable',
            ]);
            $table->unique('name');
        });
    }
};
