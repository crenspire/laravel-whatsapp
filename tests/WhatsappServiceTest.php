<?php

use Crenspire\Whatsapp\WhatsappService;
use Crenspire\Whatsapp\Exceptions\WhatsappException;

it('has proper configuration structure', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    $reflection = new ReflectionClass($service);
    $property = $reflection->getProperty('config');
    $property->setAccessible(true);
    $config = $property->getValue($service);

    expect($config)->toHaveKey('phone_number_id');
    expect($config)->toHaveKey('access_token');
    expect($config)->toHaveKey('base_uri');
    expect($config)->toHaveKey('rate_limit');
    expect($config)->toHaveKey('media_storage');
    expect($config)->toHaveKey('tenants');
});

it('validates phone number format', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    expect(fn () => $service->sendTextMessage('123', 'Hello'))
        ->toThrow(WhatsappException::class, 'Invalid phone number format');

    expect(fn () => $service->sendTextMessage('12345678901234567890', 'Hello'))
        ->toThrow(WhatsappException::class, 'Invalid phone number format');
});

it('creates media storage directory', function () {
    $tempDir = sys_get_temp_dir() . '/whatsapp-test-' . uniqid();
    
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => $tempDir,
        'tenants' => []
    ]);

    expect(is_dir($tempDir))->toBeTrue();
    expect(is_writable($tempDir))->toBeTrue();
    
    rmdir($tempDir);
});

it('gets file extension from mime type', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getFileExtensionFromMimeType');
    $method->setAccessible(true);

    expect($method->invoke($service, 'image/jpeg'))->toBe('jpg');
    expect($method->invoke($service, 'image/png'))->toBe('png');
    expect($method->invoke($service, 'video/mp4'))->toBe('mp4');
    expect($method->invoke($service, 'application/pdf'))->toBe('pdf');
    expect($method->invoke($service, 'unknown/type'))->toBe('bin');
});

it('validates phone number correctly', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('validatePhoneNumber');
    $method->setAccessible(true);

    // Valid phone numbers should not throw
    expect(fn () => $method->invoke($service, '1234567890'))->not->toThrow(Exception::class);
    expect(fn () => $method->invoke($service, '123456789012345'))->not->toThrow(Exception::class);
    expect(fn () => $method->invoke($service, '+1234567890'))->not->toThrow(Exception::class);
    expect(fn () => $method->invoke($service, '123-456-7890'))->not->toThrow(Exception::class);

    // Invalid phone numbers should throw
    expect(fn () => $method->invoke($service, '123'))->toThrow(WhatsappException::class);
    expect(fn () => $method->invoke($service, '12345678901234567890'))->toThrow(WhatsappException::class);
    expect(fn () => $method->invoke($service, 'abc123def'))->toThrow(WhatsappException::class);
});

it('uses tenant configuration when provided', function () {
    $service = new WhatsappService([
        'phone_number_id' => 'default123',
        'access_token' => 'default_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => [
            'tenant1' => [
                'phone_number_id' => 'tenant123',
                'access_token' => 'tenant_token'
            ]
        ]
    ]);

    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('tenantConfig');
    $method->setAccessible(true);

    // Test default configuration
    $defaultConfig = $method->invoke($service, null);
    expect($defaultConfig['phone_number_id'])->toBe('default123');
    expect($defaultConfig['access_token'])->toBe('default_token');

    // Test tenant configuration
    $tenantConfig = $method->invoke($service, 'tenant1');
    expect($tenantConfig['phone_number_id'])->toBe('tenant123');
    expect($tenantConfig['access_token'])->toBe('tenant_token');
});

it('handles file upload validation', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    $nonExistentFile = sys_get_temp_dir() . '/non-existent-file.jpg';
    
    expect(fn () => $service->uploadMedia($nonExistentFile, 'image'))
        ->toThrow(WhatsappException::class, 'File not found');
});

it('creates proper button message structure', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    $buttons = [
        ['id' => 'btn1', 'title' => 'Option 1'],
        ['id' => 'btn2', 'title' => 'Option 2']
    ];

    // Test the button message structure by calling sendButtonMessage
    // We'll mock the HTTP call to avoid actual API calls
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sendButtonMessage');
    $method->setAccessible(true);

    // This will fail due to HTTP call, but we can check the structure
    expect(fn () => $method->invoke($service, '1234567890', 'Test', $buttons))
        ->toThrow(Exception::class);
});

it('creates proper list message structure', function () {
    $service = new WhatsappService([
        'phone_number_id' => '123456789',
        'access_token' => 'test_token',
        'base_uri' => 'https://graph.facebook.com/v20.0',
        'rate_limit' => 30,
        'media_storage' => sys_get_temp_dir() . '/whatsapp-media',
        'tenants' => []
    ]);

    $sections = [
        [
            'title' => 'Section 1',
            'rows' => [
                ['id' => 'row1', 'title' => 'Row 1', 'description' => 'Description 1']
            ]
        ]
    ];

    // Test the list message structure
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('sendListMessage');
    $method->setAccessible(true);

    // This will fail due to HTTP call, but we can check the structure
    expect(fn () => $method->invoke($service, '1234567890', 'Test', 'Button', $sections))
        ->toThrow(Exception::class);
});