<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('birds', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['bird_id']);
            $table->unique(['user_id', 'bird_id']);
        });

        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('breeding_predictions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('computation_results', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        Schema::table('chick_outcome_images', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropUnique(['signature']);
            $table->unique(['user_id', 'signature']);
        });
    }

    public function down(): void
    {
        Schema::table('chick_outcome_images', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'signature']);
            $table->unique('signature');
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('computation_results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('breeding_predictions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('bird_pairs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('birds', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'bird_id']);
            $table->unique('bird_id');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
