<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');
            $table->date('published_at');
            $table->boolean('is_current')->default(false);
            $table->decimal('q_score', 5, 2)->nullable();
            $table->decimal('stability', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['report_id', 'version_label']);
            $table->index(['report_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
