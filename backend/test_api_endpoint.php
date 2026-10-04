<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "=== Testing Transaction History API ===\n\n";

// Get a resident user and create a token
$user = User::whereHas('resident')->first();
if (!$user) {
    echo "No resident user found!\n";
    exit(1);
}

echo "User: {$user->name}\n";
echo "Email: {$user->email}\n";

// Create a token for authentication
$token = $user->createToken('test-token')->plainTextToken;
echo "Token created: " . substr($token, 0, 20) . "...\n\n";

// Test the API endpoint
$url = 'http://127.0.0.1:8000/api/resident/transaction-history';
echo "Testing: GET {$url}\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: {$httpCode}\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    echo "✓ API Response Success!\n\n";
    
    // Display summary
    if (isset($data['summary'])) {
        echo "=== Summary ===\n";
        echo "Total Debit:      ₹{$data['summary']['total_debit']}\n";
        echo "Total Credit:     ₹{$data['summary']['total_credit']}\n";
        echo "Current Balance:  ₹{$data['summary']['current_balance']}\n";
        echo "Outstanding:      ₹{$data['summary']['outstanding_amount']}\n\n";
    }
    
    // Display transactions
    if (isset($data['data']) && is_array($data['data'])) {
        $count = count($data['data']);
        echo "=== Transactions ({$count}) ===\n";
        
        foreach ($data['data'] as $index => $transaction) {
            if ($index >= 5) {
                echo "... and " . ($count - 5) . " more transactions\n";
                break;
            }
            
            echo "\n" . ($index + 1) . ". {$transaction['description']}\n";
            echo "   Type: {$transaction['transaction_type']}\n";
            echo "   Category: {$transaction['category']}\n";
            echo "   Amount: ₹{$transaction['amount']}\n";
            echo "   Balance After: ₹{$transaction['balance_after']}\n";
            echo "   Date: {$transaction['transaction_date']}\n";
        }
    }
    
    // Display pagination
    if (isset($data['pagination'])) {
        echo "\n=== Pagination ===\n";
        echo "Page {$data['pagination']['current_page']} of {$data['pagination']['last_page']}\n";
        echo "Total: {$data['pagination']['total']} transactions\n";
    }
} else {
    echo "✗ API Error!\n";
    echo "Response: {$response}\n";
}

// Clean up token
$user->tokens()->delete();
echo "\n✓ Test token cleaned up\n";
