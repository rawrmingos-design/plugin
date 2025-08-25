<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Installer
{
  private const DB_VERSION = '1.3.0';

  public static function activate(): void
  {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $table_forms = $wpdb->prefix . 'ppdb_forms';
    $table_submissions = $wpdb->prefix . 'ppdb_submissions';
    $table_departments = $wpdb->prefix . 'ppdb_departments';
    $table_pdf_templates = $wpdb->prefix . 'ppdb_pdf_templates';

    $sql_forms = "CREATE TABLE {$table_forms} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text NULL,
            success_message text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            fields_json longtext NULL,
            steps_config longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id)
        ) {$charset_collate};";

    $sql_submissions = "CREATE TABLE {$table_submissions} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id mediumint(9) NOT NULL,
            submission_data longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY form_id (form_id)
        ) {$charset_collate};";

    $sql_departments = "CREATE TABLE {$table_departments} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";

    $sql_pdf_templates = "CREATE TABLE {$table_pdf_templates} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            preset_type varchar(50) DEFAULT 'default',
            config_data longtext NULL,
            is_active tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active (is_active),
            KEY idx_preset_type (preset_type)
        ) {$charset_collate};";

    dbDelta($sql_forms);
    dbDelta($sql_submissions);
    dbDelta($sql_departments);
    dbDelta($sql_pdf_templates);

    // Seed default departments
    self::seed_default_departments();

    // Save DB version for future upgrades
    if (!get_option('ppdb_form_db_version')) {
      add_option('ppdb_form_db_version', self::DB_VERSION);
    } else {
      update_option('ppdb_form_db_version', self::DB_VERSION);
    }
  }

  /**
   * Run incremental upgrades if needed (e.g., add indexes)
   */
  public static function maybe_upgrade(): void
  {
    global $wpdb;
    $installed = (string) get_option('ppdb_form_db_version', '1.0.0');
    if (version_compare($installed, self::DB_VERSION, '>=')) {
      return;
    }

    $table_forms = $wpdb->prefix . 'ppdb_forms';
    $table_submissions = $wpdb->prefix . 'ppdb_submissions';
    $table_departments = $wpdb->prefix . 'ppdb_departments';
    $table_pdf_templates = $wpdb->prefix . 'ppdb_pdf_templates';

    // Helper to check index existence
    $has_index = static function (string $table, string $indexName) use ($wpdb): bool {
      /* @var wpdb $wpdb */
      $rows = $wpdb->get_results($wpdb->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = %s', $indexName));
      return !empty($rows);
    };

    // Check if steps_config column exists, add if missing (for v1.2.0 upgrade)
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_forms} LIKE 'steps_config'");
    if (empty($columns)) {
      $wpdb->query("ALTER TABLE {$table_forms} ADD COLUMN steps_config longtext NULL AFTER fields_json");
    }

    // Add indexes for performance
    if (!$has_index($table_submissions, 'idx_form_created')) {
      $wpdb->query('ALTER TABLE ' . $table_submissions . ' ADD INDEX idx_form_created (form_id, created_at)');
    }
    if (!$has_index($table_submissions, 'idx_created_at')) {
      $wpdb->query('ALTER TABLE ' . $table_submissions . ' ADD INDEX idx_created_at (created_at)');
    }
    if (!$has_index($table_forms, 'idx_is_active')) {
      $wpdb->query('ALTER TABLE ' . $table_forms . ' ADD INDEX idx_is_active (is_active)');
    }
    if (!$has_index($table_forms, 'idx_updated_at')) {
      $wpdb->query('ALTER TABLE ' . $table_forms . ' ADD INDEX idx_updated_at (updated_at)');
    }
    if (!$has_index($table_departments, 'idx_active_name')) {
      $wpdb->query('ALTER TABLE ' . $table_departments . ' ADD INDEX idx_active_name (is_active, name)');
    }

    // Upgrade to 1.3.0: Add PDF templates table
    if (version_compare($installed, '1.3.0', '<')) {
      $charset_collate = $wpdb->get_charset_collate();

      $sql_pdf_templates = "CREATE TABLE {$table_pdf_templates} (
              id mediumint(9) NOT NULL AUTO_INCREMENT,
              name varchar(255) NOT NULL,
              preset_type varchar(50) DEFAULT 'default',
              config_data longtext NULL,
              is_active tinyint(1) DEFAULT 0,
              created_at datetime DEFAULT CURRENT_TIMESTAMP,
              updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              KEY idx_active (is_active),
              KEY idx_preset_type (preset_type)
          ) {$charset_collate};";

      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
      dbDelta($sql_pdf_templates);

      // Seed default PDF templates
      self::seed_default_pdf_templates();
    }

    // Ensure departments are seeded for existing installations
    if (version_compare($installed, '1.0.0', '>=')) {
      self::seed_default_departments();
    }

    update_option('ppdb_form_db_version', self::DB_VERSION);
  }

  /**
   * Seed default departments
   */
  private static function seed_default_departments(): void
  {
    global $wpdb;
    $table_departments = $wpdb->prefix . 'ppdb_departments';

    // Check if departments already exist
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_departments}");
    if ($count > 0) {
      return; // Already seeded
    }

    $default_departments = [
      'Rekayasa Perangkat Lunak (RPL)',
      'Teknik Komputer dan Jaringan (TKJ)', 
      'Desain Komunikasi Visual (DKV)',
      'Multimedia (MM)',
      'Akuntansi dan Keuangan Lembaga (AKL)',
      'Otomatisasi dan Tata Kelola Perkantoran (OTKP)',
      'Bisnis Daring dan Pemasaran (BDP)',
      'Teknik Kendaraan Ringan Otomotif (TKRO)',
      'Teknik dan Bisnis Sepeda Motor (TBSM)',
      'Teknik Elektronika Industri (TEI)'
    ];

    foreach ($default_departments as $dept_name) {
      $wpdb->insert(
        $table_departments,
        [
          'name' => $dept_name,
          'is_active' => 1,
          'created_at' => current_time('mysql')
        ],
        ['%s', '%d', '%s']
      );
    }

    // Clear cache after seeding
    delete_transient('ppdb_departments_active');
  }

  /**
   * Seed default PDF templates
   */
  private static function seed_default_pdf_templates(): void
  {
    global $wpdb;
    $table_pdf_templates = $wpdb->prefix . 'ppdb_pdf_templates';

    $default_templates = [
      [
        'name' => 'Default Template',
        'preset_type' => 'default',
        'config_data' => wp_json_encode([
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
            'contact' => get_option('admin_email')
          ]
        ]),
        'is_active' => 1
      ],
      [
        'name' => 'Modern Template',
        'preset_type' => 'modern',
        'config_data' => wp_json_encode([
          'colors' => [
            'primary' => '#10b981',
            'secondary' => '#374151',
            'text' => '#111827'
          ],
          'layout' => 'minimal',
          'header_style' => 'logo_left',
          'qr_position' => 'top_right',
          'fields' => ['nama_lengkap', 'email', 'jurusan'],
          'institution' => [
            'name' => get_bloginfo('name'),
            'logo' => '',
            'address' => '',
            'contact' => get_option('admin_email')
          ]
        ]),
        'is_active' => 0
      ],
      [
        'name' => 'Classic Template',
        'preset_type' => 'classic',
        'config_data' => wp_json_encode([
          'colors' => [
            'primary' => '#dc2626',
            'secondary' => '#1f2937',
            'text' => '#000000'
          ],
          'layout' => 'formal',
          'header_style' => 'full_header',
          'qr_position' => 'bottom_center',
          'fields' => ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan', 'alamat'],
          'institution' => [
            'name' => get_bloginfo('name'),
            'logo' => '',
            'address' => '',
            'contact' => get_option('admin_email')
          ]
        ]),
        'is_active' => 0
      ],
      [
        'name' => 'Academic Template',
        'preset_type' => 'academic',
        'config_data' => wp_json_encode([
          'colors' => [
            'primary' => '#7c3aed',
            'secondary' => '#4b5563',
            'text' => '#1f2937'
          ],
          'layout' => 'academic',
          'header_style' => 'logo_center_seal',
          'qr_position' => 'bottom_left',
          'fields' => ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan', 'tanggal_lahir'],
          'institution' => [
            'name' => get_bloginfo('name'),
            'logo' => '',
            'address' => '',
            'contact' => get_option('admin_email')
          ]
        ]),
        'is_active' => 0
      ]
    ];

    foreach ($default_templates as $template) {
      $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table_pdf_templates} WHERE preset_type = %s",
        $template['preset_type']
      ));

      if (!$existing) {
        $wpdb->insert($table_pdf_templates, $template);
      }
    }
  }
}
