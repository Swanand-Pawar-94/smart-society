<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== Testing Transaction History API Filters ===\n\n";

$user = User::whereHas('resident')->first();
$token = $user->createToken('test-token')->plainTextToken;

// Test 1: Filter by DEBIT
echo "Test 1: Filter by transaction_type=DEBIT\n";
$url = 'http://127.0.0.1:8000/api/resident/transaction-history?transaction_type=DEBIT';
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
    $count = count($data['data']);
    echo "✓ Success: Found {$count} DEBIT transactions\n";
    foreach ($data['data'] as $t) {
        echo "  - {$t['description']} (₹{$t['amount']})\n";
    }
} else {
    echo "✗ Failed with status {$httpCode}\n";
}

echo "\n";

// Test 2: Filter by CREDIT
echo "Test 2: Filter by transaction_type=CREDIT\n";
$url = 'http://127.0.0.1:8000/api/resident/transaction-history?transaction_type=CREDIT';
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
    $count = count($data['data']);
    echo "✓ Success: Found {$count} CREDIT transactions\n";
    foreach ($data['data'] as $t) {
        echo "  - {$t['description']} (₹{$t['amount']})\n";
    }
} else {
    echo "✗ Failed with status {$httpCode}\n";
}

echo "\n";

// Test 3: Test summary endpoint
echo "Test 3: Transaction Summary Endpoint\n";
$url = 'http://127.0.0.1:8000/api/resident/transaction-summary';
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
    echo "✓ Success\n";
    echo "  Total Debit:  ₹{$data['data']['total_debit']}\n";
    echo "  Total Credit: ₹{$data['data']['total_credit']}\n";
    echo "  Balance:      ₹{$data['data']['current_balance']}\n";
    echo "  Outstanding:  ₹{$data['data']['outstanding_amount']}\n";
} else {
    echo "✗ Failed with status {$httpCode}\n";
}

$user->tokens()->delete();
echo "\n✓ Tests completed\n";
