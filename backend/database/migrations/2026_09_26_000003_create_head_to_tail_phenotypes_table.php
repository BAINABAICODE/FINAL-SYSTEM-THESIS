<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('head_to_tail_phenotypes', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->unsignedInteger('lovebird_species_id');
            $table->unsignedBigInteger('visual_mutation_id')->nullable();
            $table->foreign('lovebird_species_id')
                ->references('id')
                ->on('lovebird_species')
                ->cascadeOnDelete();
            $table->foreign('visual_mutation_id')
                ->references('id')
                ->on('visual_mutations')
                ->cascadeOnDelete();
            $table->string('mutation_name', 100)->nullable();
            $table->string('eyes', 180);
            $table->string('head', 180);
            $table->string('neck', 180);
            $table->string('body', 180);
            $table->string('wings', 180);
            $table->string('rump', 180);
            $table->string('tail', 180);
            $table->text('pigment_notes')->nullable();
            $table->string('source', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['kind', 'lovebird_species_id', 'visual_mutation_id'],
                'htt_kind_species_mutation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('head_to_tail_phenotypes');
    }
};
