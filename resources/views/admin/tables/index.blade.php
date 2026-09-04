@extends('layouts.app')
@section('title', 'Mesas | Mekatos')
@section('content')
<div class="page-shell">
    <div class="page-heading"><div><span class="eyebrow">Administración</span><h2>Mesas</h2><p>Gestiona las mesas y los enlaces utilizados para el acceso por QR.</p></div><a class="button button-primary" href="{{ route('admin.tables.create') }}">+ Nueva mesa</a></div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="alert alert-error"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="panel">
        <div class="panel-header"><h3>Listado de mesas</h3><span>{{ $tables->count() }} registradas</span></div>
        <div class="table-wrap"><table class="data-table">
            <thead><tr><th>Mesa</th><th>Nombre</th><th>Capacidad</th><th>Estado</th><th>Acceso QR</th><th class="actions-cell">Acciones</th></tr></thead>
            <tbody>
            @forelse ($tables as $table)
                <tr>
                    <td><strong>#{{ $table->number }}</strong></td>
                    <td>{{ $table->name ?: 'Sin nombre' }}</td>
                    <td>{{ $table->capacity ?? '—' }} personas</td>
                    <td><span class="status {{ $table->status->value === 'AVAILABLE' ? 'status-active' : 'status-inactive' }}">{{ $table->status->value }}</span></td>
                    <td><a href="{{ url('/mesa/'.$table->qr_token) }}" target="_blank" rel="noopener">Abrir mesa</a><small>{{ $table->qr_token }}</small></td>
                    <td class="actions-cell"><a class="button button-small" href="{{ route('admin.tables.edit', $table) }}">Editar</a><form method="POST" action="{{ route('admin.tables.destroy', $table) }}" class="inline-form" onsubmit="return confirm('¿Eliminar esta mesa?')">@csrf @method('DELETE')<button class="button button-small button-danger" type="submit">Eliminar</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-state">Todavía no hay mesas registradas.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </section>
</div>
@endsection
