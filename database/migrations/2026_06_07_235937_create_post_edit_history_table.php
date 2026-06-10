<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
     {
         Schema::create('post_edit_history', function (Blueprint $table) {
             $table->uuid('id')->primary();
             $table->foreignUuid('post_id')->constrained()->cascadeOnDelete();
             $table->foreignUuid('edited_by')->constrained('users')->cascadeOnDelete();
             $table->text('body_before');
             $table->text('body_after');
             $table->string('reason', 255)->nullable();
             $table->timestamp('edited_at')->useCurrent();
             $table->index('post_id', 'post_edit_history_post_id_idx');
         });
     }

     public function down(): void
     {
         Schema::dropIfExists('post_edit_history');
     }
};
