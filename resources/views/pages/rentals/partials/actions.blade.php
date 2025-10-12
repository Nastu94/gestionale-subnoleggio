{{-- Ogni pulsante rispetta le policy via middleware/authorize nel controller --}}
<form method="POST" action="{{ route('rentals.checkout', $rental) }}" class="space-y-2">
    @csrf
    <button class="btn btn-primary btn-block" @disabled(!in_array($rental->status,['draft','reserved']))>Checkout</button>
</form>

<form method="POST" action="{{ route('rentals.inuse', $rental) }}" class="space-y-2">
    @csrf
    <button class="btn btn-outline btn-block" @disabled($rental->status!=='checked_out')>Passa a In-use</button>
</form>

<form method="POST" action="{{ route('rentals.checkin', $rental) }}" class="space-y-2">
    @csrf
    <button class="btn btn-accent btn-block" @disabled(!in_array($rental->status,['checked_out','in_use']))>Check-in</button>
</form>

<form method="POST" action="{{ route('rentals.close', $rental) }}" class="space-y-2">
    @csrf
    <button class="btn btn-success btn-block" @disabled($rental->status!=='checked_in')>Chiudi</button>
</form>

<div class="grid grid-cols-2 gap-2">
    <form method="POST" action="{{ route('rentals.cancel', $rental) }}">
        @csrf
        <button class="btn btn-error btn-block" @disabled(!in_array($rental->status,['draft','reserved']))>Cancella</button>
    </form>
    <form method="POST" action="{{ route('rentals.noshow', $rental) }}">
        @csrf
        <button class="btn btn-warning btn-block" @disabled($rental->status!=='reserved')>No-show</button>
    </form>
</div>
