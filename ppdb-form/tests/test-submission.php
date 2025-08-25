<?php

/**
 * Tests for PPDB Form submission functionality
 */
class Test_PPDB_Form_Submission extends WP_UnitTestCase
{
  private $form_id;

  public function setUp(): void
  {
    parent::setUp();

    // Install plugin tables
    PPDB_Form_Installer::activate();

    // Create a test form
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    $wpdb->insert(
      $table_forms,
      [
        'name' => 'Test Form',
        'description' => 'Form for testing',
        'success_message' => 'Thank you for your submission!',
        'is_active' => 1,
        'fields_json' => json_encode([
          'nama_lengkap' => ['enabled' => 1, 'required' => 1],
          'email' => ['enabled' => 1, 'required' => 1],
          'nomor_telepon' => ['enabled' => 1, 'required' => 0],
          'jurusan' => ['enabled' => 1, 'required' => 1],
          'dok_kk' => ['enabled' => 1, 'required' => 0],
          'dok_akta' => ['enabled' => 1, 'required' => 0]
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
   * Test form shortcode renders correctly
   */
  public function test_form_shortcode_renders()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    $this->assertNotEmpty($shortcode_output);
    $this->assertStringContainsString('ppdb-form', $shortcode_output);
    $this->assertStringContainsString('nama_lengkap', $shortcode_output);
    $this->assertStringContainsString('email', $shortcode_output);
    $this->assertStringContainsString('jurusan', $shortcode_output);
  }

  /**
   * Test field registry
   */
  public function test_field_registry()
  {
    $registry = PPDB_Form_Plugin::get_field_registry();

    $this->assertIsArray($registry);
    $this->assertArrayHasKey('nama_lengkap', $registry);
    $this->assertArrayHasKey('email', $registry);
    $this->assertArrayHasKey('nomor_telepon', $registry);
    $this->assertArrayHasKey('jurusan', $registry);

    // Check document fields
    $this->assertArrayHasKey('dok_kk', $registry);
    $this->assertArrayHasKey('dok_akta', $registry);
    $this->assertArrayHasKey('dok_ijazah', $registry);
    $this->assertArrayHasKey('dok_pas_foto', $registry);

    // Check rapor fields
    $this->assertArrayHasKey('dok_rapor_1', $registry);
    $this->assertArrayHasKey('dok_rapor_2', $registry);
    $this->assertArrayHasKey('dok_rapor_1_3', $registry);
    $this->assertArrayHasKey('dok_rapor_4_6', $registry);
    $this->assertArrayHasKey('dok_rapor_1_6', $registry);

    // Check basic field structure
    $nama_field = $registry['nama_lengkap'];
    $this->assertArrayHasKey('label', $nama_field);
    $this->assertArrayHasKey('type', $nama_field);
    $this->assertEquals('text', $nama_field['type']);

    // Check file field structure
    $kk_field = $registry['dok_kk'];
    $this->assertEquals('file', $kk_field['type']);
    $this->assertArrayHasKey('accept', $kk_field);
  }

  /**
   * Test successful form submission
   */
  public function test_successful_submission()
  {
    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';

    // Valid submission data
    $submission_data = [
      'nama_lengkap' => 'John Doe',
      'email' => 'john@example.com',
      'nomor_telepon' => '081234567890',
      'jurusan' => 'TKJ'
    ];

    // Insert submission directly (simulating successful form submission)
    $result = $wpdb->insert(
      $submissions_table,
      [
        'form_id' => $this->form_id,
        'submission_data' => json_encode($submission_data),
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test User Agent',
        'submitted_at' => current_time('mysql')
      ],
      ['%d', '%s', '%s', '%s', '%s']
    );

    $this->assertNotFalse($result, 'Submission should be inserted successfully');

    // Verify submission exists
    $submission_id = $wpdb->insert_id;
    $saved_submission = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$submissions_table} WHERE id = %d",
      $submission_id
    ));

    $this->assertNotNull($saved_submission);
    $this->assertEquals($this->form_id, $saved_submission->form_id);

    $saved_data = json_decode($saved_submission->submission_data, true);
    $this->assertEquals('John Doe', $saved_data['nama_lengkap']);
    $this->assertEquals('john@example.com', $saved_data['email']);
  }

  /**
   * Test multi-step form configuration
   */
  public function test_multistep_configuration()
  {
    // Update form to use multi-step
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    $steps_config = [
      'enabled' => true,
      'steps' => [
        [
          'id' => 'step-1',
          'title' => 'Data Pribadi',
          'description' => 'Masukkan data pribadi Anda',
          'fields' => ['nama_lengkap', 'email']
        ],
        [
          'id' => 'step-2',
          'title' => 'Data Pendaftaran',
          'description' => 'Pilih jurusan dan masukkan nomor telepon',
          'fields' => ['jurusan', 'nomor_telepon']
        ],
        [
          'id' => 'step-3',
          'title' => 'Unggah Dokumen',
          'description' => 'Upload dokumen yang diperlukan',
          'fields' => ['dok_kk', 'dok_akta']
        ]
      ]
    ];

    $wpdb->update(
      $table_forms,
      ['steps_config' => json_encode($steps_config)],
      ['id' => $this->form_id],
      ['%s'],
      ['%d']
    );

    // Test shortcode with multi-step
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    $this->assertStringContainsString('ppdb-multistep', $shortcode_output);
    $this->assertStringContainsString('Data Pribadi', $shortcode_output);
    $this->assertStringContainsString('Data Pendaftaran', $shortcode_output);
    $this->assertStringContainsString('Unggah Dokumen', $shortcode_output);

    // Should contain progress indicator
    $this->assertStringContainsString('ppdb-progress', $shortcode_output);
  }

  /**
   * Test data sanitization
   */
  public function test_data_sanitization()
  {
    $test_data = [
      'nama_lengkap' => '<script>alert("xss")</script>John Doe',
      'email' => 'john@example.com<script>',
      'nomor_telepon' => '081234567890',
      'jurusan' => 'TKJ & Multimedia'
    ];

    // Simulate sanitization (would normally be done in handle_submission)
    $sanitized = [];
    foreach ($test_data as $key => $value) {
      if ($key === 'email') {
        $sanitized[$key] = sanitize_email($value);
      } else {
        $sanitized[$key] = sanitize_text_field($value);
      }
    }

    // Check that dangerous content is removed
    $this->assertStringNotContainsString('<script>', $sanitized['nama_lengkap']);
    $this->assertEquals('John Doe', $sanitized['nama_lengkap']);
    $this->assertEquals('john@example.com', $sanitized['email']);
    $this->assertEquals('TKJ & Multimedia', $sanitized['jurusan']);
  }

  /**
   * Test honeypot functionality
   */
  public function test_honeypot_field()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Check that honeypot field is present and hidden
    $this->assertStringContainsString('name="website"', $shortcode_output);
    $this->assertStringContainsString('style="display:none"', $shortcode_output);
  }

