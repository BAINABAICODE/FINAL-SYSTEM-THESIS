<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lovebird_species', function (Blueprint $table) {
            $table->text('description')->nullable()->after('has_eye_ring');
        });
    }

    public function down(): void
    {
        Schema::table('lovebird_species', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
