<?php

namespace App\Services\Updates;

use Illuminate\Support\Str;

class ReleaseNotesRenderer
{
    public function render(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,
        ]);
    }
}
