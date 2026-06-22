@if ($availableUpdate)
<a href="{{ route('system-updates.index') }}"
    title="CuckooRemind {{ $availableUpdate['version'] }} が利用できます"
    class="relative -m-2.5 p-2.5 text-indigo-600 hover:text-indigo-700">
    <span class="sr-only">新しいバージョン {{ $availableUpdate['version'] }} が利用できます</span>
    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
    </svg>
    <span class="absolute right-2 top-2 block h-2 w-2 rounded-full bg-red-500 ring-2 ring-white"></span>
</a>
@endif
