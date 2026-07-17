<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Data: PPR/PMR were dev-only demo rows — delete (issues/assets cascade).
        DB::table('reports')->whereIn('type', ['ppr', 'pmr'])->delete();

        // Data: the charity "FAR" is really the PIR.
        DB::table('reports')->where('type', 'far')->update([
            'type' => 'pir',
            'slug' => DB::raw("REPLACE(slug, 'far-', 'pir-')"),
            'name' => DB::raw("REPLACE(name, 'Financial Analysis Report', 'Public Information Report')"),
        ]);

        Schema::table('reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('market_id');
            $table->string('tier')->nullable()->after('slug'); // FAR only, from the index spreadsheet
        });

        Schema::dropIfExists('provider_charity_links');
        Schema::dropIfExists('markets');

        Schema::create('far_pir_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charity_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['issue_id', 'charity_id']);
        });
    }

    public function down(): void
    {
        // Dev-only forward migration; no production data exists to restore.
        Schema::dropIfExists('far_pir_references');
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('tier');
            $table->foreignId('market_id')->nullable()->constrained()->cascadeOnDelete();
        });
        DB::table('reports')->where('type', 'pir')->update(['type' => 'far']);
    }
};
