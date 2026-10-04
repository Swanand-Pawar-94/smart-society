class TransactionSummary {
  final String totalDebit;
  final String totalCredit;
  final String currentBalance;
  final String outstandingAmount;

  const TransactionSummary({
    required this.totalDebit,
    required this.totalCredit,
    required this.currentBalance,
    required this.outstandingAmount,
  });

  factory TransactionSummary.fromJson(Map<String, dynamic> json) {
    return TransactionSummary(
      totalDebit: json['total_debit']?.toString() ?? '0.00',
      totalCredit: json['total_credit']?.toString() ?? '0.00',
      currentBalance: json['current_balance']?.toString() ?? '0.00',
      outstandingAmount: json['outstanding_amount']?.toString() ?? '0.00',
    );
  }

  double get totalDebitValue => double.tryParse(totalDebit) ?? 0.0;
  double get totalCreditValue => double.tryParse(totalCredit) ?? 0.0;
  double get currentBalanceValue => double.tryParse(currentBalance) ?? 0.0;
  double get outstandingAmountValue => double.tryParse(outstandingAmount) ?? 0.0;
}

class ResidentTransaction {
  final int id;
  final String transactionType; // 'DEBIT' or 'CREDIT'
  final String category;
  final String description;
  final String amount;
  final String balanceAfter;
  final DateTime transactionDate;
  final String? referenceType;
  final int? referenceId;
  final String? referenceNumber;
  final String? paymentMethod;
  final String status;
  final DateTime? createdAt;

  const ResidentTransaction({
    required this.id,
    required this.transactionType,
    required this.category,
    required this.description,
    required this.amount,
    required this.balanceAfter,
    required this.transactionDate,
    this.referenceType,
    this.referenceId,
    this.referenceNumber,
    this.paymentMethod,
    required this.status,
    this.createdAt,
  });

  factory ResidentTransaction.fromJson(Map<String, dynamic> json) {
    return ResidentTransaction(
      id: json['id'] as int,
      transactionType: json['transaction_type']?.toString() ?? 'DEBIT',
      category: json['category']?.toString() ?? '',
      description: json['description']?.toString() ?? '',
      amount: json['amount']?.toString() ?? '0.00',
      balanceAfter: json['balance_after']?.toString() ?? '0.00',
      transactionDate: DateTime.parse(json['transaction_date'] as String),
      referenceType: json['reference_type']?.toString(),
      referenceId: json['reference_id'] as int?,
      referenceNumber: json['reference_number']?.toString(),
      paymentMethod: json['payment_method']?.toString(),
      status: json['status']?.toString() ?? 'COMPLETED',
      createdAt: json['created_at'] != null 
          ? DateTime.parse(json['created_at'] as String)
          : null,
    );
  }

  bool get isDebit => transactionType == 'DEBIT';
  bool get isCredit => transactionType == 'CREDIT';
  
  double get amountValue => double.tryParse(amount) ?? 0.0;
  double get balanceAfterValue => double.tryParse(balanceAfter) ?? 0.0;

  String get categoryDisplayName {
    switch (category) {
      case 'MAINTENANCE':
        return 'Maintenance';
      case 'WATER':
        return 'Water';
      case 'ELECTRICITY':
        return 'Electricity';
      case 'PARKING':
        return 'Parking';
      case 'AMENITY':
        return 'Amenity';
      case 'LATE_FEE':
        return 'Late Fee';
      case 'OTHER_CHARGE':
        return 'Other Charge';
      case 'DISCOUNT':
        return 'Discount';
      case 'PAYMENT':
        return 'Payment';
      case 'REFUND':
        return 'Refund';
      case 'ADJUSTMENT':
        return 'Adjustment';
      default:
        return category;
    }
  }
}

class TransactionHistoryResponse {
  final TransactionSummary summary;
  final List<ResidentTransaction> transactions;
  final TransactionPagination pagination;

  const TransactionHistoryResponse({
    required this.summary,
    required this.transactions,
    required this.pagination,
  });

  factory TransactionHistoryResponse.fromJson(Map<String, dynamic> json) {
    final summaryData = json['summary'] as Map<String, dynamic>?;
    final dataList = json['data'] as List<dynamic>? ?? [];
    final paginationData = json['pagination'] as Map<String, dynamic>?;

    return TransactionHistoryResponse(
      summary: summaryData != null
          ? TransactionSummary.fromJson(summaryData)
          : const TransactionSummary(
              totalDebit: '0.00',
              totalCredit: '0.00',
              currentBalance: '0.00',
              outstandingAmount: '0.00',
            ),
      transactions: dataList
          .map((item) => ResidentTransaction.fromJson(item as Map<String, dynamic>))
          .toList(),
      pagination: paginationData != null
          ? TransactionPagination.fromJson(paginationData)
          : const TransactionPagination(
              currentPage: 1,
              lastPage: 1,
              perPage: 20,
              total: 0,
            ),
    );
  }
}

class TransactionPagination {
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  const TransactionPagination({
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  factory TransactionPagination.fromJson(Map<String, dynamic> json) {
    return TransactionPagination(
      currentPage: json['current_page'] as int? ?? 1,
      lastPage: json['last_page'] as int? ?? 1,
      perPage: json['per_page'] as int? ?? 20,
      total: json['total'] as int? ?? 0,
    );
  }

  bool get hasNextPage => currentPage < lastPage;
  bool get hasPreviousPage => currentPage > 1;
}
