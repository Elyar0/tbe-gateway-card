<?php

return [
    'labels' => [
        'billing' => 'Billing',
        'gateways' => 'Gateways',
        'to_card' => 'To Card',
        'status' => 'To Card Status',
        'card_number' => 'Card Number',
        'card_name' => 'Card Name',
        'transactions_chat_id' => 'Transactions Chat ID',
        'sms_bank' => 'SMS Auto-Verify Bank',
        'sms_secret' => 'SMS Webhook Secret',
        'unique_amount' => 'Unique Payment Amounts',
    ],

    'options' => [
        'unique_amount' => [
            'hard' => 'Hard - always add a unique amount',
            'soft' => 'Soft - only when another payment needs it',
            'disabled' => 'Disabled - never add a unique amount',
        ],
    ],

    'descriptions' => [
        'billing' => 'Payment and invoicing settings for the bot.',
        'gateways' => 'Enable and configure the payment methods customers can use.',
        'to_card' => 'Accept manual card-to-card transfer payments.',
        'status' => 'Turn card-to-card payment on or off.',
        'card_number' => 'The card number customers should transfer payment to.',
        'card_name' => "The cardholder's name shown to customers.",
        'transactions_chat_id' => 'Chat ID where card payment receipts are sent for review.',
        'sms_bank' => 'Which bank\'s deposit SMS format to recognize for automatic payment verification.',
        'sms_secret' => "Shared secret used to verify SMS forwarded from your phone. Must match the secret configured in the forwarder app.\n\nForwarder app URL: :url",
        'unique_amount' => 'Add a small extra amount to a to-card invoice so payments can be told apart automatically. Hard always adds it, Soft only when another pending payment already needs it, Disabled never adds it. Any extra amount is credited to the member\'s wallet when that feature is available and enabled.',
    ],
];
