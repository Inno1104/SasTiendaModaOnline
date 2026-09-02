<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->foreignId('cerrada_por')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('caja', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cerrada_por');
        });
    }
};
