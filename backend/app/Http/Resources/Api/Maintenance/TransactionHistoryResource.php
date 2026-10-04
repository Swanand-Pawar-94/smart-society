<?php

namespace App\Http\Resources\Api\Maintenance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_type' => $this->transaction_type,
            'category' => $this->category,
            'description' => $this->description,
            'amount' => (string) $this->amount,
            'balance_after' => (string) $this->balance_after,
            'transaction_date' => $this->transaction_date->toDateString(),
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'reference_number' => $this->reference_number,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
