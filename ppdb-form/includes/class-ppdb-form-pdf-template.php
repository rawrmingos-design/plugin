<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * PDF Template Manager for PPDB Form
 * Handles PDF template configuration, storage, and retrieval
 */
class PPDB_Form_PDF_Template
{
  /**
   * Get active PDF template configuration
   */
  public static function get_active_template(): array
  {
    global $wpdb;
    $table = $wpdb->prefix . 'ppdb_pdf_templates';

    $template = $wpdb->get_row(
      "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id DESC LIMIT 1"
    );

    if (!$template) {
      // Fallback to default template
      $template = $wpdb->get_row(
        "SELECT * FROM {$table} WHERE preset_type = 'default' ORDER BY id ASC LIMIT 1"
      );
    }

    if (!$template) {
      // Last resort: return built-in default config
      return self::get_default_config();
    }

    $config = json_decode($template->config_data, true) ?: [];
    $config['template_id'] = $template->id;
    $config['template_name'] = $template->name;
    $config['preset_type'] = $template->preset_type;

    return $config;
  }

  /**
   * Save template configuration
   */
  public static function save_template_config(array $config, int $template_id = 0): int
  {
    global $wpdb;
    $table = $wpdb->prefix . 'ppdb_pdf_templates';

    $data = [
      'name' => sanitize_text_field($config['name'] ?? 'Custom Template'),
      'preset_type' => sanitize_text_field($config['preset_type'] ?? 'custom'),
      'config_data' => wp_json_encode($config),
      'is_active' => (int) ($config['is_active'] ?? 0),
      'updated_at' => current_time('mysql')
    ];

    if ($template_id > 0) {
      // Update existing template
      $wpdb->update($table, $data, ['id' => $template_id], ['%s', '%s', '%s', '%d', '%s'], ['%d']);
      $result_id = $template_id;
    } else {
      // Create new template
      $data['created_at'] = current_time('mysql');
      $wpdb->insert($table, $data, ['%s', '%s', '%s', '%d', '%s', '%s']);
      $result_id = (int) $wpdb->insert_id;
    }

    // If this template is set as active, deactivate others
    if (!empty($config['is_active'])) {
      $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET is_active = 0 WHERE id != %d",
        $result_id
      ));
    }

    return $result_id;
  }

  /**
   * Get all available templates
   */
  public static function get_all_templates(): array
  {
    global $wpdb;
    $table = $wpdb->prefix . 'ppdb_pdf_templates';

    $templates = $wpdb->get_results(
      "SELECT * FROM {$table} ORDER BY is_active DESC, preset_type ASC, created_at DESC"
    );

    $result = [];
    foreach ($templates as $template) {
      $config = json_decode($template->config_data, true) ?: [];
      $result[] = [
        'id' => $template->id,
        'name' => $template->name,
        'preset_type' => $template->preset_type,
        'is_active' => (bool) $template->is_active,
        'config' => $config,
        'created_at' => $template->created_at,
        'updated_at' => $template->updated_at
      ];
    }

    return $result;
  }

  /**
   * Get available preset types
   */
  public static function get_available_presets(): array
  {
    return [
      'default' => [
        'name' => 'Default',
        'description' => 'Clean & professional untuk semua institusi',
        'preview_image' => 'default-preview.png',
        'colors' => ['primary' => '#3b82f6', 'secondary' => '#64748b', 'text' => '#1f2937'],
        'layout' => 'standard',
        'header_style' => 'logo_center',
        'qr_position' => 'bottom_right'
      ],
      'modern' => [
        'name' => 'Modern',
        'description' => 'Minimalist & contemporary design',
        'preview_image' => 'modern-preview.png',
        'colors' => ['primary' => '#10b981', 'secondary' => '#374151', 'text' => '#111827'],
        'layout' => 'minimal',
        'header_style' => 'logo_left',
        'qr_position' => 'top_right'
      ],
      'classic' => [
        'name' => 'Classic',
        'description' => 'Traditional & formal untuk institusi konservatif',
        'preview_image' => 'classic-preview.png',
        'colors' => ['primary' => '#dc2626', 'secondary' => '#1f2937', 'text' => '#000000'],
        'layout' => 'formal',
        'header_style' => 'full_header',
        'qr_position' => 'bottom_center'
      ],
      'academic' => [
        'name' => 'Academic',
        'description' => 'University-style dengan elemen akademik',
        'preview_image' => 'academic-preview.png',
        'colors' => ['primary' => '#7c3aed', 'secondary' => '#4b5563', 'text' => '#1f2937'],
        'layout' => 'academic',
        'header_style' => 'logo_center_seal',
        'qr_position' => 'bottom_left'
      ]
    ];
  }

  /**
   * Activate a template by ID
   */
  public static function activate_template(int $template_id): bool
  {
    global $wpdb;
    $table = $wpdb->prefix . 'ppdb_pdf_templates';

    // Deactivate all templates first
    $wpdb->update($table, ['is_active' => 0], [], ['%d']);

    // Activate the selected template
    $result = $wpdb->update(
      $table,
      ['is_active' => 1, 'updated_at' => current_time('mysql')],
      ['id' => $template_id],
      ['%d', '%s'],
      ['%d']
    );

    return $result !== false;
  }

  /**
   * Delete a template by ID
   */
  public static function delete_template(int $template_id): bool
  {
    global $wpdb;
    $table = $wpdb->prefix . 'ppdb_pdf_templates';

    // Don't allow deleting the active template
    $active_template = $wpdb->get_var(
      "SELECT id FROM {$table} WHERE is_active = 1"
    );

    if ((int) $active_template === $template_id) {
      return false;
    }

    $result = $wpdb->delete($table, ['id' => $template_id], ['%d']);

    return $result !== false;
  }

  /**
   * Generate CSS variables from template config
   */
  public static function generate_css_variables(array $config): string
  {
    $colors = $config['colors'] ?? [];
    $primary = $colors['primary'] ?? '#3b82f6';
    $secondary = $colors['secondary'] ?? '#64748b';
    $text = $colors['text'] ?? '#1f2937';

    return "
      :root {
        --ppdb-pdf-primary: {$primary};
        --ppdb-pdf-secondary: {$secondary};
        --ppdb-pdf-text: {$text};
        --ppdb-pdf-layout: " . ($config['layout'] ?? 'standard') . ';
        --ppdb-pdf-header: ' . ($config['header_style'] ?? 'logo_center') . ';
        --ppdb-pdf-qr-position: ' . ($config['qr_position'] ?? 'bottom_right') . ';
      }
    ';
  }

  /**
   * Get default template configuration
   */
  public static function get_default_config(): array
  {
    return [
      'template_id' => 0,
      'template_name' => 'Built-in Default',
      'preset_type' => 'default',
      'colors' => [
        'primary' => '#3b82f6',
        'secondary' => '#64748b',
        'text' => '#1f2937'
      ],
      'layout' => 'standard',
      'header_style' => 'logo_center',
      'qr_position' => 'bottom_right',
      'fields' => ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan'],
      'institution' => [
        'name' => get_bloginfo('name'),
        'logo' => '',
        'address' => '',
        'contact' => get_option('admin_email'),
        'tagline' => ''
      ],
      'custom_footer' => 'Dokumen ini adalah bukti pendaftaran resmi yang sah.'
    ];
  }

  /**
   * Get template by preset type
   */
  public static function get_template_by_preset(string $preset_type): ?array
  {
    global $wpdb;
    $table = $wpdb->prefix . 'ppdb_pdf_templates';

    $template = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$table} WHERE preset_type = %s ORDER BY id ASC LIMIT 1",
      $preset_type
    ));

    if (!$template) {
      return null;
    }

    $config = json_decode($template->config_data, true) ?: [];
    $config['template_id'] = $template->id;
    $config['template_name'] = $template->name;
    $config['preset_type'] = $template->preset_type;

    return $config;
  }

  /**
   * Create template from preset
   */
  public static function create_from_preset(string $preset_type, array $overrides = []): int
  {
    $presets = self::get_available_presets();

    if (!isset($presets[$preset_type])) {
      return 0;
    }

    $preset = $presets[$preset_type];
    $config = array_merge([
      'name' => $preset['name'] . ' Template',
      'preset_type' => $preset_type,
      'colors' => $preset['colors'],
      'layout' => $preset['layout'],
      'header_style' => $preset['header_style'],
      'qr_position' => $preset['qr_position'],
      'fields' => ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan'],
      'institution' => [
        'name' => get_bloginfo('name'),
        'logo' => '',
        'address' => '',
        'contact' => get_option('admin_email'),
        'tagline' => ''
      ],
      'custom_footer' => '',
      'is_active' => 0
    ], $overrides);

    return self::save_template_config($config);
  }

  /**
   * Get available field options for template configuration
   */
  public static function get_available_fields(): array
  {
    $registry = PPDB_Form_Plugin::get_field_registry();
    $available_fields = [];

    foreach ($registry as $key => $meta) {
      $available_fields[$key] = [
        'key' => $key,
        'label' => $meta['label'],
        'type' => $meta['type'],
        'category' => self::get_field_category($key)
      ];
    }

    return $available_fields;
  }

  /**
   * Categorize fields for better organization
   */
  private static function get_field_category(string $field_key): string
  {
    $personal_fields = ['nama_lengkap', 'tanggal_lahir', 'jenis_kelamin', 'alamat'];
    $contact_fields = ['email', 'nomor_telepon'];
    $academic_fields = ['jurusan', 'asal_sekolah', 'tahun_lulus'];
    $document_fields = ['file_', 'dokumen_', 'kk', 'akta', 'ijazah', 'rapor'];

    foreach ($document_fields as $prefix) {
      if (strpos($field_key, $prefix) === 0) {
        return 'document';
      }
    }

    if (in_array($field_key, $personal_fields)) {
      return 'personal';
    }

    if (in_array($field_key, $contact_fields)) {
      return 'contact';
    }

    if (in_array($field_key, $academic_fields)) {
      return 'academic';
    }

    return 'other';
  }
}
