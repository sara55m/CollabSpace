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
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->text('note')->nullable();
            $table->string('status')->default('scheduled');   // scheduled | completed | cancelled
            $table->string('join_url')->nullable();
            $table->string('recording_path')->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index('starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
