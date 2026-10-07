<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->decimal('min_qty', 8, 2)->default(0)->after('percent');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        Schema::table('promos', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->after('tenant_id');
        });

        Schema::table('promos', function (Blueprint $table) {
            $table->dropColumn('min_qty');
        });
    }
};
