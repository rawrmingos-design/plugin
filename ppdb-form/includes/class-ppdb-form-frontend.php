<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Frontend
{
  /**
   * Holds inline success HTML for current request to suppress re-rendering the form.
   * @var array{form_id:int, html:string}|null
   */
  private static $inline_success = null;

  private static function get_forms_table(): string
  {
    global $wpdb;
    return $wpdb->prefix . 'ppdb_forms';
  }

  private static function get_submissions_table(): string
  {
    global $wpdb;
    return $wpdb->prefix . 'ppdb_submissions';
  }

  private static function get_departments_table(): string
  {
    global $wpdb;
    return $wpdb->prefix . 'ppdb_departments';
  }

  private static function get_departments_direct(): array
  {
    global $wpdb;
    $rows = $wpdb->get_results('SELECT name FROM ' . self::get_departments_table() . ' WHERE is_active = 1 ORDER BY name ASC');
    return array_map(static fn($r) => (string) $r->name, $rows ?: []);
  }

  public static function render_shortcode(array $atts, ?string $content = null, string $tag = ''): string
  {
    $atts = shortcode_atts(['id' => 0], $atts, $tag);
    $form_id = (int) $atts['id'];
    if ($form_id <= 0) {
      return '<div class="ppdb-form">' . esc_html__('Form tidak ditemukan.', 'ppdb-form') . '</div>';
    }
    global $wpdb;
    $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d AND is_active = 1', $form_id));
    if (!$form) {
      return '<div class="ppdb-form">' . esc_html__('Form tidak aktif atau tidak ditemukan.', 'ppdb-form') . '</div>';
    }

    // If inline success exists for this form (same request), show it and hide the form
    if (is_array(self::$inline_success) && (int) (self::$inline_success['form_id'] ?? 0) === $form_id) {
      return (string) self::$inline_success['html'];
    }

    // Success block via PRG with secure hash (after redirect)
    $success_block = '';
    $behavior = (string) get_option('ppdb_submit_behavior', 'redirect');
    if (isset($_GET['ppdb_thanks'], $_GET['sid'], $_GET['k'])) {
      $sid = (int) $_GET['sid'];
      $k = (string) $_GET['k'];
      $expected = wp_hash($sid . '|' . wp_salt('auth'));
      if (hash_equals($expected, $k)) {
        global $wpdb;
        $submission = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_submissions_table() . ' WHERE id = %d AND form_id = %d', $sid, $form_id));
        if ($submission) {
          $data = json_decode((string) $submission->submission_data, true) ?: [];
          $success_block = self::render_success_block($form, (int) $submission->id, $data);
        }
      }
    }

    // Check if multi-step is enabled
    $steps_config = $form->steps_config ? json_decode($form->steps_config, true) : null;
    $is_multistep = !empty($steps_config['enabled']);

    if ($success_block !== '') {
      return $success_block;
    }

    if ($is_multistep) {
      return self::render_multistep_form($form, $steps_config);
    } else {
      return self::render_single_step_form($form);
    }
  }

  private static function render_success_block($form, int $submission_id, array $data): string
  {
    // Debug: Log success page rendering
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log("PPDB Success Page: Rendering for submission ID {$submission_id}");
    }

    $template = (string) get_option('ppdb_success_template', __('<strong>Terima kasih {nama_lengkap}!</strong> Pendaftaran Anda berhasil dikirim. Nomor pendaftaran: {submission_id}.', 'ppdb-form'));
    // Replace placeholders
    $replacements = ['{submission_id}' => (string) $submission_id];
    foreach ($data as $k => $v) {
      if (is_string($v)) {
        $replacements['{' . $k . '}'] = $v;
      }
    }
    $html_msg = strtr($template, $replacements);

    // Generate registration number
    $prefix = get_option('ppdb_reg_number_prefix', 'REG');
    $year = date('Y');
    $padded_id = str_pad((string) $submission_id, 6, '0', STR_PAD_LEFT);
    $reg_number = $prefix . $year . $padded_id;

    $show_summary = (bool) get_option('ppdb_success_show_summary', true);
    ob_start();
    echo '<div class="ppdb-form ppdb-success-container">';

    // Modern Success Header with Animation
    echo '<div class="ppdb-success-header">';
    echo '<div class="ppdb-success-icon">';
    echo '<div class="ppdb-checkmark">';
    echo '<svg width="60" height="60" viewBox="0 0 60 60">';
    echo '<circle cx="30" cy="30" r="28" fill="none" stroke="#10b981" stroke-width="4" stroke-dasharray="175" stroke-dashoffset="175" class="ppdb-circle-animation"/>';
    echo '<polyline points="15,30 25,40 45,20" fill="none" stroke="#10b981" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" stroke-dasharray="40" stroke-dashoffset="40" class="ppdb-check-animation"/>';
    echo '</svg>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ppdb-success-content">';
    echo '<h2 class="ppdb-success-title">🎉 Pendaftaran Berhasil!</h2>';
    echo '<div class="ppdb-success-message">' . wp_kses_post($html_msg) . '</div>';
    echo '<div class="ppdb-reg-number">';
    echo '<span class="ppdb-reg-label">Nomor Registrasi:</span>';
    echo '<span class="ppdb-reg-value">' . esc_html($reg_number) . '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    if ($show_summary) {
      $registry = PPDB_Form_Plugin::get_field_registry();
      echo '<div class="ppdb-success-card">';
      echo '<div class="ppdb-card-header">';
      echo '<h3><span class="ppdb-icon">📋</span> Ringkasan Pendaftaran</h3>';
      echo '</div>';
      echo '<div class="ppdb-card-content">';

      $key_fields = ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan'];
      foreach ($key_fields as $key) {
        if (isset($data[$key])) {
          $label = $registry[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
          $value = is_array($data[$key]) ? implode(', ', array_map('sanitize_text_field', $data[$key])) : (string) $data[$key];
          echo '<div class="ppdb-summary-item">';
          echo '<span class="ppdb-summary-label">' . esc_html($label) . ':</span>';
          echo '<span class="ppdb-summary-value">' . esc_html($value) . '</span>';
          echo '</div>';
        }
      }
      echo '</div>';
      echo '</div>';
    }

    // Add certificate/registration proof section
    if (class_exists('PPDB_Form_Certificate')) {
      $certificate_url = PPDB_Form_Certificate::get_certificate_url($submission_id);
      echo '<div class="ppdb-success-card">';
      echo '<div class="ppdb-card-header">';
      echo '<h3><span class="ppdb-icon">🎓</span> Bukti Pendaftaran</h3>';
      echo '<p>' . esc_html__('Silakan unduh atau cetak bukti pendaftaran Anda untuk arsip resmi.', 'ppdb-form') . '</p>';
      echo '</div>';
      echo '<div class="ppdb-card-content">';
      echo '<div class="ppdb-action-buttons">';
      echo '<a href="' . esc_url($certificate_url) . '" target="_blank" class="ppdb-action-btn ppdb-btn-view">';
      echo '<span class="ppdb-btn-icon">👁️</span>';
      echo '<span class="ppdb-btn-text">' . esc_html__('Lihat Bukti', 'ppdb-form') . '</span>';
      echo '</a>';
      echo '<a href="' . esc_url($certificate_url) . '" target="_blank" class="ppdb-action-btn ppdb-btn-print" onclick="setTimeout(() => window.print(), 500);">';
      echo '<span class="ppdb-btn-icon">🖨️</span>';
      echo '<span class="ppdb-btn-text">' . esc_html__('Cetak', 'ppdb-form') . '</span>';
      echo '</a>';

      // Add PDF download button if PDF generator is available
      if (class_exists('PPDB_Form_PDF_Generator')) {
        $pdf_url = add_query_arg(['ppdb_pdf' => 1, 'sid' => $submission_id, 'hash' => wp_hash($submission_id . '|' . wp_salt('auth'))], home_url('/'));
        echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="ppdb-action-btn ppdb-btn-pdf">';
        echo '<span class="ppdb-btn-icon">📄</span>';
        echo '<span class="ppdb-btn-text">' . esc_html__('Download PDF', 'ppdb-form') . '</span>';
        echo '</a>';
      }

      echo '</div>';
      echo '<div class="ppdb-note">';
      echo '<span class="ppdb-note-icon">💡</span>';
      echo '<span>' . esc_html__('Simpan bukti pendaftaran ini sebagai tanda bukti resmi pendaftaran Anda.', 'ppdb-form') . '</span>';
      echo '</div>';
      echo '</div>';
      echo '</div>';
    }

    // Next Steps Card
    echo '<div class="ppdb-success-card">';
    echo '<div class="ppdb-card-header">';
    echo '<h3><span class="ppdb-icon">🚀</span> Langkah Selanjutnya</h3>';
    echo '</div>';
    echo '<div class="ppdb-card-content">';
    echo '<div class="ppdb-step-item">';
    echo '<div class="ppdb-step-number">1</div>';
    echo '<div class="ppdb-step-text">';
    echo '<strong>Simpan bukti pendaftaran</strong><br>';
    echo '<small>Download dan simpan bukti pendaftaran di tempat aman</small>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ppdb-step-item">';
    echo '<div class="ppdb-step-number">2</div>';
    echo '<div class="ppdb-step-text">';
    echo '<strong>Catat nomor registrasi</strong><br>';
    echo '<small>Gunakan nomor <strong>' . esc_html($reg_number) . '</strong> untuk keperluan selanjutnya</small>';
    echo '</div>';
    echo '</div>';
    echo '<div class="ppdb-step-item">';
    echo '<div class="ppdb-step-number">3</div>';
    echo '<div class="ppdb-step-text">';
    echo '<strong>Tunggu konfirmasi</strong><br>';
    echo '<small>Tim kami akan menghubungi Anda untuk informasi lebih lanjut</small>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Contact Info Card
    echo '<div class="ppdb-success-card">';
    echo '<div class="ppdb-card-header">';
    echo '<h3><span class="ppdb-icon">📞</span> Butuh Bantuan?</h3>';
    echo '</div>';
    echo '<div class="ppdb-card-content">';
    echo '<p>Jika Anda memiliki pertanyaan atau memerlukan bantuan, jangan ragu untuk menghubungi kami:</p>';
    echo '<div class="ppdb-contact-info">';
    echo '<span class="ppdb-contact-item">';
    echo '<strong>Email:</strong> ' . esc_html(get_option('ppdb_form_email_admin', get_option('admin_email')));
    echo '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '</div>';
    return ob_get_clean();
  }

  private static function render_single_step_form($form): string
  {
    $fields_config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
    $registry = PPDB_Form_Plugin::get_field_registry();

    ob_start();
    echo '<div class="ppdb-form">';
    echo '<h2>' . esc_html($form->name) . '</h2>';
    if ($form->description) {
      echo '<p>' . esc_html($form->description) . '</p>';
    }
    echo '<form method="post" enctype="multipart/form-data" class="ppdb-grid">';
    wp_nonce_field('ppdb_submit_form_' . $form->id, 'ppdb_form_nonce');
    echo '<input type="hidden" name="ppdb_form_id" value="' . (int) $form->id . '">';
    echo '<input type="hidden" name="ppdb_current_url" value="' . esc_attr(get_permalink() ?: home_url(sanitize_text_field($_SERVER['REQUEST_URI'] ?? '/'))) . '">';
    // Honeypot field (invisible via CSS)
    echo '<div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">'
      . '<label>Website<input type="text" name="website" autocomplete="off" tabindex="-1"></label>'
      . '</div>';

    foreach ($registry as $key => $meta) {
      // For single-step forms honor admin config; new doc fields appear enabled only if admin enables them
      $cfg = $fields_config[$key] ?? ['enabled' => 0, 'required' => 0];
      if ((int) $cfg['enabled'] !== 1) {
        continue;
      }
      $required_attr = (int) $cfg['required'] === 1 ? 'required' : '';
      $field_id = 'ppdb_field_' . $key;
      $error_id = 'ppdb_error_' . $key;

      echo '<div class="ppdb-field-group" data-field="' . esc_attr($key) . '">';
      echo '<label for="' . esc_attr($field_id) . '" class="ppdb-label">';
      echo esc_html($meta['label']);
      if ((int) $cfg['required'] === 1) {
        echo ' <span class="ppdb-required" aria-label="' . esc_attr__('Wajib diisi', 'ppdb-form') . '">*</span>';
      }
      echo '</label>';
      echo self::render_input($key, $meta['type'], $required_attr, $field_id, $error_id);
      echo '<div class="ppdb-field-error" id="' . esc_attr($error_id) . '" role="alert" aria-live="polite"></div>';
      echo '</div>';
    }

    // reCAPTCHA
    $site_key = (string) get_option('ppdb_recaptcha_site_key', '');
    if ($site_key !== '') {
      echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
    }

    echo '<div class="ppdb-form-actions">';
    echo '<button type="submit" class="ppdb-btn">' . esc_html__('Kirim Pendaftaran', 'ppdb-form') . '</button>';
    echo '</div>';
    echo '</form></div>';
    return ob_get_clean();
  }

  private static function render_multistep_form($form, $steps_config): string
  {
    $steps = $steps_config['steps'] ?? [];
    $total_steps = count($steps);
    $fields_config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
    $registry = PPDB_Form_Plugin::get_field_registry();

    ob_start();
    echo '<div class="ppdb-form ppdb-multistep" data-multi-step="true" data-total-steps="' . esc_attr($total_steps) . '">';
    echo '<h2>' . esc_html($form->name) . '</h2>';
    if ($form->description) {
      echo '<p>' . esc_html($form->description) . '</p>';
    }

    // Progress indicator
    echo '<div class="ppdb-progress-container">';
    echo '<div class="ppdb-progress">';
    echo '<div class="ppdb-progress-bar" style="width: ' . (100 / $total_steps) . '%"></div>';
    echo '</div>';
    echo '<ul class="ppdb-steps">';
    foreach ($steps as $index => $step) {
      $step_number = $index + 1;
      $is_active = $index === 0;
      echo '<li class="ppdb-step' . ($is_active ? ' active' : '') . '">';
      echo '<div class="ppdb-step-number">' . $step_number . '</div>';
      echo '<div class="ppdb-step-title">' . esc_html($step['title']) . '</div>';
      echo '</li>';
    }
    echo '</ul>';
    echo '</div>';

    echo '<form method="post" enctype="multipart/form-data" class="ppdb-multistep-form">';
    wp_nonce_field('ppdb_submit_form_' . $form->id, 'ppdb_form_nonce');
    echo '<input type="hidden" name="ppdb_form_id" value="' . (int) $form->id . '">';
    echo '<input type="hidden" name="ppdb_current_url" value="' . esc_attr(get_permalink() ?: home_url(sanitize_text_field($_SERVER['REQUEST_URI'] ?? '/'))) . '">';
    echo '<input type="hidden" name="ppdb_current_step" value="1" />';
    // Honeypot field
    echo '<div style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">'
      . '<label>Website<input type="text" name="website" autocomplete="off" tabindex="-1"></label>'
      . '</div>';

    // Render each step
    foreach ($steps as $step_index => $step) {
      $step_number = $step_index + 1;
      $is_active = $step_index === 0;

      echo '<div class="ppdb-step-content" data-step="' . esc_attr($step_number) . '"' . ($is_active ? '' : ' style="display:none"') . '>';
      echo '<div class="ppdb-step-header">';
      echo '<h3>' . esc_html($step['title']) . '</h3>';
      if (!empty($step['description'])) {
        echo '<p class="ppdb-step-description">' . esc_html($step['description']) . '</p>';
      }
      echo '</div>';

      echo '<div class="ppdb-grid">';
      // Determine fields for the step. If none defined (legacy config),
      // and this step looks like a document upload step, auto-populate
      // with all 'Dokumen' (type=file) fields from registry.
      $raw_fields = isset($step['fields']) && is_array($step['fields']) ? $step['fields'] : [];
      // Filter hanya field yang valid dan ENABLED oleh admin
      $step_fields = array_values(array_filter($raw_fields, static function ($fk) use ($registry, $fields_config) {
        return isset($registry[$fk]) && isset($fields_config[$fk]) && (int) ($fields_config[$fk]['enabled'] ?? 0) === 1;
      }));
      $title = strtolower((string) ($step['title'] ?? ''));
      $desc = strtolower((string) ($step['description'] ?? ''));
      $is_doc_step = (strpos($title, 'dokumen') !== false || strpos($title, 'rapor') !== false || strpos($desc, 'rapor') !== false);
      if ($is_doc_step && empty($step_fields)) {
        // Admin tidak menentukan fields atau semua dimatikan: fallback dokumen ENABLED saja
        foreach ($registry as $rk => $rmeta) {
          if (($rmeta['type'] ?? '') === 'file' && (int) ($fields_config[$rk]['enabled'] ?? 0) === 1) {
            $step_fields[] = $rk;
          }
        }
      }
      foreach ($step_fields as $field_key) {
        $meta = $registry[$field_key] ?? null;
        if (!$meta)
          continue;

        // Default to enabled for fields declared in steps (backward-compatible for existing forms)
        // Selalu hormati konfigurasi enabled dari admin panel
        $cfg = $fields_config[$field_key] ?? ['enabled' => 0, 'required' => 0];
        if ((int) $cfg['enabled'] !== 1) {
          continue;
        }

        $required_attr = (int) $cfg['required'] === 1 ? 'required' : '';
        $field_id = 'ppdb_field_' . $field_key;
        $error_id = 'ppdb_error_' . $field_key;

        echo '<div class="ppdb-field-group" data-field="' . esc_attr($field_key) . '">';
        echo '<label for="' . esc_attr($field_id) . '" class="ppdb-label">';
        echo esc_html($meta['label']);
        if ((int) $cfg['required'] === 1) {
          echo ' <span class="ppdb-required" aria-label="' . esc_attr__('Wajib diisi', 'ppdb-form') . '">*</span>';
        }
        echo '</label>';
        echo self::render_input($field_key, $meta['type'], $required_attr, $field_id, $error_id);
        echo '<div class="ppdb-field-error" id="' . esc_attr($error_id) . '" role="alert" aria-live="polite"></div>';
        echo '</div>';
      }
      echo '</div>';
      echo '</div>';
    }

    // reCAPTCHA (only on last step)
    $site_key = (string) get_option('ppdb_recaptcha_site_key', '');
    if ($site_key !== '') {
      echo '<div class="ppdb-recaptcha-container" style="display:none">';
      echo '<div class="g-recaptcha" data-sitekey="' . esc_attr($site_key) . '"></div>';
      echo '</div>';
    }

    // Navigation buttons
    echo '<div class="ppdb-form-navigation">';
    echo '<button type="button" class="ppdb-btn ppdb-btn-secondary ppdb-btn-prev" style="display:none">' . esc_html__('Sebelumnya', 'ppdb-form') . '</button>';
    echo '<button type="button" class="ppdb-btn ppdb-btn-next">' . esc_html__('Selanjutnya', 'ppdb-form') . '</button>';
    echo '<button type="submit" class="ppdb-btn ppdb-btn-submit" style="display:none">' . esc_html__('Kirim Pendaftaran', 'ppdb-form') . '</button>';
    echo '</div>';

    echo '</form></div>';
    return ob_get_clean();
  }

  private static function render_input(string $key, string $type, string $required_attr, string $field_id, string $error_id): string
  {
    $h = '';
    $common_attrs = 'id="' . esc_attr($field_id) . '" name="' . esc_attr($key) . '" ' . $required_attr . ' aria-describedby="' . esc_attr($error_id) . '" class="ppdb-input"';

    switch ($key) {
      case 'jenis_kelamin':
        $h = self::select($key, ['Laki-Laki', 'Perempuan'], $required_attr, $field_id, $error_id);
        break;
      case 'agama':
        $h = self::select($key, ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'], $required_attr, $field_id, $error_id);
        break;
      case 'kewarganegaraan':
        $h = self::select($key, ['WNI', 'WNA'], $required_attr, $field_id, $error_id);
        break;
      case 'golongan_darah':
        $h = self::select($key, ['A', 'B', 'AB', 'O'], $required_attr, $field_id, $error_id);
        break;
      case 'pendidikan_ayah':
      case 'pendidikan_ibu':
        $h = self::select($key, ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3'], $required_attr, $field_id, $error_id);
        break;
      case 'penghasilan_ayah':
      case 'penghasilan_ibu':
        $h = self::select($key, ['< 1 Juta', '1 - 3 Juta', '3 - 5 Juta', '5 - 10 Juta', '> 10 Juta'], $required_attr, $field_id, $error_id);
        break;
      case 'jurusan':
      case 'jurusan_pilihan_2':
        $opts = self::get_departments_direct();
        $h = self::select($key, $opts, $required_attr, $field_id, $error_id);
        break;
      default:
        // File upload fields (documents)
        if ($type === 'file') {
          // Accept common document/image formats
          $accept = 'accept=".pdf,.jpg,.jpeg,.png,.heic,.heif,.webp"';
          $h = '<input type="file" ' . $common_attrs . ' ' . $accept . ' />';
          break;
        }
        if ($type === 'textarea') {
          $h = '<textarea ' . $common_attrs . ' rows="3" data-validate="' . esc_attr($type) . '"></textarea>';
        } else {
          $input_type = in_array($type, ['text', 'email', 'date', 'number', 'tel'], true) ? $type : 'text';
          if (in_array($key, ['nomor_telepon', 'telepon_ayah', 'telepon_ibu', 'telepon_wali'], true)) {
            $input_type = 'tel';
          }

          // Add validation patterns
          $pattern = '';
          $placeholder = '';
          if ($key === 'nisn') {
            $pattern = 'pattern="[0-9]{10}"';
            $placeholder = 'placeholder="' . esc_attr__('10 digit angka', 'ppdb-form') . '"';
          } elseif (in_array($key, ['nik', 'no_kk'], true)) {
            $pattern = 'pattern="[0-9]{16}"';
            $placeholder = 'placeholder="' . esc_attr__('16 digit angka', 'ppdb-form') . '"';
          } elseif (in_array($key, ['nomor_telepon', 'telepon_ayah', 'telepon_ibu', 'telepon_wali'], true)) {
            $pattern = 'pattern="[+]?[0-9]{8,15}"';
            $placeholder = 'placeholder="' . esc_attr__('Contoh: 08123456789', 'ppdb-form') . '"';
          } elseif ($key === 'email') {
            $placeholder = 'placeholder="' . esc_attr__('nama@email.com', 'ppdb-form') . '"';
          }

          $h = '<input type="' . esc_attr($input_type) . '" ' . $common_attrs . ' ' . $pattern . ' ' . $placeholder . ' data-validate="' . esc_attr($key) . '" />';
        }
    }
    return $h;
  }

  private static function select(string $name, array $options, string $required_attr, string $field_id, string $error_id): string
  {
    $html = '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($name) . '" ' . $required_attr . ' aria-describedby="' . esc_attr($error_id) . '" class="ppdb-input ppdb-select">';
    $html .= '<option value="">' . esc_html__('Pilih', 'ppdb-form') . '</option>';
    foreach ($options as $opt) {
      $html .= '<option value="' . esc_attr((string) $opt) . '">' . esc_html((string) $opt) . '</option>';
    }
    return $html . '</select>';
  }

  public static function handle_submission(): void
  {
    if (!isset($_POST['ppdb_form_id'])) {
      return;
    }
    $form_id = (int) ($_POST['ppdb_form_id'] ?? 0);
    if ($form_id <= 0) {
      return;
    }
    if (!isset($_POST['ppdb_form_nonce']) || !wp_verify_nonce((string) $_POST['ppdb_form_nonce'], 'ppdb_submit_form_' . $form_id)) {
      return;
    }
    // Honeypot check
    if (!empty($_POST['website'])) {
      return;
    }

    // Load current form early (needed for steps config and validation)
    global $wpdb;
    $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d AND is_active = 1', $form_id));
    if (!$form) {
      return;
    }

    // For multi-step forms, check if this is final submission
    $current_step = isset($_POST['ppdb_current_step']) ? (int) $_POST['ppdb_current_step'] : 1;
    $steps_config = $form->steps_config ? json_decode($form->steps_config, true) : null;
    $is_multistep = !empty($steps_config['enabled']);

    if ($is_multistep) {
      $total_steps = count($steps_config['steps'] ?? []);
      if ($current_step < $total_steps) {
        // This is not the final step, don't process submission
        return;
      }
    }
    // reCAPTCHA server-side verify if configured
    $secret = (string) get_option('ppdb_recaptcha_secret_key', '');
    if ($secret !== '') {
      $token = isset($_POST['g-recaptcha-response']) ? (string) $_POST['g-recaptcha-response'] : '';
      if ($token === '') {
        return;
      }
      $resp = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
        'timeout' => 8,
        'body' => ['secret' => $secret, 'response' => $token, 'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''],
      ]);
      if (is_wp_error($resp)) {
        return;
      }
      $body = json_decode((string) wp_remote_retrieve_body($resp), true);
      if (empty($body['success'])) {
        return;
      }
    }

    $fields_config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
    $registry = PPDB_Form_Plugin::get_field_registry();
    $errors = [];
    // Basic rate limiting per IP per minute
    $ip = sanitize_text_field((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $rate_key = 'ppdb_rate_' . md5($ip);
    $count = (int) get_transient($rate_key);
    if ($count > 20) {
      return;
    }
    set_transient($rate_key, $count + 1, MINUTE_IN_SECONDS);
    $data = [];
    foreach ($registry as $key => $meta) {
      $cfg = $fields_config[$key] ?? ['enabled' => 0, 'required' => 0];
      if ((int) $cfg['enabled'] !== 1) {
        continue;
      }
      $is_required = (int) $cfg['required'] === 1;
      // Handle file uploads for 'file' type
      if ($meta['type'] === 'file') {
        $file_url = '';
        if (isset($_FILES[$key]) && is_array($_FILES[$key]) && (int) $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
          require_once ABSPATH . 'wp-admin/includes/file.php';
          // Allowed mimes from settings
          $allowed = get_option('ppdb_upload_allowed_mimes', 'pdf,jpg,jpeg,png,heic,heif,webp,zip');
          $allowed_mimes = array_fill_keys(array_map('trim', explode(',', (string) $allowed)), true);
          $mimes = [];
          if (!empty($allowed_mimes['pdf'])) {
            $mimes['pdf'] = 'application/pdf';
          }
          if (!empty($allowed_mimes['jpg']) || !empty($allowed_mimes['jpeg'])) {
            $mimes['jpg'] = 'image/jpeg';
            $mimes['jpeg'] = 'image/jpeg';
          }
          if (!empty($allowed_mimes['png'])) {
            $mimes['png'] = 'image/png';
          }
          if (!empty($allowed_mimes['heic'])) {
            $mimes['heic'] = 'image/heic';
          }
          if (!empty($allowed_mimes['heif'])) {
            $mimes['heif'] = 'image/heif';
          }
          if (!empty($allowed_mimes['webp'])) {
            $mimes['webp'] = 'image/webp';
          }
          if (!empty($allowed_mimes['zip'])) {
            $mimes['zip'] = 'application/zip';
          }

          $overrides = ['test_form' => false, 'mimes' => $mimes];
          $uploaded = wp_handle_upload($_FILES[$key], $overrides);
          if (!isset($uploaded['error'])) {
            // Size validation (in MB)
            $max_mb = (int) get_option('ppdb_upload_max_mb', 5);
            if (!empty($_FILES[$key]['size']) && $_FILES[$key]['size'] > ($max_mb * 1024 * 1024)) {
              $file_url = '';
            } else {
              $file_url = esc_url_raw($uploaded['url']);
            }
          }
        }
        if ($is_required && $file_url === '') {
          $errors[] = $meta['label'] . ' ' . __('wajib diunggah', 'ppdb-form');
        }
        $data[$key] = $file_url;
        continue;
      }

      $value = isset($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';
      $value = self::sanitize_value($key, $meta['type'], $value);
      if ($is_required && $value === '') {
        $errors[] = $meta['label'] . ' ' . __('wajib diisi', 'ppdb-form');
      }
      $data[$key] = $value;
    }
    if (!empty($errors)) {
      add_action('the_content', function ($c) use ($errors) {
        $msg = '<div class="ppdb-alert error">' . implode('<br>', array_map('esc_html', $errors)) . '</div>';
        return $msg . $c;
      });
      return;
    }
    $wpdb->insert(self::get_submissions_table(), ['form_id' => $form_id, 'submission_data' => wp_json_encode($data), 'created_at' => current_time('mysql')], ['%d', '%s', '%s']);

    $submission_id = (int) $wpdb->insert_id;

    // Queue email notifications
    PPDB_Form_Notifications::queue_notifications($data, $form);

    // Trigger certificate email sending
    do_action('ppdb_form_submission_success', $submission_id, $data);

    $behavior = (string) get_option('ppdb_submit_behavior', 'redirect');
    if ($behavior === 'inline') {
      // Render success inline by hooking into the_content
      $success = self::render_success_block($form, $submission_id, $data);
      // Save inline success to suppress form rendering in shortcode on the same request
      self::$inline_success = ['form_id' => $form_id, 'html' => $success];
      add_action('the_content', static function ($c) use ($success) {
        return $success;
      });
      return;
    }
    // Default: PRG redirect to same page with signed params
    $sid = (int) $wpdb->insert_id;
    $k = wp_hash($sid . '|' . wp_salt('auth'));

    // Use the URL from hidden field if available, otherwise fallback
    $base = '';
    if (!empty($_POST['ppdb_current_url'])) {
      $base = esc_url_raw($_POST['ppdb_current_url']);
    }

    // Fallback: try referer, then current page, then home
    if (empty($base)) {
      $base = wp_get_referer() ?: get_permalink() ?: home_url('/');
    }

    // Remove transient step params and existing success params
    $base = remove_query_arg(['ppdb_current_step', '_wpnonce', 'ppdb_thanks', 'sid', 'k'], $base);
    $redirect = add_query_arg(['ppdb_thanks' => 1, 'sid' => $sid, 'k' => $k], $base);
    wp_safe_redirect($redirect);
    exit;
  }

  private static function sanitize_value(string $key, string $type, string $value): string
  {
    switch ($type) {
      case 'email':
        return sanitize_email($value);
      case 'textarea':
        return sanitize_textarea_field($value);
      case 'number':
        return is_numeric($value) ? (string) $value : '';
      case 'date':
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
      default:
        $v = sanitize_text_field($value);
        // Domain-specific validation
        if (in_array($key, ['nisn'], true)) {
          // NISN biasanya 10 digit
          return preg_match('/^\d{10}$/', $v) ? $v : '';
        }
        if (in_array($key, ['nik', 'no_kk'], true)) {
          // NIK/No KK 16 digit
          return preg_match('/^\d{16}$/', $v) ? $v : '';
        }
        if (in_array($key, ['nomor_telepon', 'telepon_ayah', 'telepon_ibu', 'telepon_wali'], true)) {
          // Normalisasi sederhana nomor telepon: hanya digit dan + awal
          $v = preg_replace('/[^\d+]/', '', $v);
          // Batasi panjang wajar 8-15 digit (tanpa +)
          $digits = preg_replace('/\D/', '', $v);
          if (strlen($digits) < 8 || strlen($digits) > 15) {
            return '';
          }
          return $v;
        }
        if ($key === 'tahun_lulus') {
          $year = (int) $v;
          $current = (int) wp_date('Y');
          return ($year >= 1980 && $year <= $current + 1) ? (string) $year : '';
        }
        return $v;
    }
  }
}
