<?php

namespace Crenspire\Whatsapp\Console;

use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CheckCommand extends Command
{
    protected $signature = 'whatsapp:check {--tenant= : Check this tenant\'s credentials}';

    protected $description = 'Check the WhatsApp configuration, credentials and webhook setup';

    private bool $failed = false;

    private bool $warned = false;

    public function handle(WhatsappService $whatsapp): int
    {
        $tenantId = $this->option('tenant');
        $config = config('whatsapp');
        $credentials = $tenantId !== null ? ($config['tenants'][$tenantId] ?? null) : $config;

        if ($credentials === null) {
            $this->components->error("Tenant [{$tenantId}] is not configured in config/whatsapp.php.");

            return self::FAILURE;
        }

        $this->components->info('Configuration');
        $this->required('Phone number ID', $credentials['phone_number_id'] ?? null, 'WHATSAPP_PHONE_NUMBER_ID');
        $this->required('Access token', $credentials['access_token'] ?? null, 'WHATSAPP_ACCESS_TOKEN', secret: true);
        $this->optional('Business account ID', $credentials['business_account_id'] ?? $config['business_account_id'] ?? null, 'needed for templates');
        $this->required('Webhook verify token', $config['webhook_verify_token'] ?? null, 'WHATSAPP_WEBHOOK_VERIFY_TOKEN', secret: true);
        $this->optional('Webhook secret', $config['webhook_secret'] ?? null, 'webhook signatures are not verified', secret: true);

        if (! $this->failed) {
            $this->components->info('API access');
            $this->checkPhoneNumber($whatsapp, $tenantId);
            $this->checkSubscriptions($whatsapp, $tenantId, $credentials['business_account_id'] ?? $config['business_account_id'] ?? null);
        }

        $this->components->info('Webhook');
        $this->checkWebhookRoute();

        if ($config['message_log']['enabled'] ?? false) {
            $this->components->info('Message log');
            $this->checkMessageLog();
        }

        $this->newLine();

        if ($this->failed) {
            $this->components->error('Some checks failed.');

            return self::FAILURE;
        }

        $this->warned
            ? $this->components->warn('Checks passed with warnings.')
            : $this->components->info('Everything looks good.');

        return self::SUCCESS;
    }

    private function checkPhoneNumber(WhatsappService $whatsapp, ?string $tenantId): void
    {
        try {
            $number = $whatsapp->getPhoneNumber($tenantId);
        } catch (WhatsappException $e) {
            $this->failure('Access token and phone number', $e->getMessage());

            return;
        }

        $this->passed('Phone number', trim(($number['display_phone_number'] ?? '').' '.($number['verified_name'] ?? '')));

        $rating = $number['quality_rating'] ?? null;

        if ($rating !== null) {
            in_array($rating, ['GREEN', 'NA', 'UNKNOWN'], true)
                ? $this->passed('Quality rating', $rating)
                : $this->warning('Quality rating', "{$rating}: messaging limits may be lowered");
        }
    }

    private function checkSubscriptions(WhatsappService $whatsapp, ?string $tenantId, ?string $businessAccountId): void
    {
        if (empty($businessAccountId)) {
            return;
        }

        try {
            $apps = $whatsapp->getWebhookSubscriptions($tenantId);
        } catch (WhatsappException $e) {
            $this->warning('Webhook subscription', $e->getMessage());

            return;
        }

        if ($apps === []) {
            $this->failure('Webhook subscription', 'no app is subscribed to this business account, so no webhooks will arrive');

            return;
        }

        $appId = config('whatsapp.app_id');
        $ids = array_map(fn ($app) => (string) ($app['whatsapp_business_api_data']['id'] ?? ''), $apps);

        if ($appId && ! in_array((string) $appId, $ids, true)) {
            $this->warning('Webhook subscription', "app {$appId} is not subscribed (subscribed: ".implode(', ', $ids).')');

            return;
        }

        $this->passed('Webhook subscription', count($apps).' app(s) subscribed');
    }

    private function checkWebhookRoute(): void
    {
        if (! Route::has('whatsapp.webhook')) {
            $this->failure('Webhook route', 'the whatsapp.webhook route is not registered');

            return;
        }

        $url = route('whatsapp.webhook');
        $host = parse_url($url, PHP_URL_HOST);

        if (! str_starts_with($url, 'https://')) {
            $this->warning('Callback URL', "{$url} (Meta requires HTTPS; check APP_URL)");
        } elseif (in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with((string) $host, '.test')) {
            $this->warning('Callback URL', "{$url} (Meta can't reach a local address; use a tunnel such as ngrok)");
        } else {
            $this->passed('Callback URL', $url);
        }
    }

    private function checkMessageLog(): void
    {
        try {
            $exists = Schema::connection(config('whatsapp.message_log.connection'))->hasTable('whatsapp_messages');
        } catch (Throwable $e) {
            $this->failure('Database table', $e->getMessage());

            return;
        }

        $exists
            ? $this->passed('Database table', 'whatsapp_messages')
            : $this->failure('Database table', 'whatsapp_messages is missing; run php artisan vendor:publish --tag=whatsapp-migrations and migrate');
    }

    private function required(string $label, mixed $value, string $env, bool $secret = false): void
    {
        empty($value)
            ? $this->failure($label, "not set ({$env})")
            : $this->passed($label, $secret ? 'set' : (string) $value);
    }

    private function optional(string $label, mixed $value, string $impact, bool $secret = false): void
    {
        empty($value)
            ? $this->warning($label, "not set, {$impact}")
            : $this->passed($label, $secret ? 'set' : (string) $value);
    }

    private function passed(string $label, string $detail): void
    {
        $this->components->twoColumnDetail($label, "<fg=green>✓</> {$detail}");
    }

    private function warning(string $label, string $detail): void
    {
        $this->warned = true;
        $this->components->twoColumnDetail($label, "<fg=yellow>!</> {$detail}");
    }

    private function failure(string $label, string $detail): void
    {
        $this->failed = true;
        $this->components->twoColumnDetail($label, "<fg=red>✗</> {$detail}");
    }
}
