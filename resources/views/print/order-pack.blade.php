<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comandas | Mekatos</title>
    <style>
        *{box-sizing:border-box}html,body{margin:0;padding:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}.print-actions{padding:18px;text-align:center}.print-actions button{padding:10px 16px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:700;cursor:pointer}.tickets{width:80mm;margin:0 auto}.ticket{width:100%;padding:5mm 3mm}.ticket+.ticket{border-top:1px dashed #777}.ticket h1{text-align:center;font-size:18px;margin:0 0 7px;text-transform:uppercase}.ticket-logo{display:block;width:58mm;height:16mm;margin:0 auto 3mm;object-fit:cover;object-position:center}.ticket .location{text-align:center;font-size:15px;font-weight:800;margin-bottom:8px}.ticket .meta{font-size:11px;line-height:1.45;margin-bottom:9px}.line{display:flex;justify-content:space-between;gap:8px;margin:7px 0;font-size:13px;font-weight:700}.line-name{min-width:0}.line-price{white-space:nowrap}.line-note{font-size:11px;font-weight:500;margin:2px 0 7px;padding-left:10px;line-height:1.35}.general-note{border-top:1px dashed #777;margin-top:9px;padding-top:8px;font-size:11px;line-height:1.4}.general-note strong{display:block;text-transform:uppercase;margin-bottom:3px}.separator{border-top:1px dashed #777;margin:10px 0}.total-line{display:flex;justify-content:space-between;gap:8px;font-size:14px;font-weight:800;margin:5px 0}.grand-total{font-size:17px;margin-top:8px}.takeaway-label{text-align:center;font-size:18px;font-weight:900;border:2px solid #111;padding:5px;margin:7px 0 10px}.ticket-footer{text-align:center;font-size:10px;margin-top:12px}@media print{.print-actions{display:none}.tickets{width:80mm}.ticket{break-after:page}.ticket:last-child{break-after:auto}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Imprimir nuevamente</button></div>
    <main class="tickets">
        @if($kitchenItems->isNotEmpty())
            <section class="ticket">
                <img class="ticket-logo" src="{{ asset('images/mekatos-logo.png') }}" alt="Mekatos Comidas Rápidas">
                <h1>Cocina</h1>
                <div class="location">{{ $order->type?->value === 'PARA_LLEVAR' ? 'PARA LLEVAR' : 'MESA '.($order->tableSession?->restaurantTable?->number ?? '—') }}</div>
                <div class="meta"><div><strong>Hora:</strong> {{ $order->created_at?->format('H:i') }}</div><div><strong>Responsable:</strong> {{ $order->handledBy?->name ?? 'Pedido QR' }}</div></div>
                @foreach($kitchenItems as $item)
                    <div class="line"><span class="line-name">{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</span></div>
                    @if($item->notes)<div class="line-note">Detalle: {{ $item->notes }}</div>@endif
                @endforeach
                @if($order->notes)<div class="general-note"><strong>Nota general</strong>{{ $order->notes }}</div>@endif
            </section>
        @endif
        @if($beverageItems->isNotEmpty())
            <section class="ticket">
                <img class="ticket-logo" src="{{ asset('images/mekatos-logo.png') }}" alt="Mekatos Comidas Rápidas">
                <h1>Bebidas</h1>
                <div class="location">{{ $order->type?->value === 'PARA_LLEVAR' ? 'PARA LLEVAR' : 'MESA '.($order->tableSession?->restaurantTable?->number ?? '—') }}</div>
                <div class="meta"><div><strong>Hora:</strong> {{ $order->created_at?->format('H:i') }}</div><div><strong>Responsable:</strong> {{ $order->handledBy?->name ?? 'Pedido QR' }}</div></div>
                @foreach($beverageItems as $item)
                    <div class="line"><span class="line-name">{{ $item->quantity }} × {{ $item->product?->name ?? 'Jugo' }}</span></div>
                    @if($item->notes)<div class="line-note">Detalle: {{ $item->notes }}</div>@endif
                @endforeach
                @if($order->notes)<div class="general-note"><strong>Nota general</strong>{{ $order->notes }}</div>@endif
            </section>
        @endif

        @if($order->type?->value === 'PARA_LLEVAR')
            <section class="ticket">
                <img class="ticket-logo" src="{{ asset('images/mekatos-logo.png') }}" alt="Mekatos Comidas Rápidas">
                <h1>Pedido completo</h1>
                <div class="takeaway-label">PARA LLEVAR</div>
                <div class="meta">
                    <div><strong>Pedido:</strong> #{{ $order->id }}</div>
                    <div><strong>Hora:</strong> {{ $order->created_at?->format('H:i') }}</div>
                    <div><strong>Responsable:</strong> {{ $order->handledBy?->name ?? 'Pedido QR' }}</div>
                </div>
                @foreach($order->orderItems as $item)
                    <div class="line">
                        <span class="line-name">{{ $item->quantity }} × {{ $item->product?->name ?? 'Producto' }}</span>
                        <span class="line-price">${{ number_format($item->total, 0, ',', '.') }}</span>
                    </div>
                    @if($item->notes)
                        <div class="line-note">Detalle: {{ $item->notes }}</div>
                    @endif
                @endforeach
                <div class="separator"></div>
                <div class="total-line">
                    <span>SUBTOTAL</span>
                    <span>${{ number_format($order->subtotal, 0, ',', '.') }}</span>
                </div>
                @if((int) $order->packaging_fee > 0)
                    <div class="total-line">
                        <span>EMPAQUES</span>
                        <span>${{ number_format($order->packaging_fee, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="separator"></div>
                <div class="total-line grand-total">
                    <span>TOTAL</span>
                    <span>${{ number_format($order->total, 0, ',', '.') }}</span>
                </div>
                @if($order->notes)
                    <div class="general-note">
                        <strong>Nota general</strong>
                        {{ $order->notes }}
                    </div>
                @endif
                <div class="ticket-footer">Pedido #{{ $order->id }} · PARA LLEVAR</div>
            </section>
        @endif

    </main>
    <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));window.addEventListener('afterprint',()=>setTimeout(()=>window.close(),150));</script>
</body>
</html>
