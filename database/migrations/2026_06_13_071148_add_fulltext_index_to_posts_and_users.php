<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Posts
        Schema::table('posts', function (Blueprint $table) {
            $table->fullText(['title', 'body'], 'posts_fulltext');
        });

        // Users
        Schema::table('users', function (Blueprint $table) {
            $table->fullText(['username'], 'users_fulltext');
        });

        // Tags
        Schema::table('tags', function (Blueprint $table) {
            $table->fullText(['name'], 'tags_fulltext');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropFullText('posts_fulltext');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropFullText('users_fulltext');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropFullText('tags_fulltext');
        });
    }
};
