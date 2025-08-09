<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Frontend
{
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

    $success_message = '';
    if (isset($_GET['ppdb_success']) && (int) $_GET['ppdb_success'] === $form_id) {
      $success_message = $form->success_message ?: __('Terima kasih, pendaftaran Anda berhasil.', 'ppdb-form');
    }

    $fields_config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
    $registry = PPDB_Form_Plugin::get_field_registry();

    ob_start();
    echo '<div class="ppdb-form">';
    if ($success_message !== '') {
      echo '<div class="ppdb-alert success">' . esc_html($success_message) . '</div>';
    }
    echo '<form method="post" class="ppdb-grid">';
    wp_nonce_field('ppdb_submit_form_' . $form_id, 'ppdb_form_nonce');
    echo '<input type="hidden" name="ppdb_form_id" value="' . (int) $form_id . '">';
    foreach ($registry as $key => $meta) {
      $cfg = $fields_config[$key] ?? ['enabled' => 0, 'required' => 0];
      if ((int) $cfg['enabled'] !== 1) {
        continue;
      }
      $required_attr = (int) $cfg['required'] === 1 ? 'required' : '';
      echo '<label>' . esc_html($meta['label']) . self::render_input($key, $meta['type'], $required_attr) . '</label>';
    }
    echo '<p><button type="submit" class="ppdb-btn">' . esc_html__('Kirim Pendaftaran', 'ppdb-form') . '</button></p>';
    echo '</form></div>';
    return ob_get_clean();
  }

  private static function render_input(string $key, string $type, string $required_attr): string
  {
    $h = '';
    switch ($key) {
      case 'jenis_kelamin':
        $h = self::select($key, ['Laki-Laki', 'Perempuan'], $required_attr);
        break;
      case 'agama':
        $h = self::select($key, ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'], $required_attr);
        break;
      case 'kewarganegaraan':
        $h = self::select($key, ['WNI', 'WNA'], $required_attr);
        break;
      case 'golongan_darah':
        $h = self::select($key, ['A', 'B', 'AB', 'O'], $required_attr);
        break;
      case 'pendidikan_ayah':
      case 'pendidikan_ibu':
        $h = self::select($key, ['SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'S1', 'S2', 'S3'], $required_attr);
        break;
      case 'penghasilan_ayah':
      case 'penghasilan_ibu':
        $h = self::select($key, ['< 1 Juta', '1 - 3 Juta', '3 - 5 Juta', '5 - 10 Juta', '> 10 Juta'], $required_attr);
        break;
      case 'jurusan':
      case 'jurusan_pilihan_2':
        global $wpdb;
        $rows = $wpdb->get_results('SELECT name FROM ' . self::get_departments_table() . ' WHERE is_active = 1 ORDER BY name ASC');
        $opts = array_map(static fn($r) => (string) $r->name, $rows ?: []);
        $h = self::select($key, $opts, $required_attr);
        break;
      default:
        if ($type === 'textarea') {
          $h = '<textarea name="' . esc_attr($key) . '" rows="3" ' . $required_attr . '></textarea>';
        } else {
          $input_type = in_array($type, ['text', 'email', 'date', 'number', 'tel'], true) ? $type : 'text';
          if (in_array($key, ['nomor_telepon', 'telepon_ayah', 'telepon_ibu', 'telepon_wali'], true)) {
            $input_type = 'tel';
          }
          $h = '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($key) . '" ' . $required_attr . ' />';
        }
    }
    return $h;
  }

  private static function select(string $name, array $options, string $required_attr): string
  {
    $html = '<select name="' . esc_attr($name) . '" ' . $required_attr . '><option value="">' . esc_html__('Pilih', 'ppdb-form') . '</option>';
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

    global $wpdb;
    $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d AND is_active = 1', $form_id));
    if (!$form) {
      return;
    }

    $fields_config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
    $registry = PPDB_Form_Plugin::get_field_registry();
    $errors = [];
    $data = [];
    foreach ($registry as $key => $meta) {
      $cfg = $fields_config[$key] ?? ['enabled' => 0, 'required' => 0];
      if ((int) $cfg['enabled'] !== 1) {
        continue;
      }
      $is_required = (int) $cfg['required'] === 1;
      $value = isset($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';
      $value = self::sanitize_value($meta['type'], $value);
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
    $redirect = add_query_arg('ppdb_success', $form_id, wp_get_referer() ?: home_url('/'));
    wp_safe_redirect($redirect);
    exit;
  }

  private static function sanitize_value(string $type, string $value): string
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
        return sanitize_text_field($value);
    }
  }
}
