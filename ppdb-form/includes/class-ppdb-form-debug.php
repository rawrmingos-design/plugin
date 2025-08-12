<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Debug
{
  public static function register_menu(): void
  {
    if (!current_user_can('manage_options')) {
      return;
    }

    add_submenu_page(
      'ppdb-form',
      __('Debug PPDB', 'ppdb-form'),
      __('🔧 Debug', 'ppdb-form'),
      'manage_options',
      'ppdb-form-debug',
      [self::class, 'render_debug_page']
    );
  }

  public static function render_debug_page(): void
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
    }

    // Handle actions
    if (isset($_POST['action'])) {
      check_admin_referer('ppdb_debug_action');
      $action = sanitize_text_field((string) $_POST['action']);

      switch ($action) {
        case 'clear_transients':
          self::clear_transients();
          echo '<div class="updated"><p>' . esc_html__('Transients cleared successfully.', 'ppdb-form') . '</p></div>';
          break;
        case 'reset_db':
          self::reset_database();
          echo '<div class="updated"><p>' . esc_html__('Database reset successfully.', 'ppdb-form') . '</p></div>';
          break;
        case 'test_submission':
          $result = self::create_test_submission();
          echo '<div class="updated"><p>' . esc_html($result) . '</p></div>';
          break;
        case 'seed_submissions':
          $count = isset($_POST['seed_count']) ? max(1, min(1000, (int) $_POST['seed_count'])) : 50;
          $opts = [
            'start_date' => isset($_POST['seed_start_date']) ? sanitize_text_field((string) $_POST['seed_start_date']) : '',
            'end_date' => isset($_POST['seed_end_date']) ? sanitize_text_field((string) $_POST['seed_end_date']) : '',
            'department' => isset($_POST['seed_department']) ? sanitize_text_field((string) $_POST['seed_department']) : '',
            'male_ratio' => isset($_POST['seed_gender_ratio_male']) ? max(0, min(100, (int) $_POST['seed_gender_ratio_male'])) : 50,
            'attach_docs' => !empty($_POST['seed_attach_docs']),
            'doc_url' => isset($_POST['seed_doc_url']) ? esc_url_raw((string) $_POST['seed_doc_url']) : '',
          ];
          $result = self::seed_dummy_submissions($count, $opts);
          echo '<div class="updated"><p>' . esc_html($result) . '</p></div>';
          break;
      }
    }

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('PPDB Form Debug Panel', 'ppdb-form') . '</h1>';

    // System Info
    echo '<div class="card">';
    echo '<h2>' . esc_html__('System Information', 'ppdb-form') . '</h2>';
    self::render_system_info();
    echo '</div>';

    // Database Status
    echo '<div class="card">';
    echo '<h2>' . esc_html__('Database Status', 'ppdb-form') . '</h2>';
    self::render_database_status();
    echo '</div>';

    // Forms Status
    echo '<div class="card">';
    echo '<h2>' . esc_html__('Forms Status', 'ppdb-form') . '</h2>';
    self::render_forms_status();
    echo '</div>';

    // Submissions Status
    echo '<div class="card">';
    echo '<h2>' . esc_html__('Submissions Status', 'ppdb-form') . '</h2>';
    self::render_submissions_status();
    echo '</div>';

    // Debug Actions
    echo '<div class="card">';
    echo '<h2>' . esc_html__('Debug Actions', 'ppdb-form') . '</h2>';
    self::render_debug_actions();
    echo '</div>';

    // Recent Logs
    echo '<div class="card">';
    echo '<h2>' . esc_html__('Recent Error Logs', 'ppdb-form') . '</h2>';
    self::render_recent_logs();
    echo '</div>';

    echo '</div>';
  }

  private static function render_system_info(): void
  {
    echo '<table class="form-table">';
    echo '<tr><th>Plugin Version</th><td>' . esc_html(PPDB_Form_Plugin::VERSION) . '</td></tr>';
    echo '<tr><th>Database Version</th><td>' . esc_html(PPDB_Form_Plugin::DB_VERSION) . '</td></tr>';
    echo '<tr><th>WordPress Version</th><td>' . esc_html(get_bloginfo('version')) . '</td></tr>';
    echo '<tr><th>PHP Version</th><td>' . esc_html(PHP_VERSION) . '</td></tr>';
    echo '<tr><th>MySQL Version</th><td>' . esc_html($GLOBALS['wpdb']->db_version()) . '</td></tr>';
    echo '<tr><th>WP Debug</th><td>' . (defined('WP_DEBUG') && WP_DEBUG ? '✅ Enabled' : '❌ Disabled') . '</td></tr>';
    echo '<tr><th>Current User</th><td>' . esc_html(wp_get_current_user()->user_login) . '</td></tr>';
    echo '<tr><th>Current Time</th><td>' . esc_html(current_time('Y-m-d H:i:s')) . '</td></tr>';
    echo '</table>';
  }

  private static function render_database_status(): void
  {
    global $wpdb;

    $tables = [
      'Forms' => $wpdb->prefix . 'ppdb_forms',
      'Submissions' => $wpdb->prefix . 'ppdb_submissions',
      'Departments' => $wpdb->prefix . 'ppdb_departments',
    ];

    echo '<table class="form-table">';
    foreach ($tables as $name => $table) {
      $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
      $count = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") : 0;

      echo '<tr>';
      echo '<th>' . esc_html($name) . ' Table</th>';
      echo '<td>' . ($exists ? '✅ Exists' : '❌ Missing') . ' | Records: ' . $count . '</td>';
      echo '</tr>';
    }

    // Check for steps_config column
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}ppdb_forms LIKE 'steps_config'");
    echo '<tr>';
    echo '<th>Multi-Step Column</th>';
    echo '<td>' . (!empty($columns) ? '✅ steps_config exists' : '❌ steps_config missing') . '</td>';
    echo '</tr>';

    echo '</table>';
  }

  private static function render_forms_status(): void
  {
    global $wpdb;

    $forms = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppdb_forms ORDER BY id DESC LIMIT 10");

    if (empty($forms)) {
      echo '<p>' . esc_html__('No forms found.', 'ppdb-form') . '</p>';
      return;
    }

    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Active</th><th>Multi-Step</th><th>Fields</th><th>Created</th></tr></thead>';
    echo '<tbody>';

    foreach ($forms as $form) {
      $fields_config = $form->fields_json ? json_decode($form->fields_json, true) : [];
      $enabled_fields = array_filter($fields_config, fn($f) => !empty($f['enabled']));

      $steps_config = $form->steps_config ? json_decode($form->steps_config, true) : null;
      $is_multistep = !empty($steps_config['enabled']);

      echo '<tr>';
      echo '<td>' . (int) $form->id . '</td>';
      echo '<td>' . esc_html($form->name) . '</td>';
      echo '<td>' . ((int) $form->is_active ? '✅' : '❌') . '</td>';
      echo '<td>' . ($is_multistep ? '✅ (' . count($steps_config['steps'] ?? []) . ' steps)' : '❌') . '</td>';
      echo '<td>' . count($enabled_fields) . ' enabled</td>';
      echo '<td>' . esc_html($form->created_at) . '</td>';
      echo '</tr>';
    }

    echo '</tbody></table>';
  }

  private static function render_submissions_status(): void
  {
    global $wpdb;

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppdb_submissions");
    $today = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->prefix}ppdb_submissions WHERE DATE(created_at) = %s",
      current_time('Y-m-d')
    ));
    $this_week = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$wpdb->prefix}ppdb_submissions WHERE created_at >= %s",
      date('Y-m-d', strtotime('-7 days'))
    ));

    echo '<table class="form-table">';
    echo '<tr><th>Total Submissions</th><td>' . $total . '</td></tr>';
    echo '<tr><th>Today</th><td>' . $today . '</td></tr>';
    echo '<tr><th>This Week</th><td>' . $this_week . '</td></tr>';
    echo '</table>';

    // Recent submissions
    $recent = $wpdb->get_results("
      SELECT s.*, f.name as form_name 
      FROM {$wpdb->prefix}ppdb_submissions s 
      LEFT JOIN {$wpdb->prefix}ppdb_forms f ON s.form_id = f.id 
      ORDER BY s.created_at DESC 
      LIMIT 5
    ");

    if (!empty($recent)) {
      echo '<h4>' . esc_html__('Recent Submissions', 'ppdb-form') . '</h4>';
      echo '<table class="wp-list-table widefat fixed striped">';
      echo '<thead><tr><th>ID</th><th>Form</th><th>Data Preview</th><th>Created</th></tr></thead>';
      echo '<tbody>';

      foreach ($recent as $submission) {
        $data = json_decode($submission->submission_data, true) ?: [];
        $preview = $data['nama_lengkap'] ?? $data['email'] ?? 'No name/email';

        echo '<tr>';
        echo '<td>' . (int) $submission->id . '</td>';
        echo '<td>' . esc_html($submission->form_name ?: 'Unknown') . '</td>';
        echo '<td>' . esc_html($preview) . '</td>';
        echo '<td>' . esc_html($submission->created_at) . '</td>';
        echo '</tr>';
      }

      echo '</tbody></table>';
    }
  }

  private static function render_debug_actions(): void
  {
    echo '<form method="post">';
    wp_nonce_field('ppdb_debug_action');

    echo '<p>';
    echo '<button type="submit" name="action" value="clear_transients" class="button">' . esc_html__('Clear Transients', 'ppdb-form') . '</button> ';
    echo '<button type="submit" name="action" value="test_submission" class="button">' . esc_html__('Create Test Submission', 'ppdb-form') . '</button> ';
    echo '<button type="submit" name="action" value="reset_db" class="button button-secondary" onclick="return confirm(\'Are you sure? This will delete ALL data!\')">' . esc_html__('Reset Database (DANGER)', 'ppdb-form') . '</button>';
    echo '</p>';

    echo '<hr/>';
    echo '<h3>' . esc_html__('Seed Dummy Data', 'ppdb-form') . '</h3>';
    echo '<p>' . esc_html__('Generate multiple randomized submissions for testing list/search/filter/export.', 'ppdb-form') . '</p>';
    echo '<p style="margin-bottom:12px;">';
    echo '<label style="margin-right:8px;">' . esc_html__('Count', 'ppdb-form') . ' <input type="number" name="seed_count" value="50" min="1" max="1000" style="width:90px;" /></label> ';
    echo '</p>';

    // Date range
    echo '<p style="margin-bottom:12px;">';
    echo '<label style="margin-right:8px;">' . esc_html__('Start Date', 'ppdb-form') . ' <input type="date" name="seed_start_date" /></label> ';
    echo '<label>' . esc_html__('End Date', 'ppdb-form') . ' <input type="date" name="seed_end_date" /></label> ';
    echo '</p>';

    // Department select
    global $wpdb;
    $dept_options = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}ppdb_departments WHERE is_active = 1 ORDER BY name ASC");
    echo '<p style="margin-bottom:12px;">';
    echo '<label>' . esc_html__('Jurusan', 'ppdb-form') . ' ';
    echo '<select name="seed_department">';
    echo '<option value="">' . esc_html__('Random', 'ppdb-form') . '</option>';
    if (!empty($dept_options)) {
      foreach ($dept_options as $dn) {
        echo '<option value="' . esc_attr($dn) . '\">' . esc_html($dn) . '</option>';
      }
    }
    echo '</select></label>';
    echo '</p>';

    // Gender ratio
    echo '<p style="margin-bottom:12px;">';
    echo '<label>' . esc_html__('Male Ratio (%)', 'ppdb-form') . ' <input type="number" name="seed_gender_ratio_male" value="50" min="0" max="100" style="width:90px;" /></label> ';
    echo '</p>';

    // Document attachment
    $default_doc = 'http://localhost:5000/wordpress/wp-content/uploads/2025/08/Cetak-Biodata-Peserta-Didik-Baru-PPDB-SMAN-KONOHA.pdf';
    echo '<p style="margin-bottom:12px;">';
    echo '<label style="margin-right:8px;"><input type="checkbox" name="seed_attach_docs" value="1" /> ' . esc_html__('Attach dummy document URL to file fields', 'ppdb-form') . '</label>';
    echo '<input type="url" name="seed_doc_url" value="' . esc_attr($default_doc) . '" class="regular-text" style="min-width:420px;" />';
    echo '</p>';

    echo '<p><button type="submit" name="action" value="seed_submissions" class="button button-primary">' . esc_html__('Generate', 'ppdb-form') . '</button></p>';

    echo '</form>';
  }

  private static function render_recent_logs(): void
  {
    $log_file = WP_CONTENT_DIR . '/debug.log';

    if (!file_exists($log_file)) {
      echo '<p>' . esc_html__('Debug log file not found.', 'ppdb-form') . '</p>';
      return;
    }

    $logs = file_get_contents($log_file);
    $ppdb_logs = array_filter(
      explode("\n", $logs),
      fn($line) => strpos($line, 'PPDB') !== false
    );

    $recent_logs = array_slice(array_reverse($ppdb_logs), 0, 10);

    if (empty($recent_logs)) {
      echo '<p>' . esc_html__('No PPDB-related logs found.', 'ppdb-form') . '</p>';
      return;
    }

    echo '<pre style="background: #f1f1f1; padding: 10px; max-height: 300px; overflow-y: auto;">';
    foreach ($recent_logs as $log) {
      echo esc_html($log) . "\n";
    }
    echo '</pre>';
  }

  private static function clear_transients(): void
  {
    global $wpdb;

    // Clear PPDB-related transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ppdb_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ppdb_%'");
  }

  private static function reset_database(): void
  {
    global $wpdb;

    // Truncate all tables
    $tables = [
      $wpdb->prefix . 'ppdb_forms',
      $wpdb->prefix . 'ppdb_submissions',
      $wpdb->prefix . 'ppdb_departments',
    ];

    foreach ($tables as $table) {
      $wpdb->query("TRUNCATE TABLE {$table}");
    }

    // Reset options
    delete_option('ppdb_form_db_version');

    // Reinstall
    PPDB_Form_Installer::activate();
  }

  private static function create_test_submission(): string
  {
    global $wpdb;

    // Get first active form
    $form = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ppdb_forms WHERE is_active = 1 LIMIT 1");

    if (!$form) {
      return 'No active forms found. Please create a form first.';
    }

    $test_data = [
      'nama_lengkap' => 'Test User ' . date('His'),
      'nisn' => '1234567890',
      'email' => 'test' . date('His') . '@example.com',
      'nomor_telepon' => '08123456789',
      'jenis_kelamin' => 'Laki-Laki',
      'alamat' => 'Test Address',
      'jurusan' => 'Test Department',
    ];

    $result = $wpdb->insert(
      $wpdb->prefix . 'ppdb_submissions',
      [
        'form_id' => $form->id,
        'submission_data' => wp_json_encode($test_data),
        'created_at' => current_time('mysql'),
      ],
      ['%d', '%s', '%s']
    );

    if ($result) {
      return 'Test submission created successfully with ID: ' . $wpdb->insert_id;
    } else {
      return 'Failed to create test submission: ' . $wpdb->last_error;
    }
  }

  private static function seed_dummy_submissions(int $count, array $opts = []): string
  {
    global $wpdb;

    // Ensure there is an active form
    $form = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ppdb_forms WHERE is_active = 1 LIMIT 1");
    if (!$form) {
      $now = current_time('mysql');
      $inserted = $wpdb->insert(
        $wpdb->prefix . 'ppdb_forms',
        [
          'name' => 'Debug Form',
          'description' => 'Form for debug seeding',
          'success_message' => 'Terima kasih. Data terkirim.',
          'is_active' => 1,
          'fields_json' => null,
          'steps_config' => null,
          'created_at' => $now,
          'updated_at' => $now,
        ],
        ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
      );
      if (!$inserted) {
        return 'Failed to create debug form: ' . $wpdb->last_error;
      }
      $form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ppdb_forms WHERE id = %d", $wpdb->insert_id));
    }

    // Prefer departments if available
    $departments = $wpdb->get_col("SELECT name FROM {$wpdb->prefix}ppdb_departments WHERE is_active = 1");
    if (empty($departments)) {
      $departments = ['RPL', 'TKJ', 'DKV', 'MM', 'AKL'];
    }

    $gender = ['Laki-Laki', 'Perempuan'];

    // Date range handling
    $startTs = null;
    $endTs = null;
    if (!empty($opts['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $opts['start_date'])) {
      $startTs = strtotime($opts['start_date'] . ' 00:00:00');
    }
    if (!empty($opts['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $opts['end_date'])) {
      $endTs = strtotime($opts['end_date'] . ' 23:59:59');
    }
    if ($startTs === null || $endTs === null || $startTs > $endTs) {
      $endTs = time();
      $startTs = strtotime('-60 days', $endTs);
    }

    $maleRatio = isset($opts['male_ratio']) ? (int) $opts['male_ratio'] : 50;  // 0-100
    $fixedDept = !empty($opts['department']) ? (string) $opts['department'] : '';
    $attachDocs = !empty($opts['attach_docs']);
    $docUrl = !empty($opts['doc_url']) ? (string) $opts['doc_url'] : '';

    // Collect file fields from registry if needed
    $fileFieldKeys = [];
    if ($attachDocs && class_exists('PPDB_Form_Plugin')) {
      $registry = PPDB_Form_Plugin::get_field_registry();
      foreach ($registry as $key => $meta) {
        if (isset($meta['type']) && $meta['type'] === 'file') {
          $fileFieldKeys[] = $key;
        }
      }
    }

    $inserted = 0;
    for ($i = 0; $i < $count; $i++) {
      $name = 'Dummy ' . wp_generate_password(6, false, false);
      $nisn = (string) rand(1000000000, 9999999999);
      $email = strtolower(str_replace(' ', '.', $name)) . $i . '@example.com';
      $phone = '08' . rand(100000000, 999999999);
      $dept = $fixedDept !== '' ? $fixedDept : $departments[array_rand($departments)];
      $sex = (rand(1, 100) <= $maleRatio) ? 'Laki-Laki' : 'Perempuan';
      $createdTs = rand($startTs, $endTs);
      $created = date('Y-m-d H:i:s', $createdTs);

      $data = [
        'nama_lengkap' => $name,
        'nisn' => $nisn,
        'email' => $email,
        'nomor_telepon' => $phone,
        'jenis_kelamin' => $sex,
        'alamat' => 'Jl. Contoh No. ' . rand(1, 200),
        'jurusan' => $dept,
      ];

      if ($attachDocs && !empty($fileFieldKeys) && $docUrl !== '') {
        foreach ($fileFieldKeys as $ff) {
          $data[$ff] = $docUrl;
        }
      }

      $ok = $wpdb->insert(
        $wpdb->prefix . 'ppdb_submissions',
        [
          'form_id' => $form->id,
          'submission_data' => wp_json_encode($data),
          'created_at' => $created,
        ],
        ['%d', '%s', '%s']
      );

      if ($ok) {
        $inserted++;
      }
    }

    return sprintf('Seeded %d submissions for form ID %d.', $inserted, (int) $form->id);
  }
}
