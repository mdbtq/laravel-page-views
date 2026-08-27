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
            $table->string('route')->nullable();
            $table->string('referrer', 1024)->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('browser', 32)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('ip_anon', 45);
            $table->string('visitor_hash', 64)->nullable();
            $table->string('country', 2)->nullable();
            $table->timestamp('viewed_at')->useCurrent();

            $table->index('viewed_at');
            $table->index(['viewed_at', 'path']);
            $table->index(['viewed_at', 'referrer_host']);
            $table->index(['viewed_at', 'country']);
            $table->index(['viewed_at', 'visitor_hash']);
        });

        Schema::create('page_view_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('path', 255);
            $table->string('country', 2)->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('visitors')->default(0);

            $table->unique(['date', 'path', 'country'], 'page_view_daily_unique');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_daily');
        Schema::dropIfExists('page_views');
    }
};
