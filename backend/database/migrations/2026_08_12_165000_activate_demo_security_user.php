<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Activate all seeded / demo security-team staff accounts so they can
     * sign in without manual administrator intervention. In a production
     * deployment, administrators activate accounts via the admin panel;
     * this migration only makes demo data usable out of the box.
     */
    public function up(): void
    {
        DB::table('staff_members')
            ->whereIn('user_id', function ($query) {
                $query->select('id')
                    ->from('users')
                    ->where('role', 'SECURITY')
                    ->where('email', 'swanandp20@gmail.com'); // demo seed account
            })
            ->where('status', 'INACTIVE')
            ->update(['status' => 'ACTIVE', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Intentionally left as a no-op: reverting demo activation would be
        // confusing and is not meaningful in a rollback scenario.
    }
};
