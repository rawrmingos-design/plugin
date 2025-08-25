<?php

/**
 * Tests for PPDB_Form_Installer class
 */
class Test_PPDB_Form_Installer extends WP_UnitTestCase
{
  public function setUp(): void
  {
    parent::setUp();

    // Clean up any existing tables
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_forms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_submissions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_departments");

    // Remove options
    delete_option('ppdb_form_db_version');
  }

  public function tearDown(): void
  {
    // Clean up after each test
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_forms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_submissions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_departments");

    delete_option('ppdb_form_db_version');

    parent::tearDown();
  }

  /**
   * Test that activation creates required tables
   */
  public function test_activation_creates_tables()
  {
    // Activate the plugin
    PPDB_Form_Installer::activate();

    global $wpdb;

    // Check if tables exist
    $forms_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'ppdb_forms'));
    $submissions_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'ppdb_submissions'));
    $departments_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->prefix . 'ppdb_departments'));

    $this->assertEquals($wpdb->prefix . 'ppdb_forms', $forms_table);
    $this->assertEquals($wpdb->prefix . 'ppdb_submissions', $submissions_table);
    $this->assertEquals($wpdb->prefix . 'ppdb_departments', $departments_table);
  }

  /**
   * Test that database version is set correctly
   */
  public function test_db_version_is_set()
  {
    PPDB_Form_Installer::activate();

    $db_version = get_option('ppdb_form_db_version');
    $this->assertNotEmpty($db_version);
    $this->assertEquals(PPDB_Form_Plugin::DB_VERSION, $db_version);
  }

  /**
   * Test forms table structure
   */
  public function test_forms_table_structure()
  {
    PPDB_Form_Installer::activate();

    global $wpdb;
    $table_name = $wpdb->prefix . 'ppdb_forms';

    // Get table columns
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    $column_names = array_column($columns, 'Field');

    // Required columns
    $required_columns = [
      'id',
      'name',
      'description',
      'success_message',
      'is_active',
      'fields_json',
      'steps_config',
      'created_at',
      'updated_at'
    ];

    foreach ($required_columns as $column) {
      $this->assertContains($column, $column_names, "Column {$column} should exist in forms table");
    }
  }

  /**
   * Test submissions table structure
   */
  public function test_submissions_table_structure()
  {
    PPDB_Form_Installer::activate();

    global $wpdb;
    $table_name = $wpdb->prefix . 'ppdb_submissions';

    // Get table columns
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    $column_names = array_column($columns, 'Field');

    // Required columns
    $required_columns = [
      'id',
      'form_id',
      'submission_data',
      'ip_address',
      'user_agent',
      'submitted_at'
    ];

    foreach ($required_columns as $column) {
      $this->assertContains($column, $column_names, "Column {$column} should exist in submissions table");
    }
  }

  /**
   * Test departments table structure
   */
  public function test_departments_table_structure()
  {
    PPDB_Form_Installer::activate();

    global $wpdb;
    $table_name = $wpdb->prefix . 'ppdb_departments';

    // Get table columns
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    $column_names = array_column($columns, 'Field');

    // Required columns
    $required_columns = [
      'id',
      'name',
      'description',
      'is_active',
      'created_at'
    ];

    foreach ($required_columns as $column) {
      $this->assertContains($column, $column_names, "Column {$column} should exist in departments table");
    }
  }

  /**
   * Test upgrade functionality
   */
  public function test_upgrade_functionality()
  {
    // Simulate old version installation (without steps_config)
    global $wpdb;

    // Create basic tables without steps_config
    $table_forms = $wpdb->prefix . 'ppdb_forms';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_forms} (
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

    require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Set old version
    update_option('ppdb_form_db_version', '1.0.0');

    // Run upgrade
    PPDB_Form_Installer::maybe_upgrade();

    // Check if steps_config column was added
    $columns = $wpdb->get_results("DESCRIBE {$table_forms}");
    $column_names = array_column($columns, 'Field');

    $this->assertContains('steps_config', $column_names, 'steps_config column should be added during upgrade');

    // Check version was updated
    $this->assertEquals(PPDB_Form_Plugin::DB_VERSION, get_option('ppdb_form_db_version'));
  }

  /**
   * Test default departments are created
   */
  public function test_default_departments_created()
  {
    PPDB_Form_Installer::activate();

    global $wpdb;
    $table_name = $wpdb->prefix . 'ppdb_departments';

    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    $this->assertGreaterThan(0, $count, 'Default departments should be created');

    // Check for specific departments
    $department_names = $wpdb->get_col("SELECT name FROM {$table_name}");
    $this->assertContains('Teknik Komputer dan Jaringan', $department_names);
    $this->assertContains('Multimedia', $department_names);
  }

  /**
   * Test database indexes are created
   */
  public function test_database_indexes_created()
  {
    PPDB_Form_Installer::activate();

    global $wpdb;

    // Check forms table indexes
    $forms_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}ppdb_forms");
    $forms_index_names = array_column($forms_indexes, 'Key_name');

    $this->assertContains('idx_is_active', $forms_index_names, 'is_active index should exist');
    $this->assertContains('idx_updated_at', $forms_index_names, 'updated_at index should exist');

    // Check departments table indexes
    $dept_indexes = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}ppdb_departments");
    $dept_index_names = array_column($dept_indexes, 'Key_name');

    $this->assertContains('idx_active_name', $dept_index_names, 'active_name composite index should exist');
  }

  /**
   * Test plugin activation hook
   */
  public function test_plugin_activation_hook()
  {
    // Test that the activation hook is properly registered
    $this->assertTrue(class_exists('PPDB_Form_Installer'), 'PPDB_Form_Installer class should exist');
    $this->assertTrue(method_exists('PPDB_Form_Installer', 'activate'), 'activate method should exist');

    // Test activation creates all necessary components
    PPDB_Form_Installer::activate();

    // Verify tables exist
    global $wpdb;
    $tables = [
      $wpdb->prefix . 'ppdb_forms',
      $wpdb->prefix . 'ppdb_submissions',
      $wpdb->prefix . 'ppdb_departments'
    ];

    foreach ($tables as $table) {
      $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
      $this->assertEquals($table, $exists, "Table {$table} should exist after activation");
    }

    // Verify options are set
    $this->assertNotEmpty(get_option('ppdb_form_db_version'), 'DB version should be set');
  }
}
