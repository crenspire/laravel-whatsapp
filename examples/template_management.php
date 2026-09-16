<?php

/**
 * WhatsApp Template Management Examples
 *
 * This file demonstrates how to use the template management features
 * of the WhatsApp service package.
 */

use Crenspire\Whatsapp\Facades\Whatsapp;

// Example 1: Create a simple text template
$components = [
    [
        'type' => 'BODY',
        'text' => 'Hello {{1}}, your order {{2}} has been confirmed.'
    ]
];

$result = Whatsapp::createTemplate(
    'order_confirmation',
    'en_US',
    'UTILITY',
    $components
);

// Example 2: Create a template with header, body, and footer
$components = [
    [
        'type' => 'HEADER',
        'format' => 'TEXT',
        'text' => 'Order Confirmation'
    ],
    [
        'type' => 'BODY',
        'text' => 'Hi {{1}}, your order {{2}} has been confirmed and will be delivered on {{3}}.'
    ],
    [
        'type' => 'FOOTER',
        'text' => 'Thank you for shopping with us!'
    ]
];

$result = Whatsapp::createTemplate(
    'order_confirmation_detailed',
    'en_US',
    'UTILITY',
    $components
);

// Example 3: Create a marketing template
$components = [
    [
        'type' => 'HEADER',
        'format' => 'IMAGE',
        'example' => [
            'header_handle' => ['image_handle']
        ]
    ],
    [
        'type' => 'BODY',
        'text' => 'Check out our latest collection! Get {{1}}% off on all items.'
    ],
    [
        'type' => 'FOOTER',
        'text' => 'Limited time offer'
    ],
    [
        'type' => 'BUTTONS',
        'buttons' => [
            [
                'type' => 'URL',
                'text' => 'Shop Now',
                'url' => 'https://example.com/shop'
            ],
            [
                'type' => 'PHONE_NUMBER',
                'text' => 'Call Us',
                'phone_number' => '+1234567890'
            ]
        ]
    ]
];

$result = Whatsapp::createTemplate(
    'marketing_promotion',
    'en_US',
    'MARKETING',
    $components
);

// Example 4: Create a template with authentication
$components = [
    [
        'type' => 'BODY',
        'text' => 'Your verification code is {{1}}. This code will expire in {{2}} minutes.'
    ]
];

$result = Whatsapp::createTemplate(
    'verification_code',
    'en_US',
    'AUTHENTICATION',
    $components
);

// Example 5: Update an existing template
$updatedComponents = [
    [
        'type' => 'HEADER',
        'format' => 'TEXT',
        'text' => 'Order Update'
    ],
    [
        'type' => 'BODY',
        'text' => 'Hi {{1}}, your order {{2}} has been shipped and is on its way!'
    ],
    [
        'type' => 'FOOTER',
        'text' => 'Track your order for more details'
    ]
];

$result = Whatsapp::updateTemplate(
    'order_confirmation',
    'en_US',
    'UTILITY',
    $updatedComponents
);

// Example 6: Get all templates
$allTemplates = Whatsapp::getTemplates();

// Example 7: Get templates by status
$approvedTemplates = Whatsapp::getTemplatesByStatus('APPROVED');
$pendingTemplates = Whatsapp::getTemplatesByStatus('PENDING');
$rejectedTemplates = Whatsapp::getTemplatesByStatus('REJECTED');

// Example 8: Get templates by category
$utilityTemplates = Whatsapp::getTemplatesByCategory('UTILITY');
$marketingTemplates = Whatsapp::getTemplatesByCategory('MARKETING');
$utilityTemplates = Whatsapp::getTemplatesByCategory('UTILITY');

// Example 9: Get templates by language
$englishTemplates = Whatsapp::getTemplatesByLanguage('en_US');
$spanishTemplates = Whatsapp::getTemplatesByLanguage('es_ES');

// Example 10: Get a specific template
$template = Whatsapp::getTemplate('order_confirmation');

// Note: there is no publish step. Templates are submitted for review
// automatically when created or edited; poll the status to see the outcome.

// Example 11: Check template status
$status = Whatsapp::getTemplateStatus('order_confirmation');

// Example 12: Check if template is approved
$isApproved = Whatsapp::isTemplateApproved('order_confirmation');

