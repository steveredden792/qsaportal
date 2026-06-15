<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('charities', function (Blueprint $table) {
            $table->index('latest_q_score');
            $table->index('latest_stability');
        });
    }

    public function down(): void
    {
        Schema::table('charities', function (Blueprint $table) {
            $table->dropIndex(['latest_q_score']);
            $table->dropIndex(['latest_stability']);
        });
    }
};
