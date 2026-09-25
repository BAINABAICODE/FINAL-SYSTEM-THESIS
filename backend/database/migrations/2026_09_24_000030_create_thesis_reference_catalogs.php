<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lovebird_species', function (Blueprint $table) {
            $table->string('scientific_source', 255)->nullable()->after('description');
            $table->string('verification_status', 64)->nullable()->after('scientific_source');
        });

        Schema::create('inheritance_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->string('chromosome_model', 40);
            $table->boolean('sex_linked')->default(false);
            $table->text('punnett_rule');
            $table->text('expression_rule');
            $table->text('scientific_basis');
            $table->string('scientific_source', 255);
            $table->string('verification_status', 64);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('gold_standard_inheritance_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_key', 80)->unique();
            $table->unsignedInteger('lovebird_species_id');
            $table->string('locus_name', 80);
            $table->string('inheritance_type', 80);
            $table->string('cock_code', 64);
            $table->string('hen_code', 64);
            $table->boolean('sex_linked')->default(false);
            $table->json('expected_outcomes');
            $table->text('hand_computation');
            $table->string('scientific_source', 255);
            $table->string('verification_status', 64);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('lovebird_species_id')
                ->references('id')
                ->on('lovebird_species')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_standard_inheritance_cases');
        Schema::dropIfExists('inheritance_modes');

        Schema::table('lovebird_species', function (Blueprint $table) {
            $table->dropColumn(['scientific_source', 'verification_status']);
        });
    }
};
