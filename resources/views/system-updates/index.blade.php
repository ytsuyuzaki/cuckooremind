<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">システム更新</h1>
            <form method="POST" action="{{ route('system-updates.refresh') }}">
                @csrf
                <x-secondary-button type="submit">更新を確認</x-secondary-button>
            </form>
        </div>
    </x-slot>

    <div class="mx-auto mt-8 max-w-5xl space-y-8">
        @if ($errors->any())
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
            </div>
        @endif

        @if ($error)
            <div class="rounded-md bg-yellow-50 p-4 text-sm text-yellow-800">
                GitHubから更新情報を取得できませんでした。通常のアプリ機能は引き続き利用できます。<br>{{ $error }}
            </div>
        @endif

        <section class="overflow-hidden rounded-lg bg-white shadow">
            <div class="grid gap-6 px-6 py-5 sm:grid-cols-3">
                <div><dt class="text-sm text-gray-500">現在のバージョン</dt><dd class="mt-1 text-xl font-semibold">{{ cuckooremind_version() }}</dd></div>
                <div><dt class="text-sm text-gray-500">最新バージョン</dt><dd class="mt-1 text-xl font-semibold">{{ $releases[0]['version'] ?? '取得できません' }}</dd></div>
                <div x-data="{ status: @js($state['status'] ?? 'idle') }"
                    x-init="if (status === 'running') { const timer = setInterval(async () => { try { const response = await fetch(@js(route('system-updates.status')), { headers: { Accept: 'application/json' } }); if (response.ok) { const data = await response.json(); status = data.status; if (status !== 'running') clearInterval(timer); } } catch (_) {} }, 3000) }">
                    <dt class="text-sm text-gray-500">更新状態</dt><dd class="mt-1 text-xl font-semibold" x-text="status"></dd>
                </div>
            </div>
            @if (!empty($state['error']))
                <div class="border-t border-red-100 bg-red-50 px-6 py-4 text-sm text-red-700">{{ $state['error'] }}</div>
            @endif
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">更新前診断</h2>
            <ul class="mt-4 divide-y divide-gray-100">
                @foreach ($environmentChecks as $check)
                    <li class="flex items-center justify-between gap-4 py-3 text-sm">
                        <span>{{ $check['label'] }}</span>
                        <span class="{{ $check['passed'] ? 'text-green-700' : 'text-red-700' }}">
                            {{ $check['passed'] ? 'OK' : 'NG' }} — {{ $check['message'] }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        @if (!empty($state['started_at']) || !empty($state['finished_at']) || !empty($state['backup']))
            <section class="rounded-lg bg-white p-6 text-sm shadow">
                <h2 class="text-lg font-semibold text-gray-900">直近の更新処理</h2>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    @if (!empty($state['started_at']))<div><dt class="text-gray-500">開始日時</dt><dd>{{ $state['started_at'] }}</dd></div>@endif
                    @if (!empty($state['finished_at']))<div><dt class="text-gray-500">終了日時</dt><dd>{{ $state['finished_at'] }}</dd></div>@endif
                    @if (!empty($state['backup']))<div class="sm:col-span-2"><dt class="text-gray-500">バックアップ</dt><dd class="break-all">{{ $state['backup'] }}</dd></div>@endif
                </dl>
                @if (($state['status'] ?? null) === 'failed')
                    <p class="mt-4 text-gray-700">CLIでの復元: <code class="rounded bg-gray-100 px-2 py-1">php artisan app:update:restore</code></p>
                @endif
            </section>
        @endif

        @if ($availableUpdate)
            <section class="rounded-lg border border-indigo-200 bg-indigo-50 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-indigo-900">{{ $availableUpdate['version'] }} に更新できます</h2>
                <p class="mt-2 text-sm text-indigo-800">更新中は一時的にメンテナンスモードになります。実行前にバックアップ先と更新内容を確認してください。</p>
                <form method="POST" action="{{ route('system-updates.update') }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="version" value="{{ $availableUpdate['version'] }}">
                    @unless ($isSqlite)
                        <label class="flex items-start gap-3 text-sm text-gray-800">
                            <input type="checkbox" name="database_backup_confirmed" value="1" required class="mt-1 rounded border-gray-300 text-indigo-600">
                            MySQL/PostgreSQLの外部バックアップを取得済みです
                        </label>
                    @endunless
                    <div class="max-w-md">
                        <x-label for="current_password" value="確認のため現在のパスワードを入力してください" />
                        <x-input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="mt-1 block w-full" />
                    </div>
                    <x-button type="submit" onclick="return confirm('アプリケーションを更新します。続行しますか？')">ダウンロードして更新</x-button>
                </form>
            </section>
        @elseif (!$error)
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">現在のバージョンは最新です。</div>
        @endif

        <section>
            <h2 class="mb-4 text-lg font-semibold text-gray-900">更新履歴</h2>
            <div class="space-y-5">
                @forelse ($releases as $release)
                    <article class="rounded-lg bg-white p-6 shadow">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-lg font-semibold">{{ $release['name'] ?: $release['version'] }}</h3>
                            <div class="text-sm text-gray-500">{{ $release['published_at'] }}</div>
                        </div>
                        <div class="mt-4 space-y-3 text-sm leading-6 text-gray-700 [&_a]:text-indigo-600 [&_a]:underline [&_h2]:font-semibold [&_li]:ml-5 [&_li]:list-disc">
                            {!! $release['body_html'] !!}
                        </div>
                        @if ($release['url'])<a href="{{ $release['url'] }}" rel="noopener noreferrer" target="_blank" class="mt-4 inline-block text-sm text-indigo-600 underline">GitHub Releaseを開く</a>@endif
                    </article>
                @empty
                    <div class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow">表示できる更新履歴がありません。</div>
                @endforelse
            </div>
        </section>
    </div>
</x-app-layout>
