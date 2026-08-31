<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained('slots')->cascadeOnDelete();
            $table->string('status');
            $table->uuid('idempotency_key');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['slot_id', 'idempotency_key']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('holds');
    }
};
