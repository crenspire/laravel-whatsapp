<?php

namespace Crenspire\Whatsapp\Notifications;

use Crenspire\Whatsapp\Exceptions\WhatsappException;
use Crenspire\Whatsapp\WhatsappService;
use Illuminate\Notifications\Notification;

/**
 * Sends notifications as WhatsApp messages
 *
 * Use 'whatsapp' or WhatsappChannel::class in a notification's via() method,
 * and return a WhatsappMessage (or a string for plain text) from toWhatsapp().
 */
class WhatsappChannel
{
    /**
     * Send the notification
     *
     * @return array|null The API response, or null when the notifiable has no WhatsApp route
     *
     * @throws WhatsappException When sending fails
     */
    public function send(object $notifiable, Notification $notification): ?array
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            throw new WhatsappException('Notification '.$notification::class.' is missing a toWhatsapp() method');
        }

        $message = $notification->toWhatsapp($notifiable);

        if (is_string($message)) {
            $message = WhatsappMessage::text($message);
        }

        if (! $message instanceof WhatsappMessage) {
            throw new WhatsappException(
                $notification::class.'::toWhatsapp() must return a string or '.WhatsappMessage::class
            );
        }

        [$to, $tenantId] = $this->route($notifiable, $notification);

        $to = $message->to ?? $to;

        if (! $to) {
            return null;
        }

        if ($message->tenantId === null && $tenantId !== null) {
            $message->tenant($tenantId);
        }

        // Resolve on each send so Whatsapp::fake() applies to channels created earlier
        return $message->send(app(WhatsappService::class), $to);
    }

    /**
     * Get the phone number and tenant from the notifiable's route
     *
     * The route can be a phone number, or ['to' => ..., 'tenant' => ...].
     *
     * @return array{0: string|null, 1: string|null}
     */
    protected function route(object $notifiable, Notification $notification): array
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('whatsapp', $notification)
            : null;

        if (is_array($route)) {
            return [$route['to'] ?? $route['phone'] ?? null, $route['tenant'] ?? null];
        }

        return [$route !== null ? (string) $route : null, null];
    }
}