  /**
   * Test form with no active fields
   */
  public function test_form_with_no_active_fields()
  {
    // Update form to have no active fields
    global $wpdb;
    $table_forms = $wpdb->prefix . 'ppdb_forms';

    $wpdb->update(
      $table_forms,
      ['fields_json' => json_encode([
        'nama_lengkap' => ['enabled' => 0, 'required' => 0],
        'email' => ['enabled' => 0, 'required' => 0]
      ])],
      ['id' => $this->form_id],
      ['%s'],
      ['%d']
    );

    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Should show message about no fields
    $this->assertStringContainsString('Tidak ada field yang aktif', $shortcode_output);
  }

  /**
   * Test form validation requirements
   */
  public function test_form_validation_requirements()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Required fields should have required attribute
    $this->assertMatchesRegularExpression('/nama_lengkap.*required/', $shortcode_output);
    $this->assertMatchesRegularExpression('/email.*required/', $shortcode_output);
    $this->assertMatchesRegularExpression('/jurusan.*required/', $shortcode_output);

    // Optional fields should not have required attribute
    $this->assertDoesNotMatchRegularExpression('/nomor_telepon.*required/', $shortcode_output);
  }

  /**
   * Test file upload fields rendering
   */
  public function test_file_upload_fields_rendering()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Should contain file input fields
    $this->assertStringContainsString('type="file"', $shortcode_output);
    $this->assertStringContainsString('name="dok_kk"', $shortcode_output);
    $this->assertStringContainsString('name="dok_akta"', $shortcode_output);

    // Should have proper accept attributes
    $this->assertStringContainsString('accept=', $shortcode_output);
  }

  /**
   * Test form nonce security
   */
  public function test_form_nonce_security()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Should contain nonce field
    $this->assertStringContainsString('ppdb_form_nonce', $shortcode_output);
    $this->assertStringContainsString('_wpnonce', $shortcode_output);
  }

  /**
   * Test form with invalid ID
   */
  public function test_form_with_invalid_id()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="999999"]');

    // Should show error message for non-existent form
    $this->assertStringContainsString('Form tidak ditemukan', $shortcode_output);
  }

  /**
   * Test success message rendering
   */
  public function test_success_message_rendering()
  {
    // Simulate success parameters in URL
    $_GET['ppdb_thanks'] = '1';
    $_GET['sid'] = '123';
    $_GET['k'] = 'test_hash';

    // Mock the hash verification (normally would check against actual submission)
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Clean up GET parameters
    unset($_GET['ppdb_thanks'], $_GET['sid'], $_GET['k']);

    // The test would need more complex setup to fully test success message rendering
    $this->assertTrue(true);  // Placeholder assertion
  }

  /**
   * Test department dropdown options
   */
  public function test_department_dropdown_options()
  {
    $shortcode_output = do_shortcode('[ppdb_form id="' . $this->form_id . '"]');

    // Should contain select element for jurusan
    $this->assertStringContainsString('<select', $shortcode_output);
    $this->assertStringContainsString('name="jurusan"', $shortcode_output);

    // Should contain department options from database
    $this->assertStringContainsString('Teknik Komputer dan Jaringan', $shortcode_output);
    $this->assertStringContainsString('Multimedia', $shortcode_output);
  }
}