// Example 13: Check if template is pending
$isPending = Whatsapp::isTemplatePending('order_confirmation');

// Example 14: Delete a template
$deleted = Whatsapp::deleteTemplate('old_template');

// Example 15: Multi-tenant template management
$result = Whatsapp::createTemplate(
    'tenant_specific_template',
    'en_US',
    'UTILITY',
    $components,
    'tenant1' // Use tenant1 configuration
);

// Example 16: Create template with different languages
$languages = ['en_US', 'es_ES', 'fr_FR', 'de_DE'];

foreach ($languages as $language) {
    $result = Whatsapp::createTemplate(
        "welcome_{$language}",
        $language,
        'UTILITY',
        [
            [
                'type' => 'BODY',
                'text' => 'Welcome to our service!'
            ]
        ]
    );
}

// Example 17: Create template with media header
$components = [
    [
        'type' => 'HEADER',
        'format' => 'VIDEO',
        'example' => [
            'header_handle' => ['video_handle']
        ]
    ],
    [
        'type' => 'BODY',
        'text' => 'Watch our latest product demo!'
    ]
];

$result = Whatsapp::createTemplate(
    'video_demo',
    'en_US',
    'MARKETING',
    $components
);

// Example 18: Create template with document header
$components = [
    [
        'type' => 'HEADER',
        'format' => 'DOCUMENT',
        'example' => [
            'header_handle' => ['document_handle']
        ]
    ],
    [
        'type' => 'BODY',
        'text' => 'Please find attached your invoice for order {{1}}.'
    ]
];

$result = Whatsapp::createTemplate(
    'invoice_template',
    'en_US',
    'UTILITY',
    $components
);

// Example 19: Create template with multiple buttons
$components = [
    [
        'type' => 'BODY',
        'text' => 'How would you like to proceed?'
    ],
    [
        'type' => 'BUTTONS',
        'buttons' => [
            [
                'type' => 'QUICK_REPLY',
                'text' => 'Yes'
            ],
            [
                'type' => 'QUICK_REPLY',
                'text' => 'No'
            ],
            [
                'type' => 'QUICK_REPLY',
                'text' => 'Maybe Later'
            ]
        ]
    ]
];

$result = Whatsapp::createTemplate(
    'quick_reply_template',
    'en_US',
    'UTILITY',
    $components
);

// Example 20: Filter templates with multiple criteria
$filters = [
    'status' => 'APPROVED',
    'category' => 'UTILITY',
    'language' => 'en_US'
];

$filteredTemplates = Whatsapp::getTemplates($filters);

// Example 21: Error handling
try {
    $result = Whatsapp::createTemplate(
        'invalid_template',
        'en_US',
        'INVALID_CATEGORY', // This will throw an exception
        $components
    );
} catch (Exception $e) {
    echo "Error creating template: " . $e->getMessage();
}

// Example 22: Check template before using
if (Whatsapp::isTemplateApproved('order_confirmation')) {
    // Template is approved, safe to use
    $result = Whatsapp::sendTemplateMessage(
        '+1234567890',
        'order_confirmation',
        ['John', 'ORD-12345']
    );
} else {
    echo "Template is not approved yet";
}

// Example 23: Bulk template operations
$templatesToCreate = [
    [
        'name' => 'welcome_new_user',
        'language' => 'en_US',
        'category' => 'UTILITY',
        'components' => [
            ['type' => 'BODY', 'text' => 'Welcome {{1}}! Thanks for joining us.']
        ]
    ],
    [
        'name' => 'password_reset',
        'language' => 'en_US',
        'category' => 'AUTHENTICATION',
        'components' => [
            ['type' => 'BODY', 'text' => 'Your password reset code is {{1}}.']
        ]
    ],
    [
        'name' => 'order_shipped',
        'language' => 'en_US',
        'category' => 'UTILITY',
        'components' => [
            ['type' => 'BODY', 'text' => 'Your order {{1}} has been shipped!']
        ]
    ]
];

foreach ($templatesToCreate as $template) {
    try {
        $result = Whatsapp::createTemplate(
            $template['name'],
            $template['language'],
            $template['category'],
            $template['components']
        );
        echo "Created template: {$template['name']}\n";
    } catch (Exception $e) {
        echo "Failed to create template {$template['name']}: " . $e->getMessage() . "\n";
    }
}
