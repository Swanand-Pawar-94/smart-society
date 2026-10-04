<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ResidentTransaction;
use App\Models\Resident;

echo "=== Testing Transaction Ledger ===\n\n";

// Test resident 1
$resident = Resident::find(1);
if (!$resident) {
    echo "Resident 1 not found!\n";
    exit(1);
}

echo "Resident: {$resident->user->name}\n";
echo "Flat: {$resident->flat->flat_number}\n\n";

$transactions = ResidentTransaction::where('resident_id', 1)
    ->orderBy('transaction_date')
    ->orderBy('id')
    ->get();

echo "Total Transactions: {$transactions->count()}\n\n";

echo str_pad("Date", 12) . str_pad("Type", 10) . str_pad("Category", 20) . str_pad("Amount", 12) . str_pad("Balance", 12) . "\n";
echo str_repeat("-", 66) . "\n";

foreach ($transactions as $t) {
    $date = $t->transaction_date->format('d M Y');
    $type = $t->transaction_type;
    $category = $t->category;
    $amount = number_format($t->amount, 2);
    $balance = number_format($t->balance_after, 2);
    
    echo str_pad($date, 12) . str_pad($type, 10) . str_pad($category, 20) . str_pad($amount, 12) . str_pad($balance, 12) . "\n";
}

echo "\n=== Summary ===\n";
$totalDebit = $transactions->where('transaction_type', 'DEBIT')->sum('amount');
$totalCredit = $transactions->where('transaction_type', 'CREDIT')->sum('amount');
$finalBalance = $transactions->last()?->balance_after ?? 0;

echo "Total Debit:  ₹" . number_format($totalDebit, 2) . "\n";
echo "Total Credit: ₹" . number_format($totalCredit, 2) . "\n";
echo "Final Balance: ₹" . number_format($finalBalance, 2) . "\n";
echo "Outstanding: ₹" . number_format(max(0, $finalBalance), 2) . "\n";

echo "\n=== Verification ===\n";
$expectedBalance = $totalDebit - $totalCredit;
echo "Expected Balance (Debit - Credit): ₹" . number_format($expectedBalance, 2) . "\n";
echo "Actual Final Balance: ₹" . number_format($finalBalance, 2) . "\n";
echo "Match: " . ($expectedBalance == $finalBalance ? "✓ YES" : "✗ NO") . "\n";
