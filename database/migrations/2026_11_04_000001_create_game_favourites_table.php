<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titles a player has hearted in the lobby (slots, scratch cards). Holds the catalogue product
 * code only; a title the API stops offering simply stops showing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product', 80);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'product']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_favourites');
    }
};
