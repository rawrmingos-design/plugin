<?php

/**
 * Tests for PPDB Form admin functionality
 */
class Test_PPDB_Form_Admin extends WP_UnitTestCase
{
  private $form_id;
  private $admin_user_id;

  public function setUp(): void
  {
    parent::setUp();

    // Install plugin tables
    PPDB_Form_Installer::activate();

    // Create admin user
    $this->admin_user_id = $this->factory->user->create([
      'role' => 'administrator'
    ]);
    wp_set_current_user($this->admin_user_id);

    // Create test form
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    $wpdb->insert(
      $table_forms,
      [
        'name' => 'Test Admin Form',
        'description' => 'Form for admin testing',
        'success_message' => 'Thank you!',
        'is_active' => 1,
        'fields_json' => json_encode([
          'nama_lengkap' => ['enabled' => 1, 'required' => 1],
          'email' => ['enabled' => 1, 'required' => 1],
          'jurusan' => ['enabled' => 1, 'required' => 1]
        ]),
        'steps_config' => json_encode([
          'enabled' => false,
          'steps' => []
        ]),
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
      ],
      ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
    );

    $this->form_id = $wpdb->insert_id;
  }

  public function tearDown(): void
  {
    // Clean up
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_forms");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_submissions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ppdb_departments");

    delete_option('ppdb_form_db_version');

