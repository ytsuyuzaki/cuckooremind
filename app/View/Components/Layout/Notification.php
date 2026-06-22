<?php

namespace App\View\Components\Layout;

use App\Services\Updates\GitHubReleaseService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;
use Throwable;

class Notification extends Component
{
    /** @var array<string, mixed>|null */
    public ?array $availableUpdate = null;

    /**
     * Create a new component instance.
     */
    public function __construct(GitHubReleaseService $releases)
    {
        if (! Auth::user()?->is_system_admin) {
            return;
        }

        try {
            $this->availableUpdate = $releases->availableUpdate();
        } catch (Throwable) {
            $this->availableUpdate = null;
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.layout.notification');
    }
}
