<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Installer
{
  private const DB_VERSION = '1.2.0';

  public static function activate(): void
  {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $table_forms = $wpdb->prefix . 'ppdb_forms';
    $table_submissions = $wpdb->prefix . 'ppdb_submissions';
    $table_departments = $wpdb->prefix . 'ppdb_departments';

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

    dbDelta($sql_forms);
    dbDelta($sql_submissions);
    dbDelta($sql_departments);

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

    update_option('ppdb_form_db_version', self::DB_VERSION);
  }
}
