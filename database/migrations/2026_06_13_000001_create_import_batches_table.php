<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('type')->default('far_index');
            $table->string('status')->default('pending');
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('charities_created')->default(0);
            $table->unsignedInteger('charities_updated')->default(0);
            $table->unsignedInteger('issues_created')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
