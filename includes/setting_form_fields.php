<?php

// includes/setting_form_fields.php
if (!defined('ABSPATH')) {
    die;
}

$this->form_fields = apply_filters( 'wc_offline_form_fields', [
    'enabled' => [
        'title' => __('Enable/Disable', 'wc-nowpayments-gateway'),
        'type' => 'checkbox',
        'label' => __('Enable nowpayments.io', 'wc-nowpayments-gateway'),
        'default' => 'yes',
    ],
    'title' => [
        'title' => __('Title', 'wc-nowpayments-gateway'),
        'type' => 'text',
        'description' => __('This controls the title which the user sees during checkout.', 'wc-nowpayments-gateway'),
        'default' => __('NOWPayments', 'wc-nowpayments-gateway'),
        'desc_tip' => true,
    ],
    'description' => [
        'title' => __('Description', 'wc-nowpayments-gateway'),
        'type' => 'textarea',
        'description' => __('This controls the description which the user sees during checkout.', 'wc-nowpayments-gateway'),
        'default' => __('Expand your payment options with NOWPayments! BTC, ETH, LTC and many more: pay with anything you like!', 'wc-nowpayments-gateway'),
    ],
    'instructions' => [
        'title' => __( 'Instructions', 'wc-gateway-offline' ),
        'type' => 'textarea',
        'description' => __( '', 'wc-gateway-offline' ),
        'default' => '',
        'desc_tip' => true,
    ],
    'ipn_secret' => [
        'title' => __('IPN Secret', 'wc-nowpayments-gateway'),
        'type' => 'text',
        'description' => __('Please enter your Nowpayments.io IPN Secret.', 'wc-nowpayments-gateway'),
        'default' => '',
    ],
    'api_key' => [
        'title' => __('Api Key', 'wc-nowpayments-gateway'),
        'type' => 'text',
        'description' => __('Please enter your nowpayments.io Api Key.', 'wc-nowpayments-gateway'),
        'default' => '',
    ],
    'simple_total' => [
        'title' => __('Compatibility Mode', 'wc-nowpayments-gateway'),
        'type' => 'checkbox',
        'label' => __("This may be needed for compatibility with certain addons if the order total isn't correct.", 'wc-nowpayments-gateway'),
        'default' => '',
    ],
    'invoice_prefix' => [
        'title' => __('Invoice Prefix', 'wc-nowpayments-gateway'),
        'type' => 'text',
        'description' => __('Please enter a prefix for your invoice numbers. If you use your nowpayments.io account for multiple stores ensure this prefix is unique.', 'wc-nowpayments-gateway'),
        'default' => 'WC-',
        'desc_tip' => true,
    ],
    'debug_email' => [
        'title' => __('Debug Email', 'wc-nowpayments-gateway'),
        'type' => 'email',
        'default' => '',
        'description' => __('(this will Slow down website performance) Send copies of invalid IPNs to this email address.', 'wc-nowpayments-gateway'),
    ],
    'debug_post_url' => [
        'title' => __('Debug post url', 'wc-nowpayments-gateway'),
        'type' => 'text',
        'default' => '',
        'description' => __('(this will Slow down website performance) Send post data to debug', 'wc-nowpayments-gateway'),
    ]
] );
