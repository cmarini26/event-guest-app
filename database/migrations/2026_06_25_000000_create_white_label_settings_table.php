<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('white_label_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand_name')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 7)->nullable();  // hex e.g. #4f46e5
            $table->string('accent_color', 7)->nullable();
            $table->string('email_sender_name')->nullable();
            $table->boolean('hide_branding')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('white_label_settings');
    }
};
