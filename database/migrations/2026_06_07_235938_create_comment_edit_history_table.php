<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up(): void
     {
         Schema::create('comment_edit_history', function (Blueprint $table) {
             $table->uuid('id')->primary();
             $table->foreignUuid('comment_id')->constrained()->cascadeOnDelete();
             $table->foreignUuid('edited_by')->constrained('users')->cascadeOnDelete();
             $table->text('body_before');
             $table->text('body_after');
             $table->timestamp('edited_at')->useCurrent();
             $table->index('comment_id', 'comment_edit_history_comment_id_idx');
         });
     }

     public function down(): void
     {
         Schema::dropIfExists('comment_edit_history');
     }
};
