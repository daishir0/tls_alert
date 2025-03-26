<?php
/**
 * Sample Configuration file for TLS Certificate Checker
 * 
 * Copy this file to config.php and update with your actual values
 */

// デバッグフラグ - 1の場合は常にメール送信
$debug_flg = 0;

// Gmail送信の設定
define('API_KEY', 'your-api-key-here');  // APIキー
define('GMAIL_USER', 'your-gmail-account@gmail.com');  // Gmailアカウント
define('GMAIL_APP_PASSWORD', 'your-app-password-here');  // Gmailアプリパスワード
define('GMAIL_FROM', 'your-gmail-account@gmail.com');  // 送信元メールアドレス

// Email address for alerts
$alertEmail = 'your-alert-email@example.com';

// List of sites to check
$sites = [
    [
        'name' => 'Site1',
        'https_url' => 'https://example1.com/',
        'certificate_commonname' => '*.example1.com'
    ],
    [
        'name' => 'Site2',
        'https_url' => 'https://example2.com/',
        'certificate_commonname' => '*.example2.com'
    ],
    [
        'name' => 'Site3',
        'https_url' => 'https://example3.com/',
        'certificate_commonname' => 'example3.com'
    ]
];