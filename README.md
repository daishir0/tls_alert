# TLS Alert

## Overview
TLS Alert is a PHP-based tool that checks the expiration dates of TLS certificates for specified websites. It displays a report showing the expiration dates and sends email alerts when certificates are about to expire within a week. It also includes a debug mode for testing the email notification functionality.

## Installation
1. Clone the repository:
```bash
git clone https://github.com/daishir0/tls_alert
cd tls_alert
```

2. Install dependencies using Composer:
```bash
composer install
```

3. Create a configuration file:
```bash
cp config.sample.php config.php
```

4. Edit the `config.php` file with your settings:
   - Set your Gmail account and app password for sending alerts
   - Configure the list of websites to monitor
   - Set the debug flag (1 to always send test emails, 0 for normal operation)

## Usage
1. Access the tool via web browser:
```
http://your-server/tls_alert/index.php
```

2. For automated checking, set up a cron job:
```bash
# Run daily at 9:00 AM
0 9 * * * /path/to/tls_alert/check_certificates.sh
```

3. Debug mode:
   - Set `$debug_flg = 1` in config.php to send test emails regardless of certificate expiration status
   - This is useful for verifying that email notifications are working correctly

## Notes
- The `config.php` file contains sensitive information and should not be committed to version control
- For Gmail integration, you need to create an App Password in your Google Account security settings
- The script requires PHP with OpenSSL support
- If PHPMailer is not available, the script will fall back to the standard PHP mail() function

## License
This project is licensed under the MIT License - see the LICENSE file for details.

---

# TLS Alert

## 概要
TLS Alertは、指定されたウェブサイトのTLS証明書の有効期限をチェックするPHPベースのツールです。証明書の有効期限を表示するレポートを生成し、1週間以内に期限切れになる証明書がある場合にメールアラートを送信します。また、メール通知機能をテストするためのデバッグモードも備えています。

## インストール方法
1. リポジトリをクローンします：
```bash
git clone https://github.com/daishir0/tls_alert
cd tls_alert
```

2. Composerで依存関係をインストールします：
```bash
composer install
```

3. 設定ファイルを作成します：
```bash
cp config.sample.php config.php
```

4. `config.php`ファイルを編集して設定を行います：
   - アラート送信用のGmailアカウントとアプリパスワードを設定
   - 監視対象のウェブサイトリストを設定
   - デバッグフラグを設定（1：常にテストメールを送信、0：通常動作）

## 使い方
1. ウェブブラウザからアクセスします：
```
http://your-server/tls_alert/index.php
```

2. 自動チェックのためにcronジョブを設定します：
```bash
# 毎日午前9時に実行
0 9 * * * /path/to/tls_alert/check_certificates.sh
```

3. デバッグモード：
   - config.phpで`$debug_flg = 1`に設定すると、証明書の有効期限に関係なくテストメールが送信されます
   - これはメール通知が正しく機能していることを確認するのに役立ちます

## 注意点
- `config.php`ファイルには機密情報が含まれているため、バージョン管理システムにコミットしないでください
- Gmail連携のためには、Googleアカウントのセキュリティ設定でアプリパスワードを作成する必要があります
- このスクリプトはOpenSSLサポート付きのPHPが必要です
- PHPMailerが利用できない場合、スクリプトは標準のPHP mail()関数にフォールバックします

## ライセンス
このプロジェクトはMITライセンスの下でライセンスされています。詳細はLICENSEファイルを参照してください。