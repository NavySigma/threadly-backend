<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->uuid('target_id');
            $table->string('target_type', 20);
            $table->string('reason', 100);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->index('reporter_id', 'reports_reporter_id_idx');
            $table->index(['target_id', 'target_type'], 'reports_target_idx');
            $table->index('status', 'reports_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
