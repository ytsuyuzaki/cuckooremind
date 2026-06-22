<!-- Sidebar component, swap this element with another sidebar if you like -->
<div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6 pb-4">
    <div class="flex h-16 shrink-0 items-center">
        <a href="{{ route('top.index') }}"
            class="font-semibold text-xl text-gray-800 leading-tight ml-2 mt-4 h-8 w-auto">
            {{ config('app.name') }}
        </a>
    </div>
    <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            <li>
                <ul role="list" class="-mx-2 space-y-1">
                    <li>
                        <a href="{{ route('top.index') }}"
                            class="{{ request()->routeIs('top.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                            <svg class="h-6 w-6 shrink-0 {{ request()->routeIs('top.*') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600' }}"
                                fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                            </svg>
                            Dashboard
                        </a>
                    </li>

                    @if(Auth::user()->currentTeam)
                    <li>
                        <a href="{{ route('reminders.index') }}"
                            class="{{ request()->routeIs('reminders.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50' }} group flex gap-x-3 rounded-md p-2 text-sm leading-6 font-semibold">
                            <svg class="h-6 w-6 shrink-0 {{ request()->routeIs('reminders.*') ? 'text-indigo-600' : 'text-gray-400 group-hover:text-indigo-600' }}"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Reminders
                        </a>
                    </li>
                    @endif

                </ul>
            </li>

            @if (Laravel\Jetstream\Jetstream::hasTeamFeatures() && Auth::user()->currentTeam)
            <x-layout.team />
            @endif

            <li class="mt-auto">
                @if(Auth::user()->is_system_admin)
                <a href="{{ route('system-updates.index') }}"
                    class="{{ request()->routeIs('system-updates.*') ? 'bg-gray-50 text-indigo-600' : 'text-gray-700 hover:text-indigo-600 hover:bg-gray-50' }} group -mx-2 flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6">
                    <svg class="h-6 w-6 shrink-0 text-gray-400 group-hover:text-indigo-600" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 12c0-1.232-.046-2.453-.138-3.662A4.5 4.5 0 0015.162 4.138 48.654 48.654 0 0012 4.035c-1.061 0-2.116.034-3.162.103a4.5 4.5 0 00-4.2 4.2A48.667 48.667 0 004.5 12c0 1.232.046 2.453.138 3.662a4.5 4.5 0 004.2 4.2c1.046.069 2.1.103 3.162.103 1.061 0 2.116-.034 3.162-.103a4.5 4.5 0 004.2-4.2c.092-1.21.138-2.43.138-3.662zM12 8.25v7.5m3-3L12 15.75 9 12.75" />
                    </svg>
                    システム更新
                </a>
                @endif
                <a href="{{ route('setting.edit') }}"
                    class="group -mx-2 flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-gray-700 hover:text-indigo-600 hover:bg-gray-50">
                    <svg class="h-6 w-6 shrink-0 text-gray-400 group-hover:text-indigo-600" fill="none"
                        viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Settings
                </a>
                <a href="https://cuckooremind.com"
                    class="group -mx-2 flex gap-x-3 rounded-md p-2 text-sm font-semibold leading-6 text-gray-700 hover:text-indigo-600 hover:bg-gray-50">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="w-6 h-6 shrink-0 text-gray-400 group-hover:text-indigo-600">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                    使い方
                </a>
            </li>
        </ul>
    </nav>
</div>
