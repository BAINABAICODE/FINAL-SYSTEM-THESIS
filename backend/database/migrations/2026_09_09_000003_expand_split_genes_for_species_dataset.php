<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('split_genes', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('split_genes', function (Blueprint $table) {
            $table->unsignedInteger('lovebird_species_id')->nullable()->after('id');
            $table->string('species_name', 100)->nullable()->after('lovebird_species_id');
            $table->string('scientific_name', 100)->nullable()->after('species_name');
            $table->string('genetic_symbol', 32)->nullable()->after('name');
            $table->string('wild_type_allele', 32)->nullable()->after('genetic_symbol');
            $table->string('mutant_allele', 32)->nullable()->after('wild_type_allele');
            $table->string('inheritance_type', 80)->nullable()->after('mutant_allele');
            $table->string('genetic_category', 64)->nullable()->after('inheritance_type');
            $table->boolean('cock_can_split')->nullable()->after('genetic_category');
            $table->boolean('hen_can_split')->nullable()->after('cock_can_split');
            $table->string('heterozygous_genotype', 64)->nullable()->after('hen_can_split');
            $table->string('homozygous_genotype', 128)->nullable()->after('heterozygous_genotype');
            $table->string('genetic_code', 64)->nullable()->after('homozygous_genotype');
            $table->string('phenotype_when_visual', 128)->nullable()->after('genetic_code');
            $table->text('description')->nullable()->after('phenotype_when_visual');
            $table->text('scientific_source')->nullable()->after('description');
            $table->string('verification_status', 64)->nullable()->after('scientific_source');
            $table->boolean('computable')->nullable()->after('verification_status');

            $table->foreign('lovebird_species_id')
                ->references('id')
                ->on('lovebird_species')
                ->restrictOnDelete();

            $table->unique(['lovebird_species_id', 'name'], 'split_genes_species_name_unique');
        });

        Schema::create('bird_split_gene', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bird_id')->constrained('birds')->cascadeOnDelete();
            $table->foreignId('split_gene_id')->constrained('split_genes')->restrictOnDelete();
            $table->unique(['bird_id', 'split_gene_id'], 'bird_split_gene_unique');
        });

        Schema::create('bird_grandparent_split_gene', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bird_grandparent_id')->constrained('bird_grandparents')->cascadeOnDelete();
            $table->foreignId('split_gene_id')->constrained('split_genes')->restrictOnDelete();
            $table->unique(['bird_grandparent_id', 'split_gene_id'], 'bird_grandparent_split_gene_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bird_grandparent_split_gene');
        Schema::dropIfExists('bird_split_gene');

        Schema::table('split_genes', function (Blueprint $table) {
            $table->dropUnique('split_genes_species_name_unique');
            $table->dropForeign(['lovebird_species_id']);
            $table->dropColumn([
                'lovebird_species_id',
                'species_name',
                'scientific_name',
                'genetic_symbol',
                'wild_type_allele',
                'mutant_allele',
                'inheritance_type',
                'genetic_category',
                'cock_can_split',
                'hen_can_split',
                'heterozygous_genotype',
                'homozygous_genotype',
                'genetic_code',
                'phenotype_when_visual',
                'description',
                'scientific_source',
                'verification_status',
                'computable',
            ]);
            $table->unique('name');
        });
    }
};
