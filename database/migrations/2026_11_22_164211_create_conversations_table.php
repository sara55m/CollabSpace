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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('avatar')->nullable();
            $table->string('type');                                   // direct | project
            $table->foreignId('project_id')->nullable()     // one channel per project
                ->constrained()->cascadeOnDelete();
            $table->string('direct_key')->nullable()->unique();       //prevent duplicate DMs(user1_id+user2_id)
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('last_message_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
