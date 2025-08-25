<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Provider Manager for PPDB Form
 * Handles multiple email providers (Gmail, Outlook, Yahoo, etc.)
 */
class PPDB_Form_Email_Provider
{
    /**
     * Available email providers
     */
    private static array $providers = [
        'wordpress' => [
            'name' => 'WordPress Default',
            'description' => 'Menggunakan konfigurasi email default WordPress',
            'requires_auth' => false,
            'fields' => []
        ],
        'gmail' => [
            'name' => 'Gmail SMTP',
            'description' => 'Gmail dengan App Password atau OAuth2',
            'requires_auth' => true,
            'fields' => [
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_secure' => 'tls',
                'smtp_auth' => true,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => '',
                'from_name' => ''
            ]
        ],
        'outlook' => [
            'name' => 'Outlook/Hotmail SMTP',
            'description' => 'Microsoft Outlook/Hotmail SMTP',
            'requires_auth' => true,
            'fields' => [
                'smtp_host' => 'smtp-mail.outlook.com',
                'smtp_port' => 587,
                'smtp_secure' => 'tls',
                'smtp_auth' => true,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => '',
                'from_name' => ''
            ]
        ],
        'yahoo' => [
            'name' => 'Yahoo Mail SMTP',
            'description' => 'Yahoo Mail SMTP dengan App Password',
            'requires_auth' => true,
            'fields' => [
                'smtp_host' => 'smtp.mail.yahoo.com',
                'smtp_port' => 587,
                'smtp_secure' => 'tls',
                'smtp_auth' => true,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => '',
                'from_name' => ''
            ]
        ],
        'sendgrid' => [
            'name' => 'SendGrid API',
            'description' => 'SendGrid Email API Service',
            'requires_auth' => true,
            'fields' => [
                'api_key' => '',
                'from_email' => '',
                'from_name' => ''
            ]
        ],
        'mailgun' => [
            'name' => 'Mailgun API',
            'description' => 'Mailgun Email API Service',
            'requires_auth' => true,
            'fields' => [
                'api_key' => '',
                'domain' => '',
                'from_email' => '',
                'from_name' => ''
            ]
        ],
        'custom_smtp' => [
            'name' => 'Custom SMTP',
            'description' => 'Konfigurasi SMTP custom',
            'requires_auth' => true,
            'fields' => [
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_secure' => 'tls',
                'smtp_auth' => true,
                'smtp_username' => '',
                'smtp_password' => '',
                'from_email' => '',
                'from_name' => ''
            ]
        ]
    ];

    /**
     * Initialize email provider system
     */
    public static function init(): void
    {
        // Override wp_mail function when custom provider is selected
        add_action('phpmailer_init', [self::class, 'configure_phpmailer']);
        
        // Add email provider settings to admin
        add_action('admin_init', [self::class, 'register_settings']);
    }

    /**
     * Get all available providers
     */
    public static function get_providers(): array
    {
        return self::$providers;
    }

    /**
     * Get current active provider
     */
    public static function get_active_provider(): string
    {
        return get_option('ppdb_email_provider', 'wordpress');
    }

    /**
     * Get provider configuration
     */
    public static function get_provider_config(string $provider): array
    {
        $config = self::$providers[$provider] ?? [];
        $saved_config = get_option('ppdb_email_provider_config', []);
        
        if (isset($saved_config[$provider])) {
            $config['fields'] = array_merge($config['fields'] ?? [], $saved_config[$provider]);
        }
        
        return $config;
    }

    /**
     * Configure PHPMailer based on selected provider
     */
    public static function configure_phpmailer($phpmailer): void
    {
        $provider = self::get_active_provider();
        
        if ($provider === 'wordpress') {
            return; // Use WordPress default
        }
        
        $config = self::get_provider_config($provider);
        
        if (empty($config['fields'])) {
            return;
        }
        
        switch ($provider) {
            case 'gmail':
            case 'outlook':
            case 'yahoo':
            case 'custom_smtp':
                self::configure_smtp($phpmailer, $config['fields']);
                break;
                
            case 'sendgrid':
                self::configure_sendgrid($phpmailer, $config['fields']);
                break;
                
            case 'mailgun':
                self::configure_mailgun($phpmailer, $config['fields']);
                break;
        }
    }

