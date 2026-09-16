<?php

namespace Crenspire\Whatsapp\Console;

use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'whatsapp:test
        {phone : The phone number to send to, with country code}
        {--text= : Send a text message instead of a template (only works within 24 hours of the recipient messaging you)}
        {--template=hello_world : The template to send}
        {--language=en_US : The template language}
        {--tenant= : Send from this tenant}';

    protected $description = 'Send a test WhatsApp message';

    public function handle(WhatsappService $whatsapp): int
    {
        $phone = $this->argument('phone');
        $tenant = $this->option('tenant');

        try {
            $response = $this->option('text') !== null
                ? $whatsapp->sendTextMessage($phone, $this->option('text'), tenantId: $tenant)
                : $whatsapp->sendTemplateMessage($phone, $this->option('template'), [], $this->option('language'), $tenant);
        } catch (WhatsappException $e) {
            $this->components->error($e->getMessage());

            if ($e->getErrorCode() !== null) {
                $this->components->twoColumnDetail('Meta error code', (string) $e->getErrorCode());
            }

            return self::FAILURE;
        }

        $this->components->info("Message sent to {$phone}.");
        $this->components->twoColumnDetail('Message ID', $response['messages'][0]['id'] ?? 'unknown');

        return self::SUCCESS;
    }
}
