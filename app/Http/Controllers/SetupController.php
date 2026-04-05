<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WriteDotenvService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Fortify\Contracts\RegisterResponse;
use Laravel\Fortify\Fortify;

class SetupController extends Controller
{
    public function __construct(
        protected StatefulGuard $guard,
        protected WriteDotenvService $writeDotenvService,
    ) {}

    public function create(Request $request)
    {
        if (! config('app.setup')) {
            return redirect()->to(route('setup.user.create'));
        }

        // TODO: サーバー環境を精査して必要ライブラリが存在するか確認

        return view('setup.create');
    }

    public function store(Request $request)
    {
        $host = getenv('HTTP_HOST');
        $url = (getenv('HTTPS') ? 'https' : 'http').'://'.$host;
        $email = 'cuckoo@'.$host;
        $database = base_path('storage/db.sqlite');

        $this->writeDotenvService->setValue('APP_URL', $url);
        $this->writeDotenvService->setValue('DB_DATABASE', $database);
        $this->writeDotenvService->setValue('SESSION_DRIVER', 'database');
        $this->writeDotenvService->setValue('MAIL_FROM_ADDRESS', $email);
        // APP_KEY の設定
        Artisan::call('key:generate', ['--force' => true]);
        $this->writeDotenvService->setValue('APP_SETUP', 'false');
        Artisan::call('cache:clear');

        return back();
    }

    public function userCreate()
    {
        if (User::count()) {
            return redirect()->to(route('login'));
        }

        return view('setup.user.create');
    }

    public function userStore(
        Request $request,
        CreatesNewUsers $creator
    ): RegisterResponse {
        if (config('fortify.lowercase_usernames')) {
            $request->merge([
                Fortify::username() => Str::lower($request->{Fortify::username()}),
            ]);
        }

        event(new Registered($user = $creator->create($request->all())));

        $this->guard->login($user);

        return app(RegisterResponse::class);
    }
}
