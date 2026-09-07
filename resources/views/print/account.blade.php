<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuenta | Mekatos</title>
    <style>
        *{box-sizing:border-box}html,body{margin:0;padding:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}.actions{padding:18px;text-align:center}.actions button{padding:10px 16px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:700;cursor:pointer}.ticket{width:80mm;margin:0 auto;padding:5mm 3mm}.ticket h1{text-align:center;font-size:19px;margin:0 0 4px}.table-title{text-align:center;font-size:15px;font-weight:800;margin-bottom:9px}.meta{text-align:center;font-size:11px;margin-bottom:12px}.line{display:flex;justify-content:space-between;gap:8px;font-size:12px;margin:6px 0}.line span:first-child{min-width:0}.note{font-size:10px;margin:-2px 0 6px 8px}.separator{border-top:1px dashed #777;margin:9px 0}.total{display:flex;justify-content:space-between;font-size:16px;font-weight:800;margin-top:10px}.thanks{text-align:center;font-size:10px;margin-top:15px}@media print{.actions{display:none}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir nuevamente</button></div>
    <main class="ticket">
        <h1>MEKATOS</h1>
        <div class="table-title">MESA {{ $tableSession->restaurantTable?->number ?? '—' }}</div>
        <div class="meta">Cuenta · {{ now()->format('d/m/Y H:i') }}</div>
        @foreach($orders as $order)
            @foreach($order->orderItems as $item)
                <div class="line"><span>{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</span><strong>${{ number_format($item->total,0,',','.') }}</strong></div>
                @if($item->notes)<div class="note">{{ $item->notes }}</div>@endif
            @endforeach
        @endforeach
        <div class="separator"></div>
        <div class="total"><span>TOTAL</span><span>${{ number_format($total,0,',','.') }}</span></div>
        <div class="thanks">Gracias por su visita</div>
    </main>
    <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));window.addEventListener('afterprint',()=>setTimeout(()=>window.close(),150));</script>
</body>
</html>
