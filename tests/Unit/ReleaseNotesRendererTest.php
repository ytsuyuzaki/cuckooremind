<?php

namespace Tests\Unit;

use App\Services\Updates\ReleaseNotesRenderer;
use PHPUnit\Framework\TestCase;

class ReleaseNotesRendererTest extends TestCase
{
    public function test_it_strips_html_and_unsafe_links(): void
    {
        $html = (new ReleaseNotesRenderer)->render("# Update\n<script>alert(1)</script>\n[bad](javascript:alert(1))");

        $this->assertStringContainsString('<h1>Update</h1>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
    }
}