    /**
     * Configure SMTP settings
     */
    private static function configure_smtp($phpmailer, array $config): void
    {
        if (empty($config['smtp_host']) || empty($config['smtp_username']) || empty($config['smtp_password'])) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $config['smtp_host'];
        $phpmailer->Port = (int) ($config['smtp_port'] ?? 587);
        $phpmailer->SMTPSecure = $config['smtp_secure'] ?? 'tls';
        $phpmailer->SMTPAuth = (bool) ($config['smtp_auth'] ?? true);
        $phpmailer->Username = $config['smtp_username'];
        $phpmailer->Password = $config['smtp_password'];
        
        if (!empty($config['from_email'])) {
            $phpmailer->setFrom($config['from_email'], $config['from_name'] ?? '');
        }
        
        // Enable SMTP debug for development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $phpmailer->SMTPDebug = 1;
        }
    }

    /**
     * Configure SendGrid API
     */
    private static function configure_sendgrid($phpmailer, array $config): void
    {
        if (empty($config['api_key'])) {
            return;
        }

        // SendGrid uses SMTP with API key as password
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.sendgrid.net';
        $phpmailer->Port = 587;
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = 'apikey';
        $phpmailer->Password = $config['api_key'];
        
        if (!empty($config['from_email'])) {
            $phpmailer->setFrom($config['from_email'], $config['from_name'] ?? '');
        }
    }

    /**
     * Configure Mailgun API
     */
    private static function configure_mailgun($phpmailer, array $config): void
    {
        if (empty($config['api_key']) || empty($config['domain'])) {
            return;
        }

        // Mailgun uses SMTP
        $phpmailer->isSMTP();
        $phpmailer->Host = 'smtp.mailgun.org';
        $phpmailer->Port = 587;
        $phpmailer->SMTPSecure = 'tls';
        $phpmailer->SMTPAuth = true;
        $phpmailer->Username = 'postmaster@' . $config['domain'];
        $phpmailer->Password = $config['api_key'];
        
        if (!empty($config['from_email'])) {
            $phpmailer->setFrom($config['from_email'], $config['from_name'] ?? '');
        }
    }

    /**
     * Test email configuration
     */
    public static function test_email_config(string $provider, array $config, string $test_email): array
    {
        // Temporarily save config
        $original_provider = self::get_active_provider();
        $original_config = get_option('ppdb_email_provider_config', []);
        
        update_option('ppdb_email_provider', $provider);
        update_option('ppdb_email_provider_config', [$provider => $config]);
        
        // Send test email
        $subject = 'Test Email - PPDB Form';
        $message = 'Ini adalah email test untuk memverifikasi konfigurasi email provider.' . "\n\n";
        $message .= 'Provider: ' . (self::$providers[$provider]['name'] ?? $provider) . "\n";
        $message .= 'Waktu: ' . date('d F Y H:i:s') . "\n\n";
        $message .= 'Jika Anda menerima email ini, konfigurasi email berhasil!';
        
        $result = wp_mail($test_email, $subject, $message);
        
        // Restore original config
        update_option('ppdb_email_provider', $original_provider);
        update_option('ppdb_email_provider_config', $original_config);
        
        return [
            'success' => $result,
            'message' => $result ? 'Email test berhasil dikirim!' : 'Gagal mengirim email test. Periksa konfigurasi Anda.'
        ];
    }

    /**
     * Register settings for email providers
     */
    public static function register_settings(): void
    {
        register_setting('ppdb_email_settings', 'ppdb_email_provider');
        register_setting('ppdb_email_settings', 'ppdb_email_provider_config');
    }

    /**
     * Get email provider instructions
     */
    public static function get_provider_instructions(string $provider): string
    {
        $instructions = [
            'gmail' => 'Untuk Gmail:<br>1. Aktifkan 2-Factor Authentication<br>2. Generate App Password di Google Account Settings<br>3. Gunakan App Password sebagai password, bukan password akun Gmail Anda',
            'outlook' => 'Untuk Outlook/Hotmail:<br>1. Aktifkan 2-Factor Authentication<br>2. Generate App Password di Microsoft Account Security<br>3. Gunakan App Password sebagai password',
            'yahoo' => 'Untuk Yahoo Mail:<br>1. Aktifkan 2-Factor Authentication<br>2. Generate App Password di Yahoo Account Security<br>3. Gunakan App Password sebagai password',
            'sendgrid' => 'Untuk SendGrid:<br>1. Daftar akun SendGrid<br>2. Buat API Key di Settings > API Keys<br>3. Pilih "Restricted Access" dan berikan permission "Mail Send"',
            'mailgun' => 'Untuk Mailgun:<br>1. Daftar akun Mailgun<br>2. Verifikasi domain Anda<br>3. Dapatkan API Key dari Settings > API Keys',
            'custom_smtp' => 'Untuk Custom SMTP:<br>1. Dapatkan detail SMTP dari provider email Anda<br>2. Pastikan SMTP authentication diaktifkan<br>3. Gunakan TLS/SSL sesuai kebutuhan provider'
        ];
        
        return $instructions[$provider] ?? 'Tidak ada instruksi khusus untuk provider ini.';
    }

    /**
     * Validate email provider configuration
     */
    public static function validate_config(string $provider, array $config): array
    {
        $errors = [];
        
        if (!isset(self::$providers[$provider])) {
            $errors[] = 'Provider email tidak valid.';
            return $errors;
        }
        
        $provider_info = self::$providers[$provider];
        
        if (!$provider_info['requires_auth']) {
            return $errors; // No validation needed for WordPress default
        }
        
        switch ($provider) {
            case 'gmail':
            case 'outlook':
            case 'yahoo':
            case 'custom_smtp':
                if (empty($config['smtp_host'])) {
                    $errors[] = 'SMTP Host wajib diisi.';
                }
                if (empty($config['smtp_username'])) {
                    $errors[] = 'Username/Email wajib diisi.';
                }
                if (empty($config['smtp_password'])) {
                    $errors[] = 'Password/App Password wajib diisi.';
                }
                if (empty($config['from_email']) || !is_email($config['from_email'])) {
                    $errors[] = 'From Email harus berupa email yang valid.';
                }
                break;
                
            case 'sendgrid':
                if (empty($config['api_key'])) {
                    $errors[] = 'SendGrid API Key wajib diisi.';
                }
                if (empty($config['from_email']) || !is_email($config['from_email'])) {
                    $errors[] = 'From Email harus berupa email yang valid.';
                }
                break;
                
            case 'mailgun':
                if (empty($config['api_key'])) {
                    $errors[] = 'Mailgun API Key wajib diisi.';
                }
                if (empty($config['domain'])) {
                    $errors[] = 'Mailgun Domain wajib diisi.';
                }
                if (empty($config['from_email']) || !is_email($config['from_email'])) {
                    $errors[] = 'From Email harus berupa email yang valid.';
                }
                break;
        }
        
        return $errors;
    }
}
