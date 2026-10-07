<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cancelación pedido #{{ $order->id }} | Mekatos</title>
    <style>
        *{box-sizing:border-box}html,body{margin:0;padding:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}.print-actions{padding:18px;text-align:center}.print-actions button{padding:10px 16px;border:0;border-radius:8px;background:#111;color:#fff;font-weight:700;cursor:pointer}.tickets{width:80mm;margin:0 auto}.ticket{width:100%;padding:5mm 3mm}.ticket+.ticket{border-top:1px dashed #777}.ticket h1{text-align:center;font-size:18px;margin:0 0 7px;text-transform:uppercase}.ticket-logo{display:block;width:58mm;height:16mm;margin:0 auto 3mm;object-fit:cover;object-position:center}.ticket .location{text-align:center;font-size:18px;font-weight:800;margin-bottom:8px}.ticket .meta{font-size:18px;line-height:1.45;margin-bottom:9px}.line{display:flex;justify-content:space-between;gap:8px;margin:7px 0;font-size:18px;font-weight:700}.line-name{min-width:0}.line-price{white-space:nowrap}.line-note{font-size:18px;font-weight:500;margin:2px 0 7px;padding-left:10px;line-height:1.35}.general-note{border-top:1px dashed #777;margin-top:9px;padding-top:8px;font-size:18px;line-height:1.4}.general-note strong{display:block;text-transform:uppercase;margin-bottom:3px}.takeaway-label{text-align:center;font-size:18px;font-weight:900;border:2px solid #111;padding:5px;margin:7px 0 10px}.change-title{font-size:18px;font-weight:900;text-align:center;margin:10px 0 6px;padding:5px;border:2px solid #111}.void-line .line-name{text-decoration:line-through}@media print{.print-actions{display:none}.tickets{width:80mm}.ticket{break-after:page}.ticket:last-child{break-after:auto}@page{size:80mm auto;margin:0}}
    </style>
</head>
<body>
    <div class="print-actions"><button type="button" onclick="window.print()">Imprimir nuevamente</button></div>
    <main class="tickets">
        @foreach(['Cocina' => $kitchenItems, 'Bebidas' => $beverageItems] as $station => $items)
            @php $stationGeneral = $station === 'Cocina' ? ($generalSauces ?? collect()) : collect(); @endphp
            @if($items->isNotEmpty() || $stationGeneral->isNotEmpty())
                <section class="ticket">
                    <img class="ticket-logo" src="{{ asset('images/mekatos-logo.png') }}" alt="Mekatos Comidas Rápidas">
                    <h1>{{ $station }}</h1>
                    @if($isReprint ?? false)<div class="meta"><div><strong>*** REIMPRESIÓN ***</strong></div></div>@endif
                    <div class="takeaway-label">❌ PEDIDO #{{ $order->id }} CANCELADO — NO PREPARAR</div>
                    <div class="location">{{ in_array($order->type?->value, ['PARA_LLEVAR','DOMICILIO'], true) ? ($order->type?->value === 'DOMICILIO' ? 'DOMICILIO' : 'PARA LLEVAR') : 'MESA '.($order->tableSession?->restaurantTable?->number ?? '—') }}</div>
                    <div class="meta"><div><strong>Cancelado:</strong> {{ $cancelledAt?->format('H:i') ?? '—' }}</div><div><strong>Responsable:</strong> {{ $order->handledBy?->name ?? 'Pedido QR' }}</div></div>
                    @include('print.partials.void-lines', ['voids' => $items, 'generalVoids' => $stationGeneral])
                    @if($reason !== '')<div class="general-note"><strong>Motivo</strong>{{ $reason }}</div>@endif
                </section>
            @endif
        @endforeach
    </main>
    <script>window.addEventListener('load',()=>setTimeout(()=>window.print(),250));window.addEventListener('afterprint',()=>setTimeout(()=>window.close(),150));</script>
</body>
</html>
