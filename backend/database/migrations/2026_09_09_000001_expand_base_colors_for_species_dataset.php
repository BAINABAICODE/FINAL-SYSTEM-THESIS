<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('base_colors', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('base_colors', function (Blueprint $table) {
            $table->unsignedInteger('lovebird_species_id')->nullable()->after('id');
            $table->string('species_name', 100)->nullable()->after('lovebird_species_id');
            $table->string('scientific_name', 100)->nullable()->after('species_name');
            $table->string('series', 80)->nullable()->after('name');
            $table->string('sf', 255)->nullable()->after('series');
            $table->string('df', 255)->nullable()->after('sf');
            $table->string('allele', 255)->nullable()->after('df');
            $table->string('genotype', 255)->nullable()->after('allele');
            $table->string('genetic_code', 64)->nullable()->after('genotype');
            $table->string('inheritance_type', 80)->nullable()->after('genetic_code');
            $table->text('phenotype')->nullable()->after('inheritance_type');
            $table->string('verification_status', 64)->nullable()->after('phenotype');
            $table->string('scientific_source', 255)->nullable()->after('verification_status');
            $table->boolean('computable')->default(false)->after('scientific_source');

            $table->foreign('lovebird_species_id')
                ->references('id')
                ->on('lovebird_species')
                ->restrictOnDelete();

            $table->unique(['lovebird_species_id', 'name'], 'base_colors_species_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('base_colors', function (Blueprint $table) {
            $table->dropUnique('base_colors_species_name_unique');
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
