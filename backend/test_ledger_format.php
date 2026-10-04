<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\ResidentTransaction;

echo "=== Testing Ledger Format ===\n\n";

$user = User::whereHas('resident')->first();
$token = $user->createToken('test-token')->plainTextToken;

// Test the API endpoint
$url = 'http://127.0.0.1:8000/api/resident/transaction-history';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    echo "✓ API Response Success!\n\n";
    
    // Display ledger format
    echo "=== LEDGER FORMAT ===\n\n";
    echo str_pad("Date", 12) . str_pad("Description", 35) . str_pad("Debit", 12) . str_pad("Credit", 12) . str_pad("Balance", 12) . "\n";
    echo str_repeat("─", 83) . "\n";
    
    $runningBalance = 0;
    $totalDebit = 0;
    $totalCredit = 0;
    
    foreach ($data['data'] as $transaction) {
        $date = date('d M Y', strtotime($transaction['transaction_date']));
        $description = substr($transaction['description'], 0, 32);
        $isDebit = $transaction['transaction_type'] === 'DEBIT';
        
        $debit = $isDebit ? '₹' . $transaction['amount'] : '—';
        $credit = !$isDebit ? '₹' . $transaction['amount'] : '—';
        $balance = '₹' . $transaction['balance_after'];
        
        echo str_pad($date, 12) . 
             str_pad($description, 35) . 
             str_pad($debit, 12) . 
             str_pad($credit, 12) . 
             str_pad($balance, 12) . "\n";
        
        if ($isDebit) {
            $totalDebit += floatval($transaction['amount']);
        } else {
            $totalCredit += floatval($transaction['amount']);
        }
    }
    
    echo str_repeat("─", 83) . "\n\n";
    
    // Display summary
    echo "=== SUMMARY ===\n\n";
    $summary = $data['summary'];
    echo "Outstanding Balance    ₹" . $summary['outstanding_amount'] . "\n";
    echo "Total Debit            ₹" . $summary['total_debit'] . "\n";
    echo "Total Credit           ₹" . $summary['total_credit'] . "\n\n";
    
    // Verify calculation
    echo "=== VERIFICATION ===\n\n";
    $calculatedOutstanding = floatval($summary['total_debit']) - floatval($summary['total_credit']);
    $actualOutstanding = floatval($summary['outstanding_amount']);
    $lastBalance = count($data['data']) > 0 ? floatval($data['data'][count($data['data']) - 1]['balance_after']) : 0;
    
    echo "Total Debit - Total Credit = ₹" . number_format($calculatedOutstanding, 2) . "\n";
    echo "Outstanding Amount         = ₹" . number_format($actualOutstanding, 2) . "\n";
    echo "Last Running Balance       = ₹" . number_format($lastBalance, 2) . "\n\n";
    
    echo "Outstanding = (Debit - Credit): " . ($calculatedOutstanding == $actualOutstanding ? "✓ PASS" : "✗ FAIL") . "\n";
    echo "Outstanding = Last Balance:     " . ($actualOutstanding == $lastBalance ? "✓ PASS" : "✗ FAIL") . "\n";
    
} else {
    echo "✗ API Error! HTTP {$httpCode}\n";
    echo "Response: {$response}\n";
}

$user->tokens()->delete();
echo "\n✓ Test completed\n";
