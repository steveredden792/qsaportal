<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->date('accounting_date')->nullable()->after('stability_grade');
            $table->string('charity_type')->nullable()->after('accounting_date');
            $table->date('formation_date')->nullable()->after('charity_type');
            $table->unsignedInteger('stability_rank')->nullable()->after('formation_date');
            $table->unsignedInteger('q_score_rank')->nullable()->after('stability_rank');
            $table->longText('objectives')->nullable()->after('q_score_rank');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn([
                'accounting_date',
                'charity_type',
                'formation_date',
                'stability_rank',
                'q_score_rank',
                'objectives',
            ]);
        });
    }
};
