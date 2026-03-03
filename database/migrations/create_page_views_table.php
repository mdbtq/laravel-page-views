<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255);
            $table->string('referrer', 1024)->nullable();
            $table->string('user_agent', 1024)->nullable();
            $table->string('ip_anon', 45);
            $table->string('country', 2)->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index('viewed_at');
            $table->index('path');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
