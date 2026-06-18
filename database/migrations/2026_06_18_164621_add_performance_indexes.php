<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index('status');
            $table->index(['user_id', 'status']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->index('rsvp_status');
            $table->index(['event_id', 'rsvp_status']);
            $table->index('invited_at');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['rsvp_status']);
            $table->dropIndex(['event_id', 'rsvp_status']);
            $table->dropIndex(['invited_at']);
            $table->dropIndex(['email']);
        });
    }
};
