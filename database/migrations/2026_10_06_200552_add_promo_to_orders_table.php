<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('promo_id')->nullable()->after('service_id')->constrained('promos')->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->default(0)->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_id');
            $table->dropColumn('discount_percent');
        });
    }
};
