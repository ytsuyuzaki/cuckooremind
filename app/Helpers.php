<?php

function cuckooremind_version(): string
{
    $path = base_path('.version');

    return is_file($path) ? trim((string) file_get_contents($path)) : 'v0.0.0';
}