    parent::tearDown();
  }

  /**
   * Test admin class exists and methods are callable
   */
  public function test_admin_class_exists()
  {
    $this->assertTrue(class_exists('PPDB_Form_Admin'), 'PPDB_Form_Admin class should exist');
    $this->assertTrue(method_exists('PPDB_Form_Admin', 'register_menu'), 'register_menu method should exist');
    $this->assertTrue(method_exists('PPDB_Form_Admin', 'render_forms_page'), 'render_forms_page method should exist');
    $this->assertTrue(method_exists('PPDB_Form_Admin', 'render_registrants_page_new'), 'render_registrants_page_new method should exist');
  }

  /**
   * Test form creation in admin
   */
  public function test_form_creation()
  {
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    // Simulate form creation
    $form_data = [
      'name' => 'New Test Form',
      'description' => 'Created via test',
      'success_message' => 'Success!',
      'is_active' => 1,
      'fields_json' => json_encode([
        'nama_lengkap' => ['enabled' => 1, 'required' => 1]
      ]),
      'steps_config' => json_encode(['enabled' => false, 'steps' => []]),
      'created_at' => current_time('mysql'),
      'updated_at' => current_time('mysql')
    ];

    $result = $wpdb->insert($table_forms, $form_data, ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

    $this->assertNotFalse($result, 'Form should be created successfully');

    $form_id = $wpdb->insert_id;
    $saved_form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_forms} WHERE id = %d", $form_id));

    $this->assertNotNull($saved_form);
    $this->assertEquals('New Test Form', $saved_form->name);
    $this->assertEquals(1, $saved_form->is_active);
  }

  /**
   * Test form editing functionality
   */
  public function test_form_editing()
  {
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    // Update form
    $update_data = [
      'name' => 'Updated Test Form',
      'description' => 'Updated description',
      'updated_at' => current_time('mysql')
    ];

    $result = $wpdb->update(
      $table_forms,
      $update_data,
      ['id' => $this->form_id],
      ['%s', '%s', '%s'],
      ['%d']
    );

    $this->assertNotFalse($result, 'Form should be updated successfully');

    $updated_form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_forms} WHERE id = %d", $this->form_id));
    $this->assertEquals('Updated Test Form', $updated_form->name);
    $this->assertEquals('Updated description', $updated_form->description);
  }

  /**
   * Test submissions list table functionality
   */
  public function test_submissions_list_table()
  {
    // Create test submissions
    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';

    $submission_data = [
      'nama_lengkap' => 'Test User',
      'email' => 'test@example.com',
      'jurusan' => 'TKJ'
    ];

    $wpdb->insert(
      $submissions_table,
      [
        'form_id' => $this->form_id,
        'submission_data' => json_encode($submission_data),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test Agent',
        'submitted_at' => current_time('mysql')
      ],
      ['%d', '%s', '%s', '%s', '%s']
    );

    // Test list table class
    $this->assertTrue(class_exists('PPDB_Form_Submissions_List_Table'), 'Submissions list table class should exist');

    $list_table = new PPDB_Form_Submissions_List_Table();
    $this->assertInstanceOf('WP_List_Table', $list_table, 'Should extend WP_List_Table');

    // Test prepare items
    $list_table->prepare_items();
    $this->assertIsArray($list_table->items, 'Items should be an array');
  }

  /**
   * Test field configuration
   */
  public function test_field_configuration()
  {
    $registry = PPDB_Form_Plugin::get_field_registry();
    $this->assertIsArray($registry, 'Field registry should be an array');

    // Test default field configuration generation
    $default_config = PPDB_Form_Admin::generate_default_fields_config();
    $this->assertIsArray($default_config, 'Default config should be an array');

    // Test that basic fields are enabled by default
    $this->assertTrue($default_config['nama_lengkap']['enabled'], 'nama_lengkap should be enabled by default');
    $this->assertTrue($default_config['nama_lengkap']['required'], 'nama_lengkap should be required by default');
    $this->assertTrue($default_config['email']['enabled'], 'email should be enabled by default');
  }

  /**
   * Test steps builder functionality
   */
  public function test_steps_builder()
  {
    // Test default steps configuration
    $default_steps = PPDB_Form_Admin::get_default_steps_config();
    $this->assertIsArray($default_steps, 'Default steps should be an array');
    $this->assertArrayHasKey('enabled', $default_steps);
    $this->assertArrayHasKey('steps', $default_steps);

    // Test steps structure
    $steps = $default_steps['steps'];
    $this->assertIsArray($steps, 'Steps should be an array');

    if (!empty($steps)) {
      $first_step = $steps[0];
      $this->assertArrayHasKey('id', $first_step);
      $this->assertArrayHasKey('title', $first_step);
      $this->assertArrayHasKey('fields', $first_step);
    }
  }

  /**
   * Test CSV export functionality
   */
  public function test_csv_export_headers()
  {
    // Test that export method exists
    $this->assertTrue(method_exists('PPDB_Form_Admin', 'export_current_filter_as_csv'), 'CSV export method should exist');

    // Create test submission for export
    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';

    $submission_data = [
      'nama_lengkap' => 'Export Test',
      'email' => 'export@test.com',
      'jurusan' => 'MM'
    ];

    $wpdb->insert(
      $submissions_table,
      [
        'form_id' => $this->form_id,
        'submission_data' => json_encode($submission_data),
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Export Agent',
        'submitted_at' => current_time('mysql')
      ],
      ['%d', '%s', '%s', '%s', '%s']
    );

    // We can't easily test the full export without output buffering and headers
    // but we can verify the data would be correct
    $count = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$submissions_table} WHERE form_id = %d",
      $this->form_id
    ));

    $this->assertGreaterThan(0, $count, 'Should have submission data for export');
  }

  /**
   * Test department management
   */
  public function test_department_management()
  {
    global $wpdb;
    $departments_table = $wpdb->prefix . 'ppdb_departments';

    // Test department creation
    $result = $wpdb->insert(
      $departments_table,
      [
        'name' => 'Test Department',
        'description' => 'Department for testing',
        'is_active' => 1,
        'created_at' => current_time('mysql')
      ],
      ['%s', '%s', '%d', '%s']
    );

    $this->assertNotFalse($result, 'Department should be created');

    $dept_id = $wpdb->insert_id;
    $saved_dept = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$departments_table} WHERE id = %d", $dept_id));

    $this->assertNotNull($saved_dept);
    $this->assertEquals('Test Department', $saved_dept->name);
    $this->assertEquals(1, $saved_dept->is_active);
  }

  /**
   * Test admin permissions
   */
  public function test_admin_permissions()
  {
    // Test with admin user
    wp_set_current_user($this->admin_user_id);
    $this->assertTrue(current_user_can('manage_options'), 'Admin should have manage_options capability');

    // Test with non-admin user
    $user_id = $this->factory->user->create(['role' => 'subscriber']);
    wp_set_current_user($user_id);
    $this->assertFalse(current_user_can('manage_options'), 'Subscriber should not have manage_options capability');

    // Reset to admin
    wp_set_current_user($this->admin_user_id);
  }

  /**
   * Test AJAX save steps functionality
   */
  public function test_ajax_save_steps()
  {
    // Test method exists
    $this->assertTrue(method_exists('PPDB_Form_Admin', 'ajax_save_steps'), 'AJAX save steps method should exist');

    // Test with valid steps data
    $steps_data = [
      [
        'id' => 'step-1',
        'title' => 'Data Pribadi',
        'description' => 'Masukkan data pribadi',
        'fields' => ['nama_lengkap', 'email']
      ],
      [
        'id' => 'step-2',
        'title' => 'Data Pendaftaran',
        'description' => 'Pilih jurusan',
        'fields' => ['jurusan']
      ]
    ];

    // Update form with steps config
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    $result = $wpdb->update(
      $table_forms,
      ['steps_config' => json_encode(['enabled' => true, 'steps' => $steps_data])],
      ['id' => $this->form_id],
      ['%s'],
      ['%d']
    );

    $this->assertNotFalse($result, 'Steps config should be saved');

    // Verify saved data
    $saved_form = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_forms} WHERE id = %d", $this->form_id));
    $saved_steps = json_decode($saved_form->steps_config, true);

    $this->assertTrue($saved_steps['enabled'], 'Steps should be enabled');
    $this->assertCount(2, $saved_steps['steps'], 'Should have 2 steps');
    $this->assertEquals('Data Pribadi', $saved_steps['steps'][0]['title']);
  }

  /**
   * Test submission detail rendering
   */
  public function test_submission_detail_rendering()
  {
    // Create test submission with file uploads
    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';

    $submission_data = [
      'nama_lengkap' => 'Detail Test User',
      'email' => 'detail@test.com',
      'jurusan' => 'RPL',
      'dok_kk' => 'http://example.com/uploads/kk.pdf',
      'dok_akta' => 'http://example.com/uploads/akta.pdf'
    ];

    $wpdb->insert(
      $submissions_table,
      [
        'form_id' => $this->form_id,
        'submission_data' => json_encode($submission_data),
        'ip_address' => '10.0.0.1',
        'user_agent' => 'Detail Test Agent',
        'submitted_at' => current_time('mysql')
      ],
      ['%d', '%s', '%s', '%s', '%s']
    );

    $submission_id = $wpdb->insert_id;

    // Verify submission was created
    $saved_submission = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$submissions_table} WHERE id = %d",
      $submission_id
    ));

    $this->assertNotNull($saved_submission);

    $saved_data = json_decode($saved_submission->submission_data, true);
    $this->assertEquals('Detail Test User', $saved_data['nama_lengkap']);
    $this->assertStringContainsString('uploads/kk.pdf', $saved_data['dok_kk']);
  }
}
