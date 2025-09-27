{{-- resources/views/organizations/index.blade.php --}}
<x-app-layout>
    <div class="p-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">Renter (Organizzazioni)</h1>
            <a href="{{ route('organizations.create') }}"
               class="px-3 py-2 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
               Nuovo renter
            </a>
        </div>

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Placeholder elenco organizations.
            </p>
        </div>
    </div>
</x-app-layout>
