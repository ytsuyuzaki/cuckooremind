<?php

namespace App\Http\Controllers;

use App\Services\Updates\ApplicationUpdater;
use App\Services\Updates\GitHubReleaseService;
use App\Services\Updates\ReleaseNotesRenderer;
use App\Services\Updates\UpdateStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SystemUpdateController extends Controller
{
    public function index(
        GitHubReleaseService $releases,
        ReleaseNotesRenderer $renderer,
        UpdateStateStore $state,
        ApplicationUpdater $updater,
    ): View {
        $error = null;
        try {
            $items = collect($releases->releases())->map(function (array $release) use ($renderer): array {
                $release['body_html'] = $renderer->render($release['body']);

                return $release;
            })->all();
        } catch (Throwable $exception) {
            $items = [];
            $error = $exception->getMessage();
        }

        return view('system-updates.index', [
            'releases' => $items,
            'availableUpdate' => $items && version_compare(ltrim($items[0]['version'], 'vV'), ltrim(cuckooremind_version(), 'vV'), '>') ? $items[0] : null,
            'state' => $state->get(),
            'error' => $error,
            'isSqlite' => config('database.default') === 'sqlite',
            'environmentChecks' => $updater->environmentChecks((int) ($items[0]['assets'][0]['size'] ?? 0)),
        ]);
    }

    public function refresh(GitHubReleaseService $releases): RedirectResponse
    {
        try {
            $releases->releases(true);

            return back()->with('success', '更新情報を再取得しました。');
        } catch (Throwable $exception) {
            return back()->withErrors(['update' => $exception->getMessage()]);
        }
    }

    public function update(
        Request $request,
        GitHubReleaseService $releases,
        ApplicationUpdater $updater,
    ): RedirectResponse {
        $request->validate([
            'version' => ['required', 'string', 'max:64'],
            'current_password' => ['required', 'current_password'],
            'database_backup_confirmed' => config('database.default') === 'sqlite'
                ? ['nullable']
                : ['required', 'accepted'],
        ]);

        try {
            $release = $releases->availableUpdate(true);
            if (! $release || ! hash_equals($release['version'], $request->string('version')->toString())) {
                return back()->withErrors(['update' => '指定された更新版を確認できませんでした。']);
            }

            $updater->update(
                $release,
                $request->user()->id,
                $request->boolean('database_backup_confirmed')
            );

            return redirect()->route('system-updates.index')->with('success', 'アプリケーションを更新しました。');
        } catch (Throwable $exception) {
            return redirect()->route('system-updates.index')->withErrors(['update' => $exception->getMessage()]);
        }
    }

    public function status(UpdateStateStore $state): JsonResponse
    {
        return response()->json($state->get());
    }
}
