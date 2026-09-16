<?php

namespace Crenspire\Whatsapp\Console;

use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Console\Command;

class TemplatesCommand extends Command
{
    protected $signature = 'whatsapp:templates
        {--status= : Only show templates with this status, e.g. APPROVED or REJECTED}
        {--category= : Only show templates in this category}
        {--language= : Only show templates in this language}
        {--tenant= : List templates for this tenant}
        {--json : Output the templates as JSON}';

    protected $description = 'List the WhatsApp message templates in your business account';

    public function handle(WhatsappService $whatsapp): int
    {
        $filters = array_filter([
            'status' => $this->option('status') ? strtoupper($this->option('status')) : null,
            'category' => $this->option('category') ? strtoupper($this->option('category')) : null,
            'language' => $this->option('language'),
            'limit' => 100,
        ]);

        try {
            $templates = [];

            do {
                $page = $whatsapp->getTemplates($filters, $this->option('tenant'));
                $templates = array_merge($templates, $page['data'] ?? []);
                $filters['after'] = $page['paging']['cursors']['after'] ?? null;
            } while ($filters['after'] !== null && isset($page['paging']['next']));
        } catch (WhatsappException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($templates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        if ($templates === []) {
            $this->components->info('No templates found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Name', 'Language', 'Category', 'Status', 'ID'],
            array_map(fn ($t) => [
                $t['name'] ?? '',
                $t['language'] ?? '',
                $t['category'] ?? '',
                $t['status'] ?? '',
                $t['id'] ?? '',
            ], $templates)
        );

        return self::SUCCESS;
    }
}
