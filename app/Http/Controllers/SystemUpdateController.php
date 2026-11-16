<?php

namespace App\Http\Controllers;

use App\Services\Updates\ApplicationUpdater;
use App\Services\Updates\GitHubReleaseService;
use App\Services\Updates\ReleaseNotesRenderer;
use App\Services\Updates\UpdateStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        ]);

        try {
            $release = $releases->availableUpdate(true);
            if (! $release || ! hash_equals($release['version'], $request->string('version')->toString())) {
                return back()->withErrors(['update' => '指定された更新版を確認できませんでした。']);
            }

            $updater->update(
                $release,
                $request->user()->id
            );

            return redirect()->route('system-updates.index')->with('success', 'アプリケーションを更新しました。');
        } catch (Throwable $exception) {
            return redirect()->route('system-updates.index')->withErrors(['update' => $exception->getMessage()]);
        }
    }

    public function downloadEnvironment(): BinaryFileResponse
    {
        $path = app()->environmentFilePath();
        abort_unless(is_file($path) && is_readable($path), 404);

        $response = response()->download($path, '.env', ['Content-Type' => 'application/octet-stream']);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function downloadDatabase(): BinaryFileResponse
    {
        $database = DB::connection();
        abort_unless($database->getDriverName() === 'sqlite', 404);

        $path = tempnam(sys_get_temp_dir(), 'cuckooremind-backup-');
        abort_if($path === false, 500, 'SQLiteデータベースをバックアップできません。');

        try {
            // SQLite creates a consistent standalone copy, including committed WAL changes.
            $database->statement('VACUUM main INTO ?', [$path]);

            $response = response()->download($path, 'database.sqlite', ['Content-Type' => 'application/vnd.sqlite3']);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response->deleteFileAfterSend();
        } catch (Throwable $exception) {
            unlink($path);

            throw $exception;
        }
    }

    public function status(UpdateStateStore $state): JsonResponse
    {
        return response()->json($state->get());
    }
}
