<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_bills', function (Blueprint $table): void {
            $table->date('billing_period_start')->nullable()->after('billing_month');
            $table->date('billing_period_end')->nullable()->after('billing_period_start');
            $table->decimal('base_maintenance', 12, 2)->default(0)->after('due_date');
            $table->decimal('water_charge', 12, 2)->default(0)->after('base_maintenance');
            $table->decimal('electricity_common_area_charge', 12, 2)->default(0)->after('water_charge');
            $table->decimal('parking_charge', 12, 2)->default(0)->after('electricity_common_area_charge');
            $table->decimal('other_charges', 12, 2)->default(0)->after('parking_charge');
            $table->decimal('late_fee', 12, 2)->default(0)->after('other_charges');
            $table->decimal('discount', 12, 2)->default(0)->after('late_fee');
            $table->index(['billing_month', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_bills', function (Blueprint $table): void {
            $table->dropIndex(['billing_month', 'status']);
            $table->dropColumn([
                'billing_period_start',
                'billing_period_end',
                'base_maintenance',
                'water_charge',
                'electricity_common_area_charge',
                'parking_charge',
                'other_charges',
                'late_fee',
                'discount',
            ]);
        });
    }
};
