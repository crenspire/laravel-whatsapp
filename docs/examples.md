# Examples

This document provides real-world examples of using the Laravel WhatsApp package.

## Basic Examples

### Sending a Welcome Message

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Http\Request;

class WelcomeController extends Controller
{
    public function sendWelcome(Request $request)
    {
        $phoneNumber = $request->input('phone');
        $name = $request->input('name');
        
        try {
            $response = Whatsapp::sendTextMessage(
                $phoneNumber, 
                "Welcome to our service, {$name}! We're excited to have you on board."
            );
            
            return response()->json([
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null
            ]);
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

### Sending Order Confirmation

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class OrderController extends Controller
{
    public function sendOrderConfirmation($order)
    {
        $message = "Order #{$order->id} confirmed!\n\n";
        $message .= "Items:\n";
        foreach ($order->items as $item) {
            $message .= "• {$item->name} x{$item->quantity} - $" . number_format($item->price, 2) . "\n";
        }
        $message .= "\nTotal: $" . number_format($order->total, 2);
        $message .= "\n\nThank you for your order!";
        
        try {
            Whatsapp::sendTextMessage($order->customer_phone, $message);
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            logger('Failed to send order confirmation', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

## Interactive Messages

### Customer Support Menu

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class SupportController extends Controller
{
    public function sendSupportMenu($phoneNumber)
    {
        $buttons = [
            ['id' => 'billing', 'title' => 'Billing Support'],
            ['id' => 'technical', 'title' => 'Technical Support'],
            ['id' => 'sales', 'title' => 'Sales Inquiry'],
            ['id' => 'general', 'title' => 'General Question']
        ];
        
        try {
            Whatsapp::sendButtonMessage(
                $phoneNumber,
                'How can we help you today? Please select an option:',
                $buttons,
                'Customer Support',
                'We\'re here to help!'
            );
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            logger('Failed to send support menu', ['error' => $e->getMessage()]);
        }
    }
}
```

### Product Catalog

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class CatalogController extends Controller
{
    public function sendProductCatalog($phoneNumber)
    {
        $sections = [
            [
                'title' => 'Electronics',
                'rows' => [
                    [
                        'id' => 'laptop',
                        'title' => 'Laptops',
                        'description' => 'High-performance laptops'
                    ],
                    [
                        'id' => 'phone',
                        'title' => 'Smartphones',
                        'description' => 'Latest smartphone models'
                    ]
                ]
            ],
            [
                'title' => 'Accessories',
                'rows' => [
                    [
                        'id' => 'headphones',
                        'title' => 'Headphones',
                        'description' => 'Wireless and wired options'
                    ],
                    [
                        'id' => 'charger',
                        'title' => 'Chargers',
                        'description' => 'Fast charging solutions'
                    ]
                ]
            ]
        ];
        
        try {
            Whatsapp::sendListMessage(
                $phoneNumber,
                'Browse our product catalog:',
                'View Products',
                $sections,
                'Product Catalog',
                'Tap to explore our products'
            );
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            logger('Failed to send product catalog', ['error' => $e->getMessage()]);
        }
    }
}
```

## Media Messages

### Sending Product Images

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function sendProductImage(Request $request)
    {
        $phoneNumber = $request->input('phone');
        $productId = $request->input('product_id');
        
        // Upload the product image
        $imagePath = public_path("images/products/{$productId}.jpg");
        
        if (!file_exists($imagePath)) {
            return response()->json(['error' => 'Product image not found'], 404);
        }
        
        try {
            // Upload media to WhatsApp
            $uploadResponse = Whatsapp::uploadMedia($imagePath, 'image');
            $mediaId = $uploadResponse['id'];
            
            // Send the image with caption
            $product = Product::find($productId);
            $caption = "Check out {$product->name}!\n\n";
            $caption .= "Price: $" . number_format($product->price, 2) . "\n";
            $caption .= "Description: {$product->description}";
            
            $response = Whatsapp::sendMediaMessage($phoneNumber, $mediaId, 'image', $caption);
            
            return response()->json([
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null
            ]);
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

### Sending Documents

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class DocumentController extends Controller
{
    public function sendInvoice($order)
    {
        // Generate PDF invoice
        $pdfPath = $this->generateInvoicePdf($order);
        
        try {
            // Upload PDF to WhatsApp
            $uploadResponse = Whatsapp::uploadMedia($pdfPath, 'document');
            $mediaId = $uploadResponse['id'];
            
            // Send the invoice
            $caption = "Invoice #{$order->id}\n";
            $caption .= "Amount: $" . number_format($order->total, 2) . "\n";
            $caption .= "Due Date: " . $order->due_date->format('M d, Y');
            
            Whatsapp::sendMediaMessage($order->customer_phone, $mediaId, 'document', $caption);
            
            // Clean up temporary file
            unlink($pdfPath);
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            logger('Failed to send invoice', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

## Template Messages

### Appointment Reminder

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class AppointmentController extends Controller
{
    public function sendReminder($appointment)
    {
        try {
            Whatsapp::sendTemplateMessage(
                $appointment->customer_phone,
                'appointment_reminder',
                [
                    $appointment->customer_name,
                    $appointment->date->format('M d, Y'),
                    $appointment->time->format('g:i A'),
                    $appointment->service_name
                ],
                'en_US'
            );
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            logger('Failed to send appointment reminder', [
                'appointment_id' => $appointment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### Password Reset

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class AuthController extends Controller
{
    public function sendPasswordReset($user, $resetToken)
    {
        try {
            Whatsapp::sendTemplateMessage(
                $user->phone,
                'password_reset',
                [
                    $user->name,
                    $resetToken,
                    config('app.url') . "/reset-password?token={$resetToken}"
                ],
                'en_US'
            );
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            logger('Failed to send password reset', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

## Event Handling

### Message Status Tracking

```php
<?php

namespace App\Listeners;

use Crenspire\Whatsapp\Events\MessageSent;
use Crenspire\Whatsapp\Events\MessageDelivered;
use Crenspire\Whatsapp\Events\MessageRead;
use Crenspire\Whatsapp\Events\MessageFailed;
use Illuminate\Contracts\Queue\ShouldQueue;

class MessageStatusTracker implements ShouldQueue
{
    public function handleMessageSent(MessageSent $event)
    {
        // Log message sent
        logger('Message sent', [
            'message_id' => $event->messageId,
            'recipient' => $event->recipient
        ]);
        
        // Update database
        Message::where('whatsapp_id', $event->messageId)
               ->update(['status' => 'sent']);
    }
    
    public function handleMessageDelivered(MessageDelivered $event)
    {
        // Update delivery status
        Message::where('whatsapp_id', $event->messageId)
               ->update(['status' => 'delivered']);
    }
    
    public function handleMessageRead(MessageRead $event)
    {
        // Update read status
        Message::where('whatsapp_id', $event->messageId)
               ->update([
                   'status' => 'read',
                   'read_at' => $event->timestamp
               ]);
    }
    
    public function handleMessageFailed(MessageFailed $event)
    {
        // Log failure and update status
        logger('Message failed', [
            'recipient' => $event->recipient,
            'error' => $event->error
        ]);
        
        Message::where('recipient', $event->recipient)
               ->where('status', 'pending')
               ->update(['status' => 'failed']);
    }
}
```

### Incoming Message Handler

```php
<?php

namespace App\Listeners;

use Crenspire\Whatsapp\Events\MessageReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

class IncomingMessageHandler implements ShouldQueue
{
    public function handle(MessageReceived $event)
    {
        $message = $event->message;
        $from = $event->from;
        $type = $message['type'] ?? 'unknown';
        
        // Handle different message types
        switch ($type) {
            case 'text':
                $this->handleTextMessage($from, $message['text']['body'] ?? '');
                break;
            case 'image':
                $this->handleImageMessage($from, $message['image']);
                break;
            case 'document':
                $this->handleDocumentMessage($from, $message['document']);
                break;
            case 'interactive':
                $this->handleInteractiveMessage($from, $message['interactive']);
                break;
        }
    }
    
    private function handleTextMessage($from, $text)
    {
        // Process text message
        $user = User::where('phone', $from)->first();
        
        if ($user) {
            // Save message to database
            Message::create([
                'user_id' => $user->id,
                'from' => $from,
                'type' => 'text',
                'content' => $text,
                'received_at' => now()
            ]);
            
            // Process commands
            if (str_starts_with($text, '/')) {
                $this->processCommand($user, $text);
            }
        }
    }
    
    private function handleInteractiveMessage($from, $interactive)
    {
        $type = $interactive['type'] ?? 'unknown';
        
        if ($type === 'button_reply') {
            $buttonId = $interactive['button_reply']['id'] ?? '';
            $this->handleButtonReply($from, $buttonId);
        } elseif ($type === 'list_reply') {
            $rowId = $interactive['list_reply']['id'] ?? '';
            $this->handleListReply($from, $rowId);
        }
    }
    
    private function processCommand($user, $command)
    {
        // Process slash commands
        $parts = explode(' ', $command);
        $cmd = $parts[0];
        
        switch ($cmd) {
            case '/help':
                $this->sendHelpMessage($user->phone);
                break;
            case '/status':
                $this->sendStatusMessage($user->phone);
                break;
            case '/menu':
                $this->sendMainMenu($user->phone);
                break;
        }
    }
}
```

## Multi-tenant Examples

### Tenant-specific Messaging

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;

class MultiTenantController extends Controller
{
    public function sendMessage($tenantId, $phoneNumber, $message)
    {
        try {
            // Use tenant-specific configuration
            $response = Whatsapp::sendTextMessage($phoneNumber, $message, $tenantId);
            
            return response()->json([
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null
            ]);
        } catch (\Crenspire\Whatsapp\Exceptions\WhatsappException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

### Tenant Configuration

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\WhatsappService;

class TenantController extends Controller
{
    public function configureTenant($tenantId, $phoneNumberId, $accessToken)
    {
        // Update tenant configuration
        $config = config('whatsapp');
        $config['tenants'][$tenantId] = [
            'phone_number_id' => $phoneNumberId,
            'access_token' => $accessToken
        ];
        
        // Save configuration
        config(['whatsapp' => $config]);
        
        // Test the configuration
        try {
            $service = app(WhatsappService::class);
            $service->sendTextMessage('1234567890', 'Test message', $tenantId);
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

## Error Handling Examples

### Comprehensive Error Handling

```php
<?php

namespace App\Http\Controllers;

use Crenspire\Whatsapp\Facades\Whatsapp;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        $phoneNumber = $request->input('phone');
        $message = $request->input('message');
        
        try {
            $response = Whatsapp::sendTextMessage($phoneNumber, $message);
            
            return response()->json([
                'success' => true,
                'message_id' => $response['messages'][0]['id'] ?? null
            ]);
        } catch (WhatsappException $e) {
            // Handle different error types
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            
            if ($errorCode === 429) {
                // Rate limit exceeded
                return response()->json([
                    'success' => false,
                    'error' => 'Rate limit exceeded. Please try again later.',
                    'retry_after' => 60
                ], 429);
            } elseif ($errorCode === 400) {
                // Bad request - invalid phone number or message
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid phone number or message format.'
                ], 400);
            } else {
                // Other errors
                logger('WhatsApp API error', [
                    'phone' => $phoneNumber,
                    'error' => $errorMessage,
                    'code' => $errorCode
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send message. Please try again.'
                ], 500);
            }
        }
    }
}
```

## Testing Examples

### Unit Test Example

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Crenspire\Whatsapp\Facades\Whatsapp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Event;

class WhatsappTest extends TestCase
{
    public function test_sends_text_message()
    {
        Http::fake([
            'graph.facebook.com/v20.0/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => '1234567890', 'wa_id' => '1234567890']],
                'messages' => [['id' => 'wamid.test123']]
            ], 200)
        ]);

        Event::fake();

        $response = Whatsapp::sendTextMessage('1234567890', 'Hello World!');

        $this->assertArrayHasKey('messages', $response);
        $this->assertEquals('wamid.test123', $response['messages'][0]['id']);
        
        Event::assertDispatched(\Crenspire\Whatsapp\Events\MessageSent::class);
    }
}
```

These examples demonstrate real-world usage patterns and best practices for implementing WhatsApp messaging in your Laravel application.
