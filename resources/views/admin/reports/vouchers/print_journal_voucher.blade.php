<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Journal Voucher</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .voucher-container {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 2px;
            margin: 2px auto;
            max-width: 1200px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .voucher-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .voucher-header h2 {
            color: #007bff;
            font-weight: bold;
        }
        .voucher-details th {
            background-color: #007bff;
            color: #fff;
        }
        .voucher-details td, .voucher-details th {
            padding: 12px;
            border: 1px solid #ddd;
        }
        .voucher-details tfoot td {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .signature-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px dashed #007bff;
            text-align: right;
        }
        .signature-section p {
            margin-bottom: 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="voucher-container">
        <!-- Voucher Header -->
        <div class="voucher-header">
            <h2>Journal Voucher</h2>
        </div>

        <!-- Voucher Details in 4 Columns -->
        <table class="table table-bordered voucher-details">
            <tr>
                <th>Voucher No.</th>
                <td>JV-{{get_jv_number($voucher->voucher_number)}}</td>
                <th>Date</th>
                <td>{{ $voucher->date }}</td>
            </tr>
            <tr>
                <th>Reference</th>
                <td>{{ $voucher->reference }}</td>
                <th>Description</th>
                <td>{{ $voucher->description }}</td>
            </tr>
        </table>

        <!-- Journal Entries -->
        <h4 class="mt-4">Journal Entries</h4>
        <table class="table table-bordered voucher-details">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Sub-Account</th>
                    <th>Description</th>
                    <th>Debit</th>
                    <th>Credit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($voucher->details as $detail)
                    <tr>
                        <td>{{ $detail->account->headAccounting->name ?? 'N/A' }}</td>
                        <td>{{ $detail->account->subheadAccounting->name ?? 'N/A' }}</td>
                        <td>{{ $detail->description }}</td>
                        <td>{{ number_format($detail->credit, 2) }}</td>
                        <td>{{ number_format($detail->debit, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                    <td><strong>{{ number_format($voucher->total_credit, 2) }}</strong></td>
                    <td><strong>{{ number_format($voucher->total_debit, 2) }}</strong></td>
                </tr>
            </tfoot>
        </table>

        <!-- Signature Section -->
        <div class="signature-section">
            <p>Sign by Admin:</p>
            <p>___________________________</p>
            <p>Date: {{ now()->format('d-m-Y') }}</p>
        </div>
    </div>

    <!-- Bootstrap JS (Optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Automatically trigger print when the page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>