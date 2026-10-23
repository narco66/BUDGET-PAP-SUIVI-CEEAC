<?php

namespace Tests\Unit;

use App\Models\EmailTemplate;
use App\Support\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_clean_removes_scripts_and_unsafe_attributes(): void
    {
        $html = '<p onclick="alert(1)">Bonjour</p><script>alert(1)</script><a href="javascript:alert(1)" title="Lien">ouvrir</a>';

        $clean = SafeHtml::clean($html);

        $this->assertStringContainsString('<p>Bonjour</p>', $clean);
        $this->assertStringContainsString('<a title="Lien">ouvrir</a>', $clean);
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
    }

    public function test_email_template_escapes_variables_before_rendering_html(): void
    {
        $template = new EmailTemplate([
            'sujet' => 'Alerte {{titre}}',
            'corps_html' => '<p>{{message}}</p><a href="https://ceeac.int">Lien</a>',
        ]);

        $html = $template->appliquerCorpsSecurise([
            'message' => '<img src=x onerror=alert(1)>',
        ]);

        $this->assertSame('<p>&lt;img src=x onerror=alert(1)&gt;</p><a href="https://ceeac.int">Lien</a>', $html);
    }
}
