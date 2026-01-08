{{-- Pagina informativa: organizzazione archiviata --}}
<x-guest-layout>
    <div class="max-w-md mx-auto mt-10">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                Accesso sospeso
            </h1>

            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">
                La tua organizzazione risulta archiviata, quindi l’accesso alla piattaforma è momentaneamente disabilitato.
            </p>

            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                Se ritieni sia un errore, contatta l’amministrazione.
            </p>

            <div class="mt-5 flex items-center justify-end gap-3">
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-xs font-semibold uppercase
                          hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-300 transition">
                    Torna al login
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
