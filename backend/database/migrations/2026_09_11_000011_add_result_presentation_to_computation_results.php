<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('computation_results', function (Blueprint $table) {
            $table->json('result_presentation')->nullable()->after('offspring_visualizations');
        });
    }

    public function down(): void
    {
        Schema::table('computation_results', function (Blueprint $table) {
            $table->dropColumn('result_presentation');
        });
    }
};
