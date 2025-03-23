<?php
/**
 * Currency Configuration
 * 
 * This file contains configuration for the supported cryptocurrencies in the system
 */

return [
    // List of supported cryptocurrencies
    'supported_currencies' => [
        'BTC' => [
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'logo' => 'assets/img/currencies/bitcoin.png',
            'min_deposit' => 0.0001,
            'min_withdrawal' => 0.001,
            'withdrawal_fee' => 0.0005,
            'decimal_places' => 8,
            'api_enabled' => true
        ],
        'ETH' => [
            'name' => 'Ethereum',
            'symbol' => 'ETH',
            'logo' => 'assets/img/currencies/ethereum.png',
            'min_deposit' => 0.01,
            'min_withdrawal' => 0.05,
            'withdrawal_fee' => 0.01,
            'decimal_places' => 8,
            'api_enabled' => true
        ],
        'LTC' => [
            'name' => 'Litecoin',
            'symbol' => 'LTC',
            'logo' => 'assets/img/currencies/litecoin.png',
            'min_deposit' => 0.1,
            'min_withdrawal' => 0.1,
            'withdrawal_fee' => 0.01,
            'decimal_places' => 8,
            'api_enabled' => true
        ],
        'USDT' => [
            'name' => 'Tether USD',
            'symbol' => 'USDT',
            'logo' => 'assets/img/currencies/usdt.png',
            'min_deposit' => 10,
            'min_withdrawal' => 20,
            'withdrawal_fee' => 5,
            'decimal_places' => 2,
            'networks' => ['ERC20', 'TRC20'],
            'api_enabled' => true
        ],
        'TRX' => [
            'name' => 'TRON',
            'symbol' => 'TRX',
            'logo' => 'assets/img/currencies/tron.png',
            'min_deposit' => 100,
            'min_withdrawal' => 200,
            'withdrawal_fee' => 10,
            'decimal_places' => 6,
            'api_enabled' => true
        ],
        'DOGE' => [
            'name' => 'Dogecoin',
            'symbol' => 'DOGE',
            'logo' => 'assets/img/currencies/dogecoin.png',
            'min_deposit' => 10,
            'min_withdrawal' => 50,
            'withdrawal_fee' => 2,
            'decimal_places' => 8,
            'api_enabled' => true
        ]
    ],
    
    // Default currency for the system
    'default_currency' => 'BTC',
    
    // Currency display settings
    'display' => [
        'show_price_in_usd' => true,
        'usd_symbol' => '$',
        'price_api_url' => 'https://api.coingecko.com/api/v3/simple/price',
        'price_update_interval' => 300 // in seconds
    ]
]; 