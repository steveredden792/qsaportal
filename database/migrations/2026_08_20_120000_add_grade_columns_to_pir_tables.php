<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->string('q_grade')->nullable()->after('q_score');
            $table->decimal('stability_grade', 3, 1)->nullable()->after('stability');
        });

        Schema::table('charities', function (Blueprint $table) {
            $table->string('latest_q_grade')->nullable()->after('latest_q_score');
            $table->decimal('latest_stability_grade', 3, 1)->nullable()->after('latest_stability');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['q_grade', 'stability_grade']);
        });

        Schema::table('charities', function (Blueprint $table) {
            $table->dropColumn(['latest_q_grade', 'latest_stability_grade']);
        });
    }
};
