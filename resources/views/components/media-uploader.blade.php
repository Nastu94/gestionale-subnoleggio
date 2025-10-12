@props([
    'label' => 'Upload',
    'action' => '#',      // rotta (POST) del RentalMediaController
    'accept' => '*/*',
    'multiple' => false,
])

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="space-y-2">
    @csrf
    <label class="form-control w-full">
        <div class="label">
            <span class="label-text">{{ $label }}</span>
        </div>
        <input type="file" name="file" @if($multiple) multiple @endif accept="{{ $accept }}" class="file-input file-input-bordered w-full" required>
    </label>
    <div class="flex justify-end">
        <button class="btn btn-neutral">Carica</button>
    </div>
</form>
