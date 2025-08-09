<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Installer
{
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
  }
}
