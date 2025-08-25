<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Settings Page for PPDB Form
 * Manages email provider configuration interface
 */
class PPDB_Form_Email_Settings
{
    /**
     * Initialize email settings
     */
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_settings_page']);
        add_action('admin_init', [self::class, 'handle_form_submission']);
        add_action('wp_ajax_ppdb_test_email', [self::class, 'ajax_test_email']);
    }

    /**
     * Add email settings page to admin menu
     */
    public static function add_settings_page(): void
    {
        add_submenu_page(
            'ppdb-form',
            __('Pengaturan Email', 'ppdb-form'),
            __('Pengaturan Email', 'ppdb-form'),
            'manage_options',
            'ppdb-form-email-settings',
            [self::class, 'render_settings_page']
        );
    }

    /**
     * Handle form submission
     */
    public static function handle_form_submission(): void
    {
        if (!isset($_POST['ppdb_email_settings_nonce']) || 
            !wp_verify_nonce($_POST['ppdb_email_settings_nonce'], 'ppdb_email_settings')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $provider = sanitize_text_field($_POST['email_provider'] ?? 'wordpress');
        $config = [];

        // Get provider configuration
        $providers = PPDB_Form_Email_Provider::get_providers();
        if (!isset($providers[$provider])) {
            add_settings_error('ppdb_email_settings', 'invalid_provider', 'Provider email tidak valid.');
            return;
        }

        // Process configuration fields
        if (isset($_POST['config'][$provider])) {
            foreach ($_POST['config'][$provider] as $key => $value) {
                $config[$key] = sanitize_text_field($value);
            }
        }

        // Validate configuration
        $errors = PPDB_Form_Email_Provider::validate_config($provider, $config);
        if (!empty($errors)) {
            foreach ($errors as $error) {
                add_settings_error('ppdb_email_settings', 'config_error', $error);
            }
            return;
        }

        // Save settings
        update_option('ppdb_email_provider', $provider);
        
        $all_configs = get_option('ppdb_email_provider_config', []);
        $all_configs[$provider] = $config;
        update_option('ppdb_email_provider_config', $all_configs);

        add_settings_error('ppdb_email_settings', 'settings_saved', 'Pengaturan email berhasil disimpan.', 'updated');
    }

    /**
     * AJAX test email handler
     */
    public static function ajax_test_email(): void
    {
        check_ajax_referer('ppdb_test_email', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $test_email = sanitize_email($_POST['test_email'] ?? '');
        $config = [];

        if (isset($_POST['config']) && is_array($_POST['config'])) {
            foreach ($_POST['config'] as $key => $value) {
                $config[$key] = sanitize_text_field($value);
            }
        }

        if (empty($test_email) || !is_email($test_email)) {
            wp_send_json_error('Email test tidak valid.');
        }

        $result = PPDB_Form_Email_Provider::test_email_config($provider, $config, $test_email);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    /**
     * Render settings page
     */
    public static function render_settings_page(): void
    {
        $current_provider = PPDB_Form_Email_Provider::get_active_provider();
        $providers = PPDB_Form_Email_Provider::get_providers();
        $current_config = get_option('ppdb_email_provider_config', []);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Pengaturan Email Provider', 'ppdb-form'); ?></h1>
            
            <?php settings_errors('ppdb_email_settings'); ?>
            
            <div class="ppdb-email-settings">
                <form method="post" action="">
                    <?php wp_nonce_field('ppdb_email_settings', 'ppdb_email_settings_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="email_provider"><?php echo esc_html__('Email Provider', 'ppdb-form'); ?></label>
                            </th>
                            <td>
                                <select name="email_provider" id="email_provider" class="regular-text">
                                    <?php foreach ($providers as $key => $provider): ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($current_provider, $key); ?>>
                                            <?php echo esc_html($provider['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php echo esc_html__('Pilih provider email yang akan digunakan untuk mengirim email.', 'ppdb-form'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <?php foreach ($providers as $provider_key => $provider): ?>
                        <div class="provider-config" id="config-<?php echo esc_attr($provider_key); ?>" 
                             style="<?php echo $current_provider === $provider_key ? '' : 'display:none;'; ?>">
                            
                            <h2><?php echo esc_html($provider['name']); ?></h2>
                            <p><?php echo esc_html($provider['description']); ?></p>
                            
                            <?php if ($provider['requires_auth']): ?>
                                <div class="provider-instructions">
                                    <h3><?php echo esc_html__('Instruksi Setup', 'ppdb-form'); ?></h3>
                                    <div class="notice notice-info">
                                        <p><?php echo wp_kses_post(PPDB_Form_Email_Provider::get_provider_instructions($provider_key)); ?></p>
                                    </div>
                                </div>

                                <table class="form-table">
                                    <?php 
                                    $config = $current_config[$provider_key] ?? [];
                                    foreach ($provider['fields'] as $field_key => $default_value): 
                                        $field_value = $config[$field_key] ?? $default_value;
                                        $field_type = self::get_field_type($field_key);
                                        $field_label = self::get_field_label($field_key);
                                    ?>
                                        <tr>
                                            <th scope="row">
                                                <label for="<?php echo esc_attr($provider_key . '_' . $field_key); ?>">
                                                    <?php echo esc_html($field_label); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <?php if ($field_type === 'password'): ?>
                                                    <input type="password" 
                                                           name="config[<?php echo esc_attr($provider_key); ?>][<?php echo esc_attr($field_key); ?>]"
                                                           id="<?php echo esc_attr($provider_key . '_' . $field_key); ?>"
                                                           value="<?php echo esc_attr($field_value); ?>"
                                                           class="regular-text" />
                                                <?php elseif ($field_type === 'select'): ?>
                                                    <select name="config[<?php echo esc_attr($provider_key); ?>][<?php echo esc_attr($field_key); ?>]"
                                                            id="<?php echo esc_attr($provider_key . '_' . $field_key); ?>"
                                                            class="regular-text">
                                                        <?php foreach (self::get_field_options($field_key) as $option_value => $option_label): ?>
                                                            <option value="<?php echo esc_attr($option_value); ?>" <?php selected($field_value, $option_value); ?>>
                                                                <?php echo esc_html($option_label); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="<?php echo esc_attr($field_type); ?>" 
                                                           name="config[<?php echo esc_attr($provider_key); ?>][<?php echo esc_attr($field_key); ?>]"
                                                           id="<?php echo esc_attr($provider_key . '_' . $field_key); ?>"
                                                           value="<?php echo esc_attr($field_value); ?>"
                                                           class="regular-text" />
                                                <?php endif; ?>
                                                
                                                <?php if ($description = self::get_field_description($field_key)): ?>
                                                    <p class="description"><?php echo esc_html($description); ?></p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>

                                <div class="test-email-section">
                                    <h3><?php echo esc_html__('Test Konfigurasi', 'ppdb-form'); ?></h3>
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row">
                                                <label for="test_email_<?php echo esc_attr($provider_key); ?>">
                                                    <?php echo esc_html__('Email Test', 'ppdb-form'); ?>
                                                </label>
                                            </th>
                                            <td>
                                                <input type="email" 
                                                       id="test_email_<?php echo esc_attr($provider_key); ?>"
                                                       class="regular-text test-email-input"
                                                       placeholder="test@example.com" />
                                                <button type="button" 
                                                        class="button test-email-btn" 
                                                        data-provider="<?php echo esc_attr($provider_key); ?>">
                                                    <?php echo esc_html__('Kirim Test Email', 'ppdb-form'); ?>
                                                </button>
                                                <div class="test-result" id="test-result-<?php echo esc_attr($provider_key); ?>"></div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p><?php echo esc_html__('Provider ini menggunakan konfigurasi default WordPress.', 'ppdb-form'); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php submit_button(__('Simpan Pengaturan', 'ppdb-form')); ?>
                </form>
            </div>
        </div>

        <style>
        .ppdb-email-settings .provider-config {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            background: #fff;
        }
        .provider-instructions {
            margin: 15px 0;
        }
        .test-email-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        .test-result {
            margin-top: 10px;
        }
        .test-result.success {
            color: #10b981;
            font-weight: 600;
        }
        .test-result.error {
            color: #ef4444;
            font-weight: 600;
        }
        .test-email-btn {
            margin-left: 10px;
        }
        .test-email-btn:disabled {
            opacity: 0.6;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Provider selection change
            $('#email_provider').on('change', function() {
                var selectedProvider = $(this).val();
                $('.provider-config').hide();
                $('#config-' + selectedProvider).show();
            });

            // Test email functionality
            $('.test-email-btn').on('click', function() {
                var $btn = $(this);
                var provider = $btn.data('provider');
                var $emailInput = $('#test_email_' + provider);
                var testEmail = $emailInput.val();
                var $result = $('#test-result-' + provider);

                if (!testEmail || !isValidEmail(testEmail)) {
                    $result.removeClass('success').addClass('error').text('Masukkan email yang valid.');
                    return;
                }

                // Collect configuration data
                var configData = {};
                $('#config-' + provider + ' input, #config-' + provider + ' select').each(function() {
                    var name = $(this).attr('name');
                    if (name && name.includes('[' + provider + ']')) {
                        var fieldName = name.match(/\[([^\]]+)\]$/)[1];
                        configData[fieldName] = $(this).val();
                    }
                });

                $btn.prop('disabled', true).text('Mengirim...');
                $result.removeClass('success error').text('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ppdb_test_email',
                        nonce: '<?php echo wp_create_nonce('ppdb_test_email'); ?>',
                        provider: provider,
                        test_email: testEmail,
                        config: configData
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.addClass('success').text(response.data);
                        } else {
                            $result.addClass('error').text(response.data);
                        }
                    },
                    error: function() {
                        $result.addClass('error').text('Terjadi kesalahan saat mengirim email test.');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php echo esc_js(__('Kirim Test Email', 'ppdb-form')); ?>');
                    }
                });
            });

            function isValidEmail(email) {
                var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
        });
        </script>
        <?php
    }

    /**
     * Get field type for input rendering
     */
    private static function get_field_type(string $field_key): string
    {
        $password_fields = ['smtp_password', 'api_key'];
        $number_fields = ['smtp_port'];
        $email_fields = ['from_email', 'smtp_username'];
        $select_fields = ['smtp_secure'];

        if (in_array($field_key, $password_fields)) {
            return 'password';
        } elseif (in_array($field_key, $number_fields)) {
            return 'number';
        } elseif (in_array($field_key, $email_fields)) {
            return 'email';
        } elseif (in_array($field_key, $select_fields)) {
            return 'select';
        }

        return 'text';
    }

    /**
     * Get field label
     */
    private static function get_field_label(string $field_key): string
    {
        $labels = [
            'smtp_host' => 'SMTP Host',
            'smtp_port' => 'SMTP Port',
            'smtp_secure' => 'Encryption',
            'smtp_auth' => 'SMTP Authentication',
            'smtp_username' => 'Username/Email',
            'smtp_password' => 'Password/App Password',
            'from_email' => 'From Email',
            'from_name' => 'From Name',
            'api_key' => 'API Key',
            'domain' => 'Domain'
        ];

        return $labels[$field_key] ?? ucfirst(str_replace('_', ' ', $field_key));
    }

    /**
     * Get field description
     */
    private static function get_field_description(string $field_key): string
    {
        $descriptions = [
            'smtp_password' => 'Untuk Gmail/Outlook/Yahoo, gunakan App Password bukan password akun.',
            'from_email' => 'Email pengirim yang akan muncul di email yang dikirim.',
            'from_name' => 'Nama pengirim yang akan muncul di email yang dikirim.',
            'api_key' => 'API Key dari provider email service.',
            'domain' => 'Domain yang telah diverifikasi di Mailgun.'
        ];

        return $descriptions[$field_key] ?? '';
    }

    /**
     * Get field options for select fields
     */
    private static function get_field_options(string $field_key): array
    {
        $options = [
            'smtp_secure' => [
                'tls' => 'TLS',
                'ssl' => 'SSL',
                '' => 'None'
            ]
        ];

        return $options[$field_key] ?? [];
    }
}
