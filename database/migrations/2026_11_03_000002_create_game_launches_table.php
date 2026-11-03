<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per launch attempt: powers "recently played" and real popularity
 * ("most played") in the lobby, and shows failed launches. Never holds a
 * launch token, IP address or anything else about the session.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_launches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 40);
            $table->string('product', 80)->nullable();
            $table->string('status', 16); // launched | failed
            $table->string('error', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['product', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_launches');
    }
};
