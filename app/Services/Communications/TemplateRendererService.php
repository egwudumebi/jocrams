<?php

namespace App\Services\Communications;

use App\Models\NotificationTemplate;

class TemplateRendererService
{
    /** @param array<string, string> $variables */
    public function render(NotificationTemplate $template, array $variables): array
    {
        $subject = $template->subject
            ? $this->replaceVariables($template->subject, $variables)
            : null;

        $body = $this->replaceVariables($template->body, $variables);

        return compact('subject', 'body');
    }

    /** @param array<string, string> $variables */
    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace(['{{'.$key.'}}', '{{ '.$key.' }}'], $value, $content);
        }

        return $content;
    }
}
