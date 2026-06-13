<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // AssetType: report_pdf|dataset|teaser
            $table->string('disk')->default('s3');
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime')->nullable();
            $table->timestamps();
            $table->index(['issue_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
