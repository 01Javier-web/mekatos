@extends('layouts.app')

@section('title', 'Pedidos')

@section('content')

<div class="container">

    <h2>Pedidos</h2>

    @if ($orders->isEmpty())

    <p>No hay pedidos disponibles.</p>

    @else

    @foreach ($orders as $order)

    <div class="order-card">

        <h3>
            Pedido #{{ $order->id }}
        </h3>

        <p>
            Mesa:
            {{ $order->tableSession->restaurantTable->number }}
        </p>

        <p>
            Total:
            RD$ {{ number_format($order->total, 2) }}
        </p>

        <p>
            Estado:
            {{ $order->status->value }}
        </p>

        <a
            class="button"
            href="{{ route('admin.orders.show', $order->id) }}">
            Ver pedido
        </a>

    </div>

    @endforeach

    @endif

</div>

@endsection