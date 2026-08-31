<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('holds', function (Blueprint $table) {
            // MySQL не отдаст старый индекс, пока FK не обслуживается новым:
            // сначала unique (slot_id — левая колонка, FK им обслуживается), затем дроп.
            $table->unique(['slot_id', 'idempotency_key']);
            $table->dropIndex(['slot_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('holds', function (Blueprint $table) {
            $table->dropUnique(['slot_id', 'idempotency_key']);
            $table->index(['slot_id', 'idempotency_key']);
        });
    }
};
