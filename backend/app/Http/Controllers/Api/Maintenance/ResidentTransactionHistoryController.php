<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Maintenance\TransactionHistoryResource;
use App\Models\ResidentTransaction;
use App\Services\TransactionLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ResidentTransactionHistoryController extends Controller
{
    public function __construct(
        private readonly TransactionLedgerService $ledger,
    ) {}

    /**
     * Get transaction history with summary
     */
    public function index(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;
        abort_unless($resident, 403, 'Resident profile not found');

        $filters = $request->validate([
            'transaction_type' => 'nullable|in:DEBIT,CREDIT',
            'category' => 'nullable|string',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ResidentTransaction::query()
            ->where('resident_id', $resident->id)
            ->where('status', ResidentTransaction::STATUS_COMPLETED);

        // Apply filters
        if (!empty($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate('transaction_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate('transaction_date', '<=', $filters['to_date']);
        }

        // Get summary statistics
        $allTransactions = ResidentTransaction::query()
            ->where('resident_id', $resident->id)
            ->where('status', ResidentTransaction::STATUS_COMPLETED);

        $totalDebit = (clone $allTransactions)
            ->where('transaction_type', ResidentTransaction::TYPE_DEBIT)
            ->sum('amount');

        $totalCredit = (clone $allTransactions)
            ->where('transaction_type', ResidentTransaction::TYPE_CREDIT)
            ->sum('amount');

        $currentBalance = $this->ledger->getCurrentBalance($resident->id);

        // Get paginated transactions - ordered chronologically (oldest first)
        $perPage = $filters['per_page'] ?? 20;
        $transactions = $query->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->paginate($perPage);

        return response()->json([
            'summary' => [
                'total_debit' => (string) $totalDebit,
                'total_credit' => (string) $totalCredit,
                'current_balance' => (string) $currentBalance,
                'outstanding_amount' => (string) max(0, $currentBalance),
            ],
            'data' => TransactionHistoryResource::collection($transactions->items()),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
        ]);
    }

    /**
     * Get transaction summary only
     */
    public function summary(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;
        abort_unless($resident, 403, 'Resident profile not found');

        $totalDebit = ResidentTransaction::query()
            ->where('resident_id', $resident->id)
            ->where('status', ResidentTransaction::STATUS_COMPLETED)
            ->where('transaction_type', ResidentTransaction::TYPE_DEBIT)
            ->sum('amount');

        $totalCredit = ResidentTransaction::query()
            ->where('resident_id', $resident->id)
            ->where('status', ResidentTransaction::STATUS_COMPLETED)
            ->where('transaction_type', ResidentTransaction::TYPE_CREDIT)
            ->sum('amount');

        $currentBalance = $this->ledger->getCurrentBalance($resident->id);

        return response()->json([
            'data' => [
                'total_debit' => (string) $totalDebit,
                'total_credit' => (string) $totalCredit,
                'current_balance' => (string) $currentBalance,
                'outstanding_amount' => (string) max(0, $currentBalance),
            ],
        ]);
    }

    /**
     * Get category breakdown
     */
    public function categoryBreakdown(Request $request): JsonResponse
    {
        $resident = $request->user()->resident;
        abort_unless($resident, 403, 'Resident profile not found');

        $debitsByCategory = ResidentTransaction::query()
            ->where('resident_id', $resident->id)
            ->where('status', ResidentTransaction::STATUS_COMPLETED)
            ->where('transaction_type', ResidentTransaction::TYPE_DEBIT)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->category => (string) $item->total]);

        $creditsByCategory = ResidentTransaction::query()
            ->where('resident_id', $resident->id)
            ->where('status', ResidentTransaction::STATUS_COMPLETED)
            ->where('transaction_type', ResidentTransaction::TYPE_CREDIT)
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn ($item) => [$item->category => (string) $item->total]);

        return response()->json([
            'data' => [
                'debits' => $debitsByCategory,
                'credits' => $creditsByCategory,
            ],
        ]);
    }
}
