<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breeding_predictions', function (Blueprint $table) {
            $table->foreignId('bird_pair_id')->nullable()->after('id')->constrained('bird_pairs')->nullOnDelete();
            $table->foreignId('computation_result_id')->nullable()->after('bird_pair_id')->constrained('computation_results')->nullOnDelete();
        });

        Schema::table('computation_results', function (Blueprint $table) {
            $table->foreign('prediction_id')
                ->references('id')
                ->on('breeding_predictions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('computation_results', function (Blueprint $table) {
            $table->dropForeign(['prediction_id']);
        });

        Schema::table('breeding_predictions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('computation_result_id');
            $table->dropConstrainedForeignId('bird_pair_id');
        });
    }
};
