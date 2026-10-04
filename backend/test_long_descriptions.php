<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ResidentTransaction;
use App\Models\Resident;
use App\Services\TransactionLedgerService;

echo "=== Testing Long Description Wrapping ===\n\n";

$resident = Resident::find(1);
if (!$resident) {
    echo "Resident not found!\n";
    exit(1);
}

$ledger = app(TransactionLedgerService::class);

// Create a test transaction with a very long description
$currentBalance = $ledger->getCurrentBalance($resident->id);

$longTransaction = ResidentTransaction::create([
    'resident_id' => $resident->id,
    'flat_id' => $resident->flat_id,
    'transaction_type' => ResidentTransaction::TYPE_DEBIT,
    'category' => ResidentTransaction::CATEGORY_OTHER_CHARGE,
    'description' => 'Special assessment fee for society building maintenance and repair work including painting, plumbing, electrical repairs, and common area renovations',
    'amount' => 500.00,
    'balance_after' => $currentBalance + 500.00,
    'transaction_date' => now(),
    'reference_type' => null,
    'reference_id' => null,
    'reference_number' => 'ASSESS-001',
    'status' => ResidentTransaction::STATUS_COMPLETED,
]);

echo "✓ Created test transaction with long description\n";
echo "ID: {$longTransaction->id}\n";
echo "Description: {$longTransaction->description}\n";
echo "Length: " . strlen($longTransaction->description) . " characters\n\n";

// Show all transactions with their description lengths
echo "=== All Transactions ===\n\n";
$transactions = ResidentTransaction::where('resident_id', $resident->id)
    ->orderBy('transaction_date', 'asc')
    ->orderBy('id', 'asc')
    ->get();

echo str_pad("ID", 5) . str_pad("Type", 8) . str_pad("Desc Length", 13) . "Description Preview\n";
echo str_repeat("─", 80) . "\n";

foreach ($transactions as $t) {
    $preview = substr($t->description, 0, 50);
    echo str_pad($t->id, 5) . 
         str_pad($t->transaction_type, 8) . 
         str_pad(strlen($t->description) . ' chars', 13) . 
         $preview . (strlen($t->description) > 50 ? '...' : '') . "\n";
}

echo "\n=== Test Summary ===\n\n";
echo "✓ Long description created: " . strlen($longTransaction->description) . " characters\n";
echo "✓ Reference number: {$longTransaction->reference_number}\n";
echo "✓ Transaction saved successfully\n\n";

echo "Flutter UI Requirements:\n";
echo "1. ✓ Description should NOT be truncated with '...'\n";
echo "2. ✓ Description should wrap to multiple lines\n";
echo "3. ✓ Reference number (ASSESS-001) should appear below description\n";
echo "4. ✓ Row height should expand automatically\n";
echo "5. ✓ Debit/Credit/Balance columns should remain fully visible\n\n";

echo "To verify in app:\n";
echo "1. Open Ledger History screen\n";
echo "2. Look for transaction with reference ASSESS-001\n";
echo "3. Verify full description is visible (no '...')\n";
echo "4. Verify description wraps across multiple lines\n";
echo "5. Verify reference number is visible below description\n\n";

echo "To remove test transaction:\n";
echo "ResidentTransaction::find({$longTransaction->id})->delete();\n";
