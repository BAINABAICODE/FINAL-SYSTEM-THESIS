<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chick_outcome_images', function (Blueprint $table) {
            $table->id();
            $table->string('signature', 64)->unique();
            $table->unsignedInteger('egg_number')->nullable();
            $table->string('species')->nullable();
            $table->string('sex')->nullable();
            $table->string('base_color')->nullable();
            $table->json('visual_mutations')->nullable();
            $table->json('split_genes')->nullable();
            $table->longText('prompt')->nullable();
            $table->string('image_path')->nullable();
            $table->string('image_url', 512)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 191)->nullable();
            $table->string('hf_provider', 64)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chick_outcome_images');
    }
};
