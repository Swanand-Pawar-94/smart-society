<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\MaintenancePayment;
use App\Models\MaintenanceBill;
use App\Models\Resident;
use App\Models\ResidentTransaction;

echo "=== Testing Payment → Credit Transaction Flow ===\n\n";

// Get the first resident and their first bill
$resident = Resident::find(1);
$bill = MaintenanceBill::where('flat_id', $resident->flat_id)->first();

if (!$bill) {
    echo "No bill found for resident!\n";
    exit(1);
}

echo "Resident: {$resident->user->name}\n";
echo "Bill ID: {$bill->id}\n";
echo "Bill Amount: ₹{$bill->amount}\n\n";

// Get current transaction count
$transactionsBefore = ResidentTransaction::where('resident_id', $resident->id)->count();
echo "Transactions before payment: {$transactionsBefore}\n";

// Check if payment already exists
$existingPayment = MaintenancePayment::where('maintenance_bill_id', $bill->id)
    ->where('status', MaintenancePayment::STATUS_COMPLETED)
    ->first();

if ($existingPayment) {
    echo "\n✓ Payment already exists (ID: {$existingPayment->id})\n";
    echo "  Amount: ₹{$existingPayment->amount}\n";
    echo "  Status: {$existingPayment->status}\n";
    
    $creditTransaction = ResidentTransaction::where('resident_id', $resident->id)
        ->where('reference_type', MaintenancePayment::class)
        ->where('reference_id', $existingPayment->id)
        ->first();
    
    if ($creditTransaction) {
        echo "\n✓ Credit transaction exists (ID: {$creditTransaction->id})\n";
        echo "  Type: {$creditTransaction->transaction_type}\n";
        echo "  Category: {$creditTransaction->category}\n";
        echo "  Amount: ₹{$creditTransaction->amount}\n";
        echo "  Balance After: ₹{$creditTransaction->balance_after}\n";
    } else {
        echo "\n✗ No credit transaction found for this payment!\n";
    }
} else {
    echo "\nCreating new payment...\n";
    
    // Create a payment
    $payment = MaintenancePayment::create([
        'maintenance_bill_id' => $bill->id,
        'resident_id' => $resident->id,
        'amount' => 1000.00,
        'reference_id' => 'TEST-' . time(),
        'payment_method' => MaintenancePayment::METHOD_UPI,
        'status' => MaintenancePayment::STATUS_PENDING,
    ]);
    
    echo "✓ Payment created (ID: {$payment->id}) with status PENDING\n";
    
    $transactionsAfterCreate = ResidentTransaction::where('resident_id', $resident->id)->count();
    echo "Transactions after create: {$transactionsAfterCreate}\n";
    
    // Update payment to completed (this should trigger the observer)
    $payment->update([
        'status' => MaintenancePayment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);
    
    echo "\n✓ Payment updated to COMPLETED\n";
    
    $transactionsAfterComplete = ResidentTransaction::where('resident_id', $resident->id)->count();
    echo "Transactions after complete: {$transactionsAfterComplete}\n";
    
    // Check if credit transaction was created
    $creditTransaction = ResidentTransaction::where('resident_id', $resident->id)
        ->where('reference_type', MaintenancePayment::class)
        ->where('reference_id', $payment->id)
        ->first();
    
    if ($creditTransaction) {
        echo "\n✓ Credit transaction automatically created (ID: {$creditTransaction->id})\n";
        echo "  Type: {$creditTransaction->transaction_type}\n";
        echo "  Category: {$creditTransaction->category}\n";
        echo "  Amount: ₹{$creditTransaction->amount}\n";
        echo "  Balance After: ₹{$creditTransaction->balance_after}\n";
        echo "  Description: {$creditTransaction->description}\n";
    } else {
        echo "\n✗ Credit transaction was NOT created automatically!\n";
    }
}

echo "\n=== Current Balance Summary ===\n";
$allTransactions = ResidentTransaction::where('resident_id', $resident->id)
    ->where('status', ResidentTransaction::STATUS_COMPLETED)
    ->get();

$totalDebit = $allTransactions->where('transaction_type', 'DEBIT')->sum('amount');
$totalCredit = $allTransactions->where('transaction_type', 'CREDIT')->sum('amount');
$currentBalance = $allTransactions->sortByDesc('id')->first()?->balance_after ?? 0;

echo "Total Debit:  ₹" . number_format($totalDebit, 2) . "\n";
echo "Total Credit: ₹" . number_format($totalCredit, 2) . "\n";
echo "Current Balance: ₹" . number_format($currentBalance, 2) . "\n";
echo "Outstanding: ₹" . number_format(max(0, $currentBalance), 2) . "\n";
