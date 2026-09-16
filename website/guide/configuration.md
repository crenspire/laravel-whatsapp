# Configuration reference

Publish the config file with `php artisan vendor:publish --tag=whatsapp-config`. Most options can be set from `.env`.

## Credentials

| Option | Environment variable | Description |
|---|---|---|
| `phone_number_id` | `WHATSAPP_PHONE_NUMBER_ID` | The phone number to send from |
| `access_token` | `WHATSAPP_ACCESS_TOKEN` | A system user access token |
| `business_account_id` | `WHATSAPP_BUSINESS_ACCOUNT_ID` | Needed for template management |
| `app_id` | `WHATSAPP_APP_ID` | Needed to upload sample media for templates |
| `tenants` | — | Extra phone numbers. See [Multiple phone numbers](./multiple-numbers). |

## Webhooks

| Option | Environment variable | Default | Description |
|---|---|---|---|
| `webhook_verify_token` | `WHATSAPP_WEBHOOK_VERIFY_TOKEN` | — | The token you enter in Meta's webhook settings |
| `webhook_secret` | `WHATSAPP_WEBHOOK_SECRET` | — | Your App Secret, used to check webhook signatures |
| `webhook.deduplicate` | `WHATSAPP_WEBHOOK_DEDUPLICATE` | `true` | Skip events that were already processed |
| `webhook.deduplicate_for` | — | `1440` | Minutes to remember processed events |
| `webhook.cache_store` | `WHATSAPP_WEBHOOK_CACHE_STORE` | default store | Cache store used to remember events |

## Requests

| Option | Environment variable | Default | Description |
|---|---|---|---|
| `base_uri` | `WHATSAPP_BASE_URI` | `https://graph.facebook.com/v20.0` | Graph API URL, including the version |
| `timeout` | `WHATSAPP_TIMEOUT` | `30` | Request timeout in seconds |
| `retry.times` | `WHATSAPP_RETRY_TIMES` | `3` | Attempts per request for temporary failures (`1` disables retries) |
| `retry.sleep` | `WHATSAPP_RETRY_SLEEP` | `200` | Milliseconds before the first retry, doubled each time |
| `rate_limit` | `WHATSAPP_RATE_LIMIT` | `30` | Messages per minute, per phone number |
| `default_headers` | — | JSON headers | Headers added to every request |

## Messages

| Option | Environment variable | Default | Description |
|---|---|---|---|
| `default_language` | `WHATSAPP_DEFAULT_LANGUAGE` | `en_US` | Template language when none is given |
| `media_storage` | — | `storage/app/whatsapp-media` | Where downloaded media is saved |

## Message log

| Option | Environment variable | Default | Description |
|---|---|---|---|
| `message_log.enabled` | `WHATSAPP_MESSAGE_LOG` | `false` | Store sent and received messages. See [Message log](./message-log). |
| `message_log.connection` | `WHATSAPP_MESSAGE_LOG_CONNECTION` | default connection | Database connection for the log |

## Debugging

| Option | Environment variable | Default | Description |
|---|---|---|---|
| `debug` | `WHATSAPP_DEBUG` | `false` | Log full webhook payloads, which contain customers' messages |
