<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->string('folder')->nullable()->after('type');   // S3 publication folder, e.g. 2026-07
            $table->json('errors')->nullable()->after('status');   // [{row, error}] when failed
            $table->unsignedInteger('providers_created')->default(0)->after('charities_updated');
            $table->unsignedInteger('providers_updated')->default(0)->after('providers_created');
        });

        DB::table('import_batches')->where('status', 'pending')->update(['status' => 'draft']);
        DB::table('import_batches')->where('status', 'completed')->update(['status' => 'published']);
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->dropColumn(['folder', 'errors', 'providers_created', 'providers_updated']);
        });
    }
};
