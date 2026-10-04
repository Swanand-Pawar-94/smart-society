<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Resident;
use App\Services\TransactionLedgerService;

echo "=== Recalculating Balances ===\n\n";

$ledger = app(TransactionLedgerService::class);
$residents = Resident::all();

foreach ($residents as $resident) {
    echo "Recalculating for Resident ID: {$resident->id} ({$resident->user->name})\n";
    $ledger->recalculateBalances($resident->id);
}

echo "\n✓ Balances recalculated for {$residents->count()} residents\n";
