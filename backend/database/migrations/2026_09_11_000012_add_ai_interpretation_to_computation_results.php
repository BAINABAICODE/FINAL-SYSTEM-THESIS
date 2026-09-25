<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computation_results', function (Blueprint $table) {
            $table->json('ai_interpretation')->nullable()->after('result_presentation');
        });
    }

    public function down(): void
    {
        Schema::table('computation_results', function (Blueprint $table) {
            $table->dropColumn('ai_interpretation');
        });
    }
};
