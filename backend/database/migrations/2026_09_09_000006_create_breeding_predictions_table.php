<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breeding_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('parent_1_bird_id', 80);
            $table->string('parent_2_bird_id', 80);
            $table->string('status', 40);
            $table->json('parent_1');
            $table->json('parent_2');
            $table->json('validation');
            $table->json('prediction');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeding_predictions');
    }
};
