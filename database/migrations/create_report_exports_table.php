<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->unsignedBigInteger('report_id');
            $table->unsignedBigInteger('export_id');

            $table->foreign('report_id')->references('id')->on('reports');
            $table->foreign('export_id')->references('id')->on('exports');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
