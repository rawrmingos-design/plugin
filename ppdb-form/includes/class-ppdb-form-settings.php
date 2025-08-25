<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Settings
{
  public static function register_settings(): void
  {
    // Upload constraints
    register_setting('ppdb_form_settings', 'ppdb_upload_max_mb', [
      'type' => 'integer',
      'sanitize_callback' => 'absint',
      'default' => 5,
    ]);
    register_setting('ppdb_form_settings', 'ppdb_upload_allowed_mimes', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'pdf,jpg,jpeg,png,heic,heif,webp,zip',
    ]);
    register_setting('ppdb_form_settings', 'ppdb_form_email_admin', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_email',
      'default' => get_option('admin_email'),
    ]);

    register_setting('ppdb_form_settings', 'ppdb_form_email_subject', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => __('Pendaftaran Baru - {nama_lengkap}', 'ppdb-form'),
    ]);

    register_setting('ppdb_form_settings', 'ppdb_form_email_template', [
      'type' => 'string',
      'sanitize_callback' => 'wp_kses_post',
      'default' => self::get_default_email_template(),
    ]);

    register_setting('ppdb_form_settings', 'ppdb_recaptcha_site_key', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => '',
    ]);

    register_setting('ppdb_form_settings', 'ppdb_recaptcha_secret_key', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => '',
    ]);

    register_setting('ppdb_form_settings', 'ppdb_form_rate_limit', [
      'type' => 'integer',
      'sanitize_callback' => 'absint',
      'default' => 20,
    ]);

    register_setting('ppdb_form_settings', 'ppdb_form_enable_notifications', [
      'type' => 'boolean',
      'sanitize_callback' => 'rest_sanitize_boolean',
      'default' => true,
    ]);

    register_setting('ppdb_form_settings', 'ppdb_form_user_email_subject', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => __('Konfirmasi Pendaftaran - {nama_lengkap}', 'ppdb-form'),
    ]);

    register_setting('ppdb_form_settings', 'ppdb_form_user_email_template', [
      'type' => 'string',
      'sanitize_callback' => 'wp_kses_post',
      'default' => self::get_default_user_email_template(),
    ]);

    // Button brand colors
    register_setting('ppdb_form_settings', 'ppdb_btn_color_start', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => '#3b82f6',
    ]);
    register_setting('ppdb_form_settings', 'ppdb_btn_color_end', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => '#2563eb',
    ]);

    // Submit behavior & success message
    register_setting('ppdb_form_settings', 'ppdb_submit_behavior', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'redirect',  // redirect | inline
    ]);
    register_setting('ppdb_form_settings', 'ppdb_success_template', [
      'type' => 'string',
      'sanitize_callback' => 'wp_kses_post',
      'default' => __('<strong>Terima kasih {nama_lengkap}!</strong> Pendaftaran Anda berhasil dikirim. Nomor pendaftaran: {submission_id}.', 'ppdb-form'),
    ]);
    register_setting('ppdb_form_settings', 'ppdb_success_show_summary', [
      'type' => 'boolean',
      'sanitize_callback' => 'rest_sanitize_boolean',
      'default' => true,
    ]);

    // Certificate settings
    register_setting('ppdb_form_settings', 'ppdb_institution_name', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => get_bloginfo('name'),
    ]);
    register_setting('ppdb_form_settings', 'ppdb_institution_logo', [
      'type' => 'string',
      'sanitize_callback' => 'esc_url_raw',
      'default' => '',
    ]);
    register_setting('ppdb_form_settings', 'ppdb_certificate_title', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'BUKTI PENDAFTARAN',
    ]);
    register_setting('ppdb_form_settings', 'ppdb_reg_number_prefix', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'REG',
    ]);
    register_setting('ppdb_form_settings', 'ppdb_certificate_secret', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => wp_generate_password(32, false),
    ]);

    // Certificate email settings
    register_setting('ppdb_form_settings', 'ppdb_certificate_auto_send', [
      'type' => 'boolean',
      'sanitize_callback' => 'rest_sanitize_boolean',
      'default' => false,
    ]);
    register_setting('ppdb_form_settings', 'ppdb_certificate_email_subject', [
      'type' => 'string',
      'sanitize_callback' => 'sanitize_text_field',
      'default' => 'Bukti Pendaftaran - {nama_lengkap}',
    ]);
    register_setting('ppdb_form_settings', 'ppdb_certificate_email_template', [
      'type' => 'string',
      'sanitize_callback' => 'wp_kses_post',
      'default' => 'Halo {nama_lengkap}, terima kasih telah mendaftar. Bukti pendaftaran Anda dapat diakses melalui link di bawah.',
    ]);
  }

  public static function render_settings_page(): void
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
    }

    if (isset($_POST['submit'])) {
      check_admin_referer('ppdb_form_settings');

      update_option('ppdb_form_email_admin', sanitize_email((string) ($_POST['ppdb_form_email_admin'] ?? '')));
      update_option('ppdb_form_email_subject', sanitize_text_field((string) ($_POST['ppdb_form_email_subject'] ?? '')));
      update_option('ppdb_form_email_template', wp_kses_post((string) ($_POST['ppdb_form_email_template'] ?? '')));
      update_option('ppdb_recaptcha_site_key', sanitize_text_field((string) ($_POST['ppdb_recaptcha_site_key'] ?? '')));
      update_option('ppdb_recaptcha_secret_key', sanitize_text_field((string) ($_POST['ppdb_recaptcha_secret_key'] ?? '')));
      update_option('ppdb_form_rate_limit', max(1, (int) ($_POST['ppdb_form_rate_limit'] ?? 20)));
      update_option('ppdb_form_enable_notifications', !empty($_POST['ppdb_form_enable_notifications']));
      update_option('ppdb_form_user_email_subject', sanitize_text_field((string) ($_POST['ppdb_form_user_email_subject'] ?? '')));
      update_option('ppdb_form_user_email_template', wp_kses_post((string) ($_POST['ppdb_form_user_email_template'] ?? '')));
      // Colors
      update_option('ppdb_btn_color_start', sanitize_text_field((string) ($_POST['ppdb_btn_color_start'] ?? '#3b82f6')));
      update_option('ppdb_btn_color_end', sanitize_text_field((string) ($_POST['ppdb_btn_color_end'] ?? '#2563eb')));
      update_option('ppdb_upload_max_mb', max(1, (int) ($_POST['ppdb_upload_max_mb'] ?? 5)));
      update_option('ppdb_upload_allowed_mimes', sanitize_text_field((string) ($_POST['ppdb_upload_allowed_mimes'] ?? 'pdf,jpg,jpeg,png,heic,heif,webp,zip')));

      // Submit behavior & success
      update_option('ppdb_submit_behavior', in_array(($_POST['ppdb_submit_behavior'] ?? 'redirect'), ['redirect', 'inline'], true) ? (string) $_POST['ppdb_submit_behavior'] : 'redirect');
      update_option('ppdb_success_template', wp_kses_post((string) ($_POST['ppdb_success_template'] ?? '')));
      update_option('ppdb_success_show_summary', !empty($_POST['ppdb_success_show_summary']));

      // Certificate settings
      update_option('ppdb_institution_name', sanitize_text_field((string) ($_POST['ppdb_institution_name'] ?? get_bloginfo('name'))));
      update_option('ppdb_institution_logo', esc_url_raw((string) ($_POST['ppdb_institution_logo'] ?? '')));
      update_option('ppdb_certificate_title', sanitize_text_field((string) ($_POST['ppdb_certificate_title'] ?? 'BUKTI PENDAFTARAN')));
      update_option('ppdb_reg_number_prefix', sanitize_text_field((string) ($_POST['ppdb_reg_number_prefix'] ?? 'REG')));

      // Certificate email settings
      update_option('ppdb_certificate_auto_send', !empty($_POST['ppdb_certificate_auto_send']));
      update_option('ppdb_certificate_email_subject', sanitize_text_field((string) ($_POST['ppdb_certificate_email_subject'] ?? 'Bukti Pendaftaran - {nama_lengkap}')));
      update_option('ppdb_certificate_email_template', wp_kses_post((string) ($_POST['ppdb_certificate_email_template'] ?? '')));

      echo '<div class="updated"><p>' . esc_html__('Pengaturan disimpan.', 'ppdb-form') . '</p></div>';
    }

    $email_admin = get_option('ppdb_form_email_admin', get_option('admin_email'));
    $email_subject = get_option('ppdb_form_email_subject', __('Pendaftaran Baru - {nama_lengkap}', 'ppdb-form'));
    $email_template = get_option('ppdb_form_email_template', self::get_default_email_template());
    $recaptcha_site = get_option('ppdb_recaptcha_site_key', '');
    $recaptcha_secret = get_option('ppdb_recaptcha_secret_key', '');
    $rate_limit = (int) get_option('ppdb_form_rate_limit', 20);
    $enable_notifications = (bool) get_option('ppdb_form_enable_notifications', true);
    $upload_max_mb = (int) get_option('ppdb_upload_max_mb', 5);
    $upload_allowed_mimes = get_option('ppdb_upload_allowed_mimes', 'pdf,jpg,jpeg,png,heic,heif,webp,zip');
    $user_email_subject = get_option('ppdb_form_user_email_subject', __('Konfirmasi Pendaftaran - {nama_lengkap}', 'ppdb-form'));
    $user_email_template = get_option('ppdb_form_user_email_template', self::get_default_user_email_template());
    $btn_color_start = get_option('ppdb_btn_color_start', '#3b82f6');
    $btn_color_end = get_option('ppdb_btn_color_end', '#2563eb');
    $submit_behavior = get_option('ppdb_submit_behavior', 'redirect');
    $success_template = get_option('ppdb_success_template', __('<strong>Terima kasih {nama_lengkap}!</strong> Pendaftaran Anda berhasil dikirim. Nomor pendaftaran: {submission_id}.', 'ppdb-form'));
    $success_show_summary = (bool) get_option('ppdb_success_show_summary', true);

    // Certificate settings
    $institution_name = get_option('ppdb_institution_name', get_bloginfo('name'));
    $institution_logo = get_option('ppdb_institution_logo', '');
    $certificate_title = get_option('ppdb_certificate_title', 'BUKTI PENDAFTARAN');
    $reg_number_prefix = get_option('ppdb_reg_number_prefix', 'REG');

    // Certificate email settings
    $certificate_auto_send = (bool) get_option('ppdb_certificate_auto_send', false);
    $certificate_email_subject = get_option('ppdb_certificate_email_subject', 'Bukti Pendaftaran - {nama_lengkap}');
    $certificate_email_template = get_option('ppdb_certificate_email_template', 'Halo {nama_lengkap}, terima kasih telah mendaftar. Bukti pendaftaran Anda dapat diakses melalui link di bawah.');

    ?>
        <div class="wrap">
            <h1><?php esc_html_e('Pengaturan PPDB Form', 'ppdb-form'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('ppdb_form_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Perilaku Setelah Submit', 'ppdb-form'); ?></th>
                        <td>
                            <label style="display:block; margin-bottom:6px;">
                                <input type="radio" name="ppdb_submit_behavior" value="redirect" <?php checked($submit_behavior, 'redirect'); ?> />
                                <?php esc_html_e('Redirect (direkomendasikan) – hindari double submit, tampilkan pesan sukses di URL yang sama.', 'ppdb-form'); ?>
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="ppdb_submit_behavior" value="inline" <?php checked($submit_behavior, 'inline'); ?> />
                                <?php esc_html_e('Inline – tampilkan pesan sukses tanpa pindah halaman.', 'ppdb-form'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Template Pesan Sukses', 'ppdb-form'); ?></th>
                        <td>
                            <textarea name="ppdb_success_template" rows="3" class="large-text"><?php echo esc_textarea($success_template); ?></textarea>
                            <p class="description"><?php esc_html_e('Placeholder: {submission_id}, {nama_lengkap}, {jurusan}, {email}, dst. (mengikuti field yang aktif)', 'ppdb-form'); ?></p>
                            <label style="margin-top:6px; display:inline-block;">
                                <input type="checkbox" name="ppdb_success_show_summary" value="1" <?php checked($success_show_summary); ?> />
                                <?php esc_html_e('Tampilkan ringkasan data pendaftaran', 'ppdb-form'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Warna Tombol (Gradient)', 'ppdb-form'); ?></th>
                        <td>
                            <label style="display:inline-block;margin-right:12px;">
                                <span style="display:inline-block;min-width:110px;">Start</span>
                                <input type="color" name="ppdb_btn_color_start" value="<?php echo esc_attr($btn_color_start); ?>" />
                            </label>
                            <label style="display:inline-block;">
                                <span style="display:inline-block;min-width:110px;">End</span>
                                <input type="color" name="ppdb_btn_color_end" value="<?php echo esc_attr($btn_color_end); ?>" />
                            </label>
                            <p class="description"><?php esc_html_e('Sesuaikan warna brand untuk tombol utama.', 'ppdb-form'); ?></p>
                            <style>
                              #ppdb_btn_preview .ppdb-btn {
                                appearance: none;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                padding: 10px 16px;
                                font-weight: 600;
                                border-radius: 10px;
                                border: none;
                                color: #fff;
                                background: linear-gradient(90deg, var(--ppdb-btn-bg-start, #3b82f6), var(--ppdb-btn-bg-end, #2563eb));
                                box-shadow: 0 10px 16px -10px rgba(0,0,0,.45);
                              }
                              #ppdb_btn_preview .ppdb-btn:hover { filter: brightness(0.98); }
                            </style>
                            <div id="ppdb_btn_preview" style="--ppdb-btn-bg-start: <?php echo esc_attr($btn_color_start); ?>; --ppdb-btn-bg-end: <?php echo esc_attr($btn_color_end); ?>; margin-top:8px;">
                                <button type="button" class="ppdb-btn" style="max-width:260px;">&nbsp;<?php esc_html_e('Contoh Tombol', 'ppdb-form'); ?>&nbsp;</button>
                            </div>
                            <script>
                              document.addEventListener('input', function(ev){
                                if(!ev.target) return;
                                if(ev.target.name === 'ppdb_btn_color_start' || ev.target.name === 'ppdb_btn_color_end'){
                                  var wrap = document.getElementById('ppdb_btn_preview');
                                  if(!wrap) return;
                                  if(ev.target.name === 'ppdb_btn_color_start'){
                                    wrap.style.setProperty('--ppdb-btn-bg-start', ev.target.value);
                                  } else {
                                    wrap.style.setProperty('--ppdb-btn-bg-end', ev.target.value);
                                  }
                                }
                              });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Email Admin', 'ppdb-form'); ?></th>
                        <td>
                            <input type="email" name="ppdb_form_email_admin" value="<?php echo esc_attr($email_admin); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Email untuk menerima notifikasi pendaftaran baru.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Aktifkan Notifikasi', 'ppdb-form'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ppdb_form_enable_notifications" value="1" <?php checked($enable_notifications); ?> />
                                <?php esc_html_e('Kirim email notifikasi saat ada pendaftaran baru', 'ppdb-form'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Subject Email Admin', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_form_email_subject" value="<?php echo esc_attr($email_subject); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Gunakan {nama_lengkap}, {email}, {jurusan} untuk placeholder.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Template Email Admin', 'ppdb-form'); ?></th>
                        <td>
                            <textarea name="ppdb_form_email_template" rows="8" class="large-text"><?php echo esc_textarea($email_template); ?></textarea>
                            <p class="description"><?php esc_html_e('Template email untuk admin. Gunakan {field_name} untuk data pendaftar.', 'ppdb-form'); ?></p>
                            <?php
                            $registry = PPDB_Form_Plugin::get_field_registry();
                            $tags = ['{nama_lengkap}', '{email}', '{nomor_telepon}', '{jurusan}'];
                            foreach ($registry as $k => $_m) {
                              $tags[] = '{' . $k . '}';
                            }
                            $tags = array_unique($tags);
                            echo '<div style="margin-top:8px"><strong>' . esc_html__('Placeholder Tersedia:', 'ppdb-form') . '</strong><br/>';
                            foreach ($tags as $tg) {
                              echo '<code class="ppdb-tag" data-tag="' . esc_attr($tg) . '" style="margin:2px 6px 2px 0; display:inline-block; cursor:pointer;">' . esc_html($tg) . '</code>';
                            }
                            echo '</div>';
                            ?>
                            
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Subject Email Pendaftar', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_form_user_email_subject" value="<?php echo esc_attr($user_email_subject); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Template Email Pendaftar', 'ppdb-form'); ?></th>
                        <td>
                            <textarea name="ppdb_form_user_email_template" rows="6" class="large-text"><?php echo esc_textarea($user_email_template); ?></textarea>
                            <p class="description"><?php esc_html_e('Email konfirmasi untuk pendaftar.', 'ppdb-form'); ?></p>
                            
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('reCAPTCHA Site Key', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_recaptcha_site_key" value="<?php echo esc_attr($recaptcha_site); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Kosongkan untuk menonaktifkan reCAPTCHA.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('reCAPTCHA Secret Key', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_recaptcha_secret_key" value="<?php echo esc_attr($recaptcha_secret); ?>" class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Rate Limit', 'ppdb-form'); ?></th>
                        <td>
                            <input type="number" name="ppdb_form_rate_limit" value="<?php echo esc_attr((string) $rate_limit); ?>" min="1" max="100" />
                            <p class="description"><?php esc_html_e('Maksimal submission per IP per menit.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Maksimum Ukuran Upload (MB)', 'ppdb-form'); ?></th>
                        <td>
                            <input type="number" name="ppdb_upload_max_mb" value="<?php echo esc_attr((string) $upload_max_mb); ?>" min="1" max="50" />
                            <p class="description"><?php esc_html_e('Batas maksimum ukuran file upload dokumen.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Ekstensi File yang Diizinkan', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_upload_allowed_mimes" value="<?php echo esc_attr($upload_allowed_mimes); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Pisahkan dengan koma. Contoh: pdf,jpg,jpeg,png,heic,heif,webp,zip', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Certificate Settings Section -->
                    <tr>
                        <th colspan="2" style="background: #f0f0f0; padding: 15px; font-weight: bold; border-top: 2px solid #ccc;">
                            📄 <?php esc_html_e('Pengaturan Bukti Pendaftaran', 'ppdb-form'); ?>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Nama Institusi', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_institution_name" value="<?php echo esc_attr($institution_name); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Nama sekolah/institusi yang akan ditampilkan di bukti pendaftaran.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Logo Institusi', 'ppdb-form'); ?></th>
                        <td>
                            <input type="url" name="ppdb_institution_logo" value="<?php echo esc_attr($institution_logo); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('URL logo institusi (kosongkan jika tidak ada). Contoh: https://domain.com/logo.png', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Judul Bukti Pendaftaran', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_certificate_title" value="<?php echo esc_attr($certificate_title); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Judul yang akan ditampilkan di bagian atas bukti pendaftaran.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Prefix Nomor Registrasi', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_reg_number_prefix" value="<?php echo esc_attr($reg_number_prefix); ?>" style="width: 100px;" maxlength="10" />
                            <p class="description"><?php esc_html_e('Prefix untuk nomor registrasi. Contoh: REG → REG2024000123', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    
                    <!-- Certificate Email Settings -->
                    <tr>
                        <th colspan="2" style="background: #e8f4fd; padding: 15px; font-weight: bold; border-top: 2px solid #2196f3;">
                            📧 <?php esc_html_e('Email Bukti Pendaftaran', 'ppdb-form'); ?>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Auto-Send Certificate', 'ppdb-form'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ppdb_certificate_auto_send" value="1" <?php checked($certificate_auto_send); ?> />
                                <?php esc_html_e('Kirim bukti pendaftaran otomatis via email setelah pendaftaran berhasil', 'ppdb-form'); ?>
                            </label>
                            <p class="description"><?php esc_html_e('Jika diaktifkan, bukti pendaftaran akan dikirim otomatis ke email pendaftar.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Subject Email Certificate', 'ppdb-form'); ?></th>
                        <td>
                            <input type="text" name="ppdb_certificate_email_subject" value="<?php echo esc_attr($certificate_email_subject); ?>" class="regular-text" />
                            <p class="description"><?php esc_html_e('Subject email untuk bukti pendaftaran. Gunakan {nama_lengkap}, {reg_number}, dll.', 'ppdb-form'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Template Email Certificate', 'ppdb-form'); ?></th>
                        <td>
                            <textarea name="ppdb_certificate_email_template" rows="6" class="large-text"><?php echo esc_textarea($certificate_email_template); ?></textarea>
                            <p class="description"><?php esc_html_e('Template email bukti pendaftaran. Link akan ditambahkan otomatis.', 'ppdb-form'); ?></p>
                            <div style="margin-top:8px; padding:10px; background:#f0f9ff; border-radius:6px;">
                                <strong><?php esc_html_e('Placeholder Tersedia:', 'ppdb-form'); ?></strong><br/>
                                <code>{nama_lengkap}</code> <code>{reg_number}</code> <code>{email}</code> <code>{jurusan}</code> <code>{institution_name}</code> <code>{current_date}</code>
                            </div>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
  }

  private static function get_default_email_template(): string
  {
    return __('Pendaftaran baru telah diterima:

Nama: {nama_lengkap}
Email: {email}
Telepon: {nomor_telepon}
Jurusan: {jurusan}

Data lengkap dapat dilihat di admin dashboard.', 'ppdb-form');
  }

  private static function get_default_user_email_template(): string
  {
    return __('Halo {nama_lengkap},

Terima kasih telah mendaftar. Pendaftaran Anda telah kami terima dan sedang diproses.

Data pendaftaran:
- Nama: {nama_lengkap}
- Email: {email}
- Jurusan: {jurusan}

Kami akan menghubungi Anda jika ada informasi lebih lanjut.

Salam,
Tim Penerimaan', 'ppdb-form');
  }
}
