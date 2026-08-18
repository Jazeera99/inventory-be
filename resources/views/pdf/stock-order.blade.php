<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Stock Order #{{ $order->order_no }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333333;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            color: #111827;
            margin: 0 0 4px 0;
            text-transform: uppercase;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 4px;
            background-color: #e5e7eb;
            color: #374151;
        }
        .info-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }
        .info-box td {
            padding: 10px;
            width: 50%;
            vertical-align: top;
        }
        .info-title {
            font-size: 10px;
            text-transform: uppercase;
            color: #6b7280;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: fixed;
        }
        .items-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding: 8px 6px;
            border: 1px solid #d1d5db;
        }
        .items-table td {
            padding: 8px 6px;
            border: 1px solid #e5e7eb;
            word-wrap: break-word;
            vertical-align: top;
        }
        .items-table tfoot td {
            background-color: #f9fafb;
            font-weight: bold;
            border: 1px solid #d1d5db;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: monospace; }
        .footer-notes {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9fafb;
            border-left: 3px solid #3b82f6;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $typeLabel = match($order->type) {
            'INBOUND' => 'PURCHASE ORDER',
            'OUTBOUND' => 'SALES ORDER',
            'RETURN_OUT' => 'RETUR SUPPLIER',
            'RETURN_IN' => 'RETUR CUSTOMER',
            default => 'STOCK ORDER'
        };
        $partyName = match($order->type) {
            'INBOUND', 'RETURN_OUT' => $order->supplier?->supplier_name ?? $order->supplier?->name ?? '-',
            default => $order->customer?->customer_name ?? $order->customer?->name ?? '-'
        };
    @endphp

    <table class="header-table">
        <tr>
            <td>
                <div class="title">{{ $typeLabel }}</div>
                <div>No. Order: <strong class="font-mono">{{ $order->order_no }}</strong></div>
            </td>
            <td class="text-right">
                <div>Status: <span class="badge">{{ $order->status }}</span></div>
                <div>Tgl Order: {{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</div>
                @if($order->expected_date)
                    <div>Estimasi: {{ \Carbon\Carbon::parse($order->expected_date)->format('d/m/Y') }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="info-box">
        <tr>
            <td>
                <div class="info-title">
                    {{ in_array($order->type, ['INBOUND', 'RETURN_OUT']) ? 'Informasi Supplier' : 'Informasi Customer' }}
                </div>
                <strong>{{ $partyName }}</strong>
            </td>
            <td>
                <div class="info-title">Detail Dokumen</div>
                <div>Total Barang: {{ count($order->items) }} Jenis</div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                @if($isSuperadmin)
                    <th style="width: 20%;">SKU</th>
                    <th style="width: 32%;">Nama Produk</th>
                    <th class="text-right" style="width: 10%;">Dipesan</th>
                    <th class="text-right" style="width: 10%;">Terpenuhi</th>
                    <th class="text-right" style="width: 14%;">Harga Satuan</th>
                    <th class="text-right" style="width: 14%;">Subtotal</th>
                @else
                    <th style="width: 25%;">SKU</th>
                    <th style="width: 45%;">Nama Produk</th>
                    <th class="text-right" style="width: 15%;">Dipesan</th>
                    <th class="text-right" style="width: 15%;">Terpenuhi</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php $grandTotal = 0; @endphp
            @foreach($order->items as $item)
                @php
                    $subtotal = $item->qty_fulfilled * $item->unit_price;
                    $grandTotal += $subtotal;
                @endphp
            <tr>
                <td class="font-mono">{{ $item->product_sku }}</td>
                <td>{{ $item->product->product_name ?? '-' }}</td>
                <td class="text-right">{{ $item->qty_ordered }}</td>
                <td class="text-right">{{ $item->qty_fulfilled }}</td>
                @if($isSuperadmin)
                    <td class="text-right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                @endif
            </tr>
            @endforeach
        </tbody>
        @if($isSuperadmin)
            <tfoot>
                <tr>
                    <td colspan="5" class="text-right">Grand Total:</td>
                    <td class="text-right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if($order->notes)
    <div class="footer-notes">
        <strong>Catatan:</strong> {{ $order->notes }}
    </div>
    @endif
</body>
</html>
