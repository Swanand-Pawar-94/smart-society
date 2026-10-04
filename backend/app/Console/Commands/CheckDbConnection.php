<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckDbConnection extends Command
{
    protected $signature = 'society:check-db';
    protected $description = 'Check database connection and report status';

    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
            $dbName = DB::connection()->getDatabaseName();
            $host = config('database.connections.mysql.host', '127.0.0.1');
            $port = config('database.connections.mysql.port', '3306');
            $userCount = DB::table('users')->count();

            $this->info("========================================");
            $this->info("DATABASE CONNECTED");
            $this->info("Database : {$dbName}");
            $this->info("Host     : {$host}:{$port}");
            $this->info("Users    : {$userCount} records in users table");
            $this->info("========================================");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("========================================");
            $this->error("DATABASE CONNECTION FAILED");
            $this->error($e->getMessage());
            $this->error("========================================");

            return self::FAILURE;
        }
    }
}
