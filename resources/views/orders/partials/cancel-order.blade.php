{{-- Cancelar pedido (ADMIN y MESERO, solo PENDIENTE o POR COBRAR): pide un motivo obligatorio,
     que queda en el historial del pedido. El pedido no se elimina. --}}
<details class="cancel-order">
    <summary class="button button-danger">❌ Cancelar pedido</summary>
    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" onsubmit="return confirm('¿Cancelar el pedido #{{ $order->id }}? No cuenta como venta y no se puede deshacer.');">
        @csrf @method('PUT')
        <label>Motivo de la cancelación<textarea name="reason" rows="2" maxlength="500" required placeholder="Ej. El cliente desistió del pedido"></textarea></label>
        <button class="button button-danger" type="submit">Confirmar cancelación</button>
    </form>
</details>
