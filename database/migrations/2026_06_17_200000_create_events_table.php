<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('venue_name')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('cover_image')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->unsignedInteger('max_guests')->nullable();
            $table->dateTime('rsvp_deadline')->nullable();
            $table->boolean('allow_plus_ones')->default(true);
            $table->unsignedTinyInteger('max_plus_ones_per_guest')->default(1);
            $table->boolean('collect_dietary')->default(false);
            $table->boolean('collect_accessibility')->default(false);
            $table->boolean('collect_seating')->default(false);
            $table->boolean('require_phone')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
