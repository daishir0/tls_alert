<?php
/**
 * TLS Certificate Expiration Checker
 * 
 * This script checks the TLS certificates of specified websites,
 * displays their expiration dates, and sends alerts for certificates
 * expiring within a week.
 */

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Load configuration
$configFile = __DIR__ . '/config.php';
$configSampleFile = __DIR__ . '/config.sample.php';

if (!file_exists($configFile)) {
    die('Configuration file not found. Please copy config.sample.php to config.php and update with your settings.');
}

require $configFile;

// PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Composerのオートロードを読み込み
$autoloadPath = __DIR__ . '/vendor/autoload.php';
$phpMailerAvailable = file_exists($autoloadPath);

if ($phpMailerAvailable) {
    require $autoloadPath;
}

// ログ記録用の関数
function logError($message) {
    $logFile = __DIR__ . '/tls_check.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Function to check certificate expiration
function checkCertificate($url) {
    $origUrl = $url;
    
    // Parse URL to get host
    $parsedUrl = parse_url($url);
    if (!isset($parsedUrl['host'])) {
        return [
            'error' => 'Invalid URL format',
            'expiry_date' => null,
            'days_remaining' => null
        ];
    }
    
    $host = $parsedUrl['host'];
    $port = isset($parsedUrl['port']) ? $parsedUrl['port'] : 443;
    
    // Create a stream context with SSL options
    $context = stream_context_create([
        'ssl' => [
            'capture_peer_cert' => true,
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    // Try to establish a connection
    $errno = 0;
    $errstr = '';
    
    try {
        // Connect to the server with a timeout
        $socket = @stream_socket_client(
            "ssl://$host:$port",
            $errno,
            $errstr,
            10, // Reduced timeout to 10 seconds
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            logError("Connection failed to $url: $errno $errstr");
            return [
                'error' => "Connection failed: $errno $errstr",
                'expiry_date' => null,
                'days_remaining' => null
            ];
        }
        
        // Get certificate information
        $params = stream_context_get_params($socket);
        if (!isset($params['options']['ssl']['peer_certificate'])) {
            fclose($socket);
            logError("Could not get certificate for $url");
            return [
                'error' => 'Could not get certificate',
                'expiry_date' => null,
                'days_remaining' => null
            ];
        }
        
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        fclose($socket);
        
        if (!$cert) {
            logError("Failed to parse certificate for $url");
            return [
                'error' => 'Failed to parse certificate',
                'expiry_date' => null,
                'days_remaining' => null
            ];
        }
        
        // Get expiration timestamp and calculate days remaining
        $expiryTimestamp = $cert['validTo_time_t'];
        $expiryDate = date('Y-m-d H:i:s', $expiryTimestamp);
        $currentTimestamp = time();
        $daysRemaining = floor(($expiryTimestamp - $currentTimestamp) / (60 * 60 * 24));
        
        logError("Certificate for $url expires on $expiryDate ($daysRemaining days remaining)");
        
        return [
            'error' => null,
            'expiry_date' => $expiryDate,
            'days_remaining' => $daysRemaining,
            'subject' => isset($cert['subject']) ? $cert['subject'] : null,
            'issuer' => isset($cert['issuer']) ? $cert['issuer'] : null
        ];
    } catch (Exception $e) {
        logError("Exception checking $url: " . $e->getMessage());
        return [
            'error' => 'Exception: ' . $e->getMessage(),
            'expiry_date' => null,
            'days_remaining' => null
        ];
    }
}

// Function to send alert email using PHPMailer
function sendAlertEmail($site, $expiryInfo, $toEmail) {
    global $phpMailerAvailable;
    
    $subject = "ALERT: TLS Certificate Expiring Soon - {$site['name']}";
    
    $message = "
    <html>
    <head>
        <title>TLS Certificate Expiration Alert</title>
    </head>
    <body>
        <h2>TLS Certificate Expiration Alert</h2>
        <p>The following site has a TLS certificate that will expire soon:</p>
        <table border='1' cellpadding='5'>
            <tr>
                <th>Site Name</th>
                <td>{$site['name']}</td>
            </tr>
            <tr>
                <th>URL</th>
                <td>{$site['https_url']}</td>
            </tr>
            <tr>
                <th>Expected Common Name</th>
                <td>{$site['certificate_commonname']}</td>
            </tr>
            <tr>
                <th>Expiry Date</th>
                <td>" . ($expiryInfo['expiry_date'] ?? 'N/A') . "</td>
            </tr>
            <tr>
                <th>Days Remaining</th>
                <td>" . ($expiryInfo['days_remaining'] !== null ? $expiryInfo['days_remaining'] . ' 日' : 'N/A') . "</td>
            </tr>
        </table>
        <p>Please take action to renew this certificate as soon as possible.</p>
    </body>
    </html>
    ";
    
    // Use PHPMailer if available, otherwise fall back to mail()
    if ($phpMailerAvailable) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 587;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPAuth = true;
            
            // Gmail認証情報の設定
            $mail->Username = GMAIL_USER;
            $mail->Password = GMAIL_APP_PASSWORD;
            
            // メール内容の設定
            $mail->setFrom(GMAIL_FROM);
            $mail->addAddress($toEmail);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // プレーンテキスト版も設定
            $plainText = strip_tags(str_replace(['<tr>', '</tr>', '</td><td>', '</th><td>'], ["\n", '', ': ', ': '], $message));
            $mail->AltBody = $plainText;
            
            // メール送信
            $mail->send();
            logError("Alert email sent successfully to $toEmail for {$site['name']}");
            return true;
        } catch (Exception $e) {
            logError("Failed to send alert email: " . $e->getMessage());
            return false;
        }
    } else {
        // Fall back to mail() function if PHPMailer is not available
        logError("PHPMailer not available, falling back to mail() function");
        
        // Headers for HTML email
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: TLS Certificate Monitor <noreply@" . $_SERVER['SERVER_NAME'] . ">\r\n";
        
        // Send email
        $result = mail($toEmail, $subject, $message, $headers);
        logError("Mail function result: " . ($result ? "success" : "failure"));
        return $result;
    }
}

// Check all sites and collect results
$results = [];
$hasAlerts = false;

// Add execution time information
$startTime = microtime(true);

foreach ($sites as $site) {
    // Add a status message
    echo "<!-- Checking {$site['name']} at {$site['https_url']} -->\n";
    flush();
    
    $certInfo = checkCertificate($site['https_url']);
    
    $result = [
        'site' => $site,
        'cert_info' => $certInfo,
        'is_alert' => false,
        'is_debug_mail' => false
    ];
    
    // Check if this is an alert (expiring within 7 days)
    if ($certInfo['error'] === null && $certInfo['days_remaining'] !== null && $certInfo['days_remaining'] < 7) {
        $result['is_alert'] = true;
        $hasAlerts = true;
        
        // Send alert email
        $emailSent = sendAlertEmail($site, $certInfo, $alertEmail);
        $result['email_sent'] = $emailSent;
    }
    // デバッグフラグが1の場合は常にメール送信
    elseif ($debug_flg == 1) {
        $result['is_debug_mail'] = true;
        
        // Send debug email
        $emailSent = sendAlertEmail($site, $certInfo, $alertEmail);
        $result['email_sent'] = $emailSent;
        
        logError("Debug mode: Sending test email for {$site['name']} regardless of expiration status");
    }
    
    $results[] = $result;
}

$endTime = microtime(true);
$executionTime = round($endTime - $startTime, 2);

// HTML output
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TLS証明書期限チェッカー</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .alert {
            background-color: #ffebee;
            color: #c62828;
            font-weight: bold;
        }
        .debug {
            background-color: #e3f2fd;
            color: #0d47a1;
            font-weight: bold;
        }
        .alert-banner {
            background-color: #c62828;
            color: white;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }
        .debug-banner {
            background-color: #0d47a1;
            color: white;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-weight: bold;
        }
        .error {
            color: #c62828;
        }
        .ok {
            color: #2e7d32;
        }
        .debug-mode {
            color: #0d47a1;
        }
        .execution-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <h1>TLS証明書期限チェッカー</h1>
    
    <?php if ($debug_flg == 1): ?>
    <div class="debug-banner">
        デバッグモード: 全てのサイトに対してテストメールを送信します
    </div>
    <?php endif; ?>
    
    <?php if ($hasAlerts): ?>
    <div class="alert-banner">
        警告: 1週間以内に期限切れになるTLS証明書があります！
    </div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>サイト名</th>
                <th>URL</th>
                <th>期待される証明書CN</th>
                <th>証明書期限</th>
                <th>残り日数</th>
                <th>ステータス</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($results as $result): ?>
                <?php 
                $rowClass = '';
                if ($result['is_alert']) {
                    $rowClass = 'alert';
                } elseif ($result['is_debug_mail']) {
                    $rowClass = 'debug';
                }
                $certInfo = $result['cert_info'];
                $site = $result['site'];
                ?>
                <tr class="<?php echo $rowClass; ?>">
                    <td><?php echo htmlspecialchars($site['name']); ?></td>
                    <td><?php echo htmlspecialchars($site['https_url']); ?></td>
                    <td><?php echo htmlspecialchars($site['certificate_commonname']); ?></td>
                    <td>
                        <?php if ($certInfo['error']): ?>
                            <span class="error">エラー: <?php echo htmlspecialchars($certInfo['error']); ?></span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($certInfo['expiry_date']); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($certInfo['days_remaining'] !== null): ?>
                            <?php echo htmlspecialchars($certInfo['days_remaining']); ?> 日
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($result['is_debug_mail']): ?>
                            <span class="debug-mode">
                                デバッグモード: テストメール送信
                                <?php if (isset($result['email_sent'])): ?>
                                    <br>
                                    <?php echo $result['email_sent'] ? 'メール送信済み' : 'メール送信失敗'; ?>
                                <?php endif; ?>
                            </span>
                        <?php elseif ($certInfo['error']): ?>
                            <span class="error">チェック失敗</span>
                        <?php elseif ($result['is_alert']): ?>
                            <span class="error">
                                警告: 期限切れまで1週間未満
                                <?php if (isset($result['email_sent'])): ?>
                                    <br>
                                    <?php echo $result['email_sent'] ? 'アラートメール送信済み' : 'メール送信失敗'; ?>
                                <?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="ok">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="execution-info">
        <p>最終チェック時刻: <?php echo date('Y-m-d H:i:s'); ?></p>
        <p>実行時間: <?php echo $executionTime; ?> 秒</p>
        <p>PHP バージョン: <?php echo phpversion(); ?></p>
        <p>PHPMailer: <?php echo $phpMailerAvailable ? '利用可能' : '利用不可'; ?></p>
        <p>デバッグモード: <?php echo $debug_flg ? 'オン' : 'オフ'; ?></p>
    </div>
    
    <script>
    // Simple script to auto-refresh the page every hour
    setTimeout(function() {
        location.reload();
    }, 3600000); // 1 hour in milliseconds
    </script>
</body>
</html>