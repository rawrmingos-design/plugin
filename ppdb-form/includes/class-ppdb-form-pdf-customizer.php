<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * PDF Customizer for PPDB Form
 * Handles admin interface for PDF template customization
 */
class PPDB_Form_PDF_Customizer
{
  /**
   * Initialize PDF customizer hooks
   */
  public static function init(): void
  {
    add_action('admin_menu', [self::class, 'register_menu'], 15);
    add_action('admin_init', [self::class, 'register_settings']);
    add_action('wp_ajax_ppdb_pdf_preview', [self::class, 'ajax_generate_preview']);
    add_action('wp_ajax_ppdb_pdf_activate_template', [self::class, 'ajax_activate_template']);
    add_action('wp_ajax_ppdb_pdf_delete_template', [self::class, 'ajax_delete_template']);
    add_action('wp_ajax_ppdb_pdf_load_preset', [self::class, 'ajax_load_preset']);
  }

  /**
   * Register admin menu for PDF templates
   */
  public static function register_menu(): void
  {
    add_submenu_page(
      'ppdb-form',
      __('Template PDF', 'ppdb-form'),
      __('Template PDF', 'ppdb-form'),
      'manage_options',
      'ppdb-form-pdf-templates',
      [self::class, 'render_settings_page']
    );
  }

  /**
   * Register settings for PDF templates
   */
  public static function register_settings(): void
  {
    register_setting('ppdb_pdf_template_settings', 'ppdb_pdf_active_template', [
      'type' => 'integer',
      'sanitize_callback' => 'absint',
      'default' => 0,
    ]);
  }

  /**
   * Render PDF template settings page
   */
  public static function render_settings_page(): void
  {
    if (!current_user_can('manage_options')) {
      wp_die(__('Anda tidak memiliki izin untuk mengakses halaman ini.', 'ppdb-form'));
    }

    // Handle form submissions
    if (isset($_POST['action']) && check_admin_referer('ppdb_pdf_template_action')) {
      self::handle_form_submission();
    }

    $active_template = PPDB_Form_PDF_Template::get_active_template();
    $all_templates = PPDB_Form_PDF_Template::get_all_templates();
    $presets = PPDB_Form_PDF_Template::get_available_presets();
    $available_fields = PPDB_Form_PDF_Template::get_available_fields();

    // Categorize fields
    $categorized_fields = [];
    foreach ($available_fields as $field) {
      $categorized_fields[$field['category']][] = $field;
    }

    ?>
    <div class="wrap ppdb-pdf-customizer">
      <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-media-document" style="font-size: 1.2em; margin-right: 8px;"></span>
        <?php esc_html_e('Template PDF Bukti Pendaftaran', 'ppdb-form'); ?>
      </h1>
      
      <p class="description" style="margin-top: 10px;">
        <?php esc_html_e('Kustomisasi tampilan PDF bukti pendaftaran sesuai dengan branding institusi Anda.', 'ppdb-form'); ?>
      </p>
      
      <div id="ppdb-pdf-customizer-container" style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- Left Panel: Settings -->
        <div class="ppdb-customizer-panel" style="flex: 1; max-width: 60%;">
          <form method="post" enctype="multipart/form-data" id="ppdb-pdf-template-form">
            <?php wp_nonce_field('ppdb_pdf_template_action'); ?>
            <input type="hidden" name="action" value="save_template">
            
            <!-- Template Selection -->
            <div class="ppdb-section">
              <h2 class="ppdb-section-title">
                <span class="dashicons dashicons-admin-appearance"></span>
                <?php esc_html_e('Pilih Template', 'ppdb-form'); ?>
              </h2>
              
              <div class="ppdb-template-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <?php foreach ($presets as $preset_key => $preset): ?>
                <div class="ppdb-template-option">
                  <label class="ppdb-template-card" style="display: block; border: 2px solid #ddd; border-radius: 8px; padding: 15px; cursor: pointer; text-align: center; transition: all 0.3s;">
                    <input type="radio" name="preset_type" value="<?php echo esc_attr($preset_key); ?>" 
                           <?php checked($active_template['preset_type'] ?? 'default', $preset_key); ?> 
                           style="margin-bottom: 10px;">
                    <div class="template-preview" style="height: 120px; background: #f9f9f9; border-radius: 4px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; color: #666;">
                      <span style="font-size: 3em; color: <?php echo esc_attr($preset['colors']['primary']); ?>;">📄</span>
                    </div>
                    <h4 style="margin: 0 0 5px 0; color: #333;"><?php echo esc_html($preset['name']); ?></h4>
                    <p style="margin: 0; font-size: 0.9em; color: #666; line-height: 1.3;"><?php echo esc_html($preset['description']); ?></p>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            
            <!-- Institution Information -->
            <div class="ppdb-section">
              <h3 class="ppdb-section-title">
                <span class="dashicons dashicons-building"></span>
                <?php esc_html_e('Informasi Institusi', 'ppdb-form'); ?>
              </h3>
              
              <table class="form-table">
                <tr>
                  <th scope="row"><?php esc_html_e('Logo Institusi', 'ppdb-form'); ?></th>
                  <td>
                    <input type="url" name="institution_logo" 
                           value="<?php echo esc_attr($active_template['institution']['logo'] ?? ''); ?>" 
                           class="regular-text" placeholder="https://domain.com/logo.png">
                    <p class="description"><?php esc_html_e('URL logo institusi. Kosongkan jika tidak ada logo.', 'ppdb-form'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Nama Institusi', 'ppdb-form'); ?></th>
                  <td>
                    <input type="text" name="institution_name" 
                           value="<?php echo esc_attr($active_template['institution']['name'] ?? get_bloginfo('name')); ?>" 
                           class="regular-text" required>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Alamat Institusi', 'ppdb-form'); ?></th>
                  <td>
                    <textarea name="institution_address" rows="3" class="regular-text"><?php echo esc_textarea($active_template['institution']['address'] ?? ''); ?></textarea>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Kontak', 'ppdb-form'); ?></th>
                  <td>
                    <input type="email" name="institution_contact" 
                           value="<?php echo esc_attr($active_template['institution']['contact'] ?? get_option('admin_email')); ?>" 
                           class="regular-text">
                    <p class="description"><?php esc_html_e('Email kontak yang akan ditampilkan di PDF.', 'ppdb-form'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Tagline (Opsional)', 'ppdb-form'); ?></th>
                  <td>
                    <input type="text" name="institution_tagline" 
                           value="<?php echo esc_attr($active_template['institution']['tagline'] ?? ''); ?>" 
                           class="regular-text" placeholder="Motto atau tagline institusi">
                  </td>
                </tr>
              </table>
            </div>
            
            <!-- Color Customization -->
            <div class="ppdb-section">
              <h3 class="ppdb-section-title">
                <span class="dashicons dashicons-art"></span>
                <?php esc_html_e('Kustomisasi Warna', 'ppdb-form'); ?>
              </h3>
              
              <table class="form-table">
                <tr>
                  <th scope="row"><?php esc_html_e('Warna Utama', 'ppdb-form'); ?></th>
                  <td>
                    <input type="color" name="color_primary" 
                           value="<?php echo esc_attr($active_template['colors']['primary'] ?? '#3b82f6'); ?>" 
                           class="ppdb-color-picker">
                    <p class="description"><?php esc_html_e('Warna untuk header dan elemen utama.', 'ppdb-form'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Warna Sekunder', 'ppdb-form'); ?></th>
                  <td>
                    <input type="color" name="color_secondary" 
                           value="<?php echo esc_attr($active_template['colors']['secondary'] ?? '#64748b'); ?>" 
                           class="ppdb-color-picker">
                    <p class="description"><?php esc_html_e('Warna untuk elemen pendukung dan border.', 'ppdb-form'); ?></p>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Warna Teks', 'ppdb-form'); ?></th>
                  <td>
                    <input type="color" name="color_text" 
                           value="<?php echo esc_attr($active_template['colors']['text'] ?? '#1f2937'); ?>" 
                           class="ppdb-color-picker">
                    <p class="description"><?php esc_html_e('Warna utama untuk teks konten.', 'ppdb-form'); ?></p>
                  </td>
                </tr>
              </table>
            </div>
            
            <!-- Field Configuration -->
            <div class="ppdb-section">
              <h3 class="ppdb-section-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('Konfigurasi Field', 'ppdb-form'); ?>
              </h3>
              
              <p class="description"><?php esc_html_e('Pilih field yang akan ditampilkan di PDF dan atur urutannya.', 'ppdb-form'); ?></p>
              
              <div class="ppdb-field-config" style="display: flex; gap: 20px; margin-top: 15px;">
                <!-- Available Fields -->
                <div style="flex: 1;">
                  <h4><?php esc_html_e('Field Tersedia', 'ppdb-form'); ?></h4>
                  <div id="ppdb-available-fields" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; min-height: 200px; background: #f9f9f9;">
                    <?php foreach ($categorized_fields as $category => $fields): ?>
                      <div class="field-category" style="margin-bottom: 15px;">
                        <h5 style="margin: 0 0 5px 0; color: #666; text-transform: uppercase; font-size: 0.8em;">
                          <?php echo esc_html(ucfirst($category)); ?>
                        </h5>
                        <?php foreach ($fields as $field): ?>
                          <?php if (!in_array($field['key'], $active_template['fields'] ?? [])): ?>
                            <div class="ppdb-field-item" data-field-key="<?php echo esc_attr($field['key']); ?>" 
                                 style="padding: 8px; margin: 2px 0; background: white; border: 1px solid #ddd; border-radius: 3px; cursor: move;">
                              <span class="dashicons dashicons-menu" style="color: #ccc; margin-right: 5px;"></span>
                              <?php echo esc_html($field['label']); ?>
                            </div>
                          <?php endif; ?>
                        <?php endforeach; ?>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
                
                <!-- Selected Fields -->
                <div style="flex: 1;">
                  <h4><?php esc_html_e('Field yang Ditampilkan', 'ppdb-form'); ?></h4>
                  <div id="ppdb-selected-fields" style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; min-height: 200px; background: #fff;">
                    <?php foreach ($active_template['fields'] ?? [] as $field_key): ?>
                      <?php if (isset($available_fields[$field_key])): ?>
                        <div class="ppdb-field-item" data-field-key="<?php echo esc_attr($field_key); ?>" 
                             style="padding: 8px; margin: 2px 0; background: #e3f2fd; border: 1px solid #2196f3; border-radius: 3px; cursor: move;">
                          <span class="dashicons dashicons-menu" style="color: #2196f3; margin-right: 5px;"></span>
                          <?php echo esc_html($available_fields[$field_key]['label']); ?>
                          <input type="hidden" name="selected_fields[]" value="<?php echo esc_attr($field_key); ?>">
                        </div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- Layout & Position -->
            <div class="ppdb-section">
              <h3 class="ppdb-section-title">
                <span class="dashicons dashicons-layout"></span>
                <?php esc_html_e('Layout & Posisi', 'ppdb-form'); ?>
              </h3>
              
              <table class="form-table">
                <tr>
                  <th scope="row"><?php esc_html_e('Posisi QR Code', 'ppdb-form'); ?></th>
                  <td>
                    <select name="qr_position" class="regular-text">
                      <option value="top_right" <?php selected($active_template['qr_position'] ?? 'bottom_right', 'top_right'); ?>>
                        <?php esc_html_e('Kanan Atas', 'ppdb-form'); ?>
                      </option>
                      <option value="bottom_right" <?php selected($active_template['qr_position'] ?? 'bottom_right', 'bottom_right'); ?>>
                        <?php esc_html_e('Kanan Bawah', 'ppdb-form'); ?>
                      </option>
                      <option value="bottom_center" <?php selected($active_template['qr_position'] ?? 'bottom_right', 'bottom_center'); ?>>
                        <?php esc_html_e('Tengah Bawah', 'ppdb-form'); ?>
                      </option>
                      <option value="bottom_left" <?php selected($active_template['qr_position'] ?? 'bottom_right', 'bottom_left'); ?>>
                        <?php esc_html_e('Kiri Bawah', 'ppdb-form'); ?>
                      </option>
                    </select>
                  </td>
                </tr>
                <tr>
                  <th scope="row"><?php esc_html_e('Footer Kustom', 'ppdb-form'); ?></th>
                  <td>
                    <textarea name="custom_footer" rows="3" class="large-text"><?php
    echo esc_textarea($active_template['custom_footer'] ?? 'Dokumen ini adalah bukti pendaftaran resmi yang sah.');
    ?></textarea>
                    <p class="description"><?php esc_html_e('Teks yang akan ditampilkan di bagian footer PDF.', 'ppdb-form'); ?></p>
                  </td>
                </tr>
              </table>
            </div>
            
            <!-- Action Buttons -->
            <div class="ppdb-section" style="text-align: center; padding: 20px; border-top: 1px solid #ddd;">
              <button type="button" id="ppdb-generate-preview" class="button button-secondary" style="margin-right: 10px;">
                <span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span>
                <?php esc_html_e('Generate Preview', 'ppdb-form'); ?>
              </button>
              
              <button type="submit" class="button button-primary" style="margin-right: 10px;">
                <span class="dashicons dashicons-saved" style="margin-right: 5px;"></span>
                <?php esc_html_e('Simpan Template', 'ppdb-form'); ?>
              </button>
              
              <button type="button" id="ppdb-reset-template" class="button button-link-delete">
                <?php esc_html_e('Reset ke Default', 'ppdb-form'); ?>
              </button>
            </div>
          </form>
        </div>
        
        <!-- Right Panel: Enhanced Preview -->
        <div class="ppdb-preview-panel" style="flex: 0 0 350px; position: sticky; top: 32px; height: fit-content;">
          <div class="ppdb-preview-container" style="border: 1px solid #e0e0e0; border-radius: 12px; background: white; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div class="ppdb-preview-header" style="padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; position: relative;">
              <h3 style="margin: 0; font-size: 1.2em; font-weight: 600;">
                <span class="dashicons dashicons-visibility" style="margin-right: 8px;"></span>
                <?php esc_html_e('Live Preview', 'ppdb-form'); ?>
              </h3>
              <p style="margin: 5px 0 0 0; font-size: 0.9em; opacity: 0.9;">Real-time PDF template preview</p>
              <div class="ppdb-preview-decoration" style="position: absolute; top: -10px; right: -10px; width: 40px; height: 40px; background: rgba(255,255,255,0.1); border-radius: 50%; opacity: 0.7;"></div>
            </div>
            <div id="ppdb-preview-content" style="min-height: 500px; background: #f8f9fb; position: relative; overflow: auto; max-height: 70vh;">
              <div class="ppdb-preview-placeholder" style="text-align: center; color: #8a8a8a; padding: 80px 20px; background: linear-gradient(45deg, #f0f2f5 25%, transparent 25%), linear-gradient(-45deg, #f0f2f5 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #f0f2f5 75%), linear-gradient(-45deg, transparent 75%, #f0f2f5 75%); background-size: 20px 20px; background-position: 0 0, 0 10px, 10px -10px, -10px 0px;">
                <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: inline-block;">
                  <span class="dashicons dashicons-media-document" style="font-size: 3.5em; display: block; margin-bottom: 15px; opacity: 0.4; color: #667eea;"></span>
                  <h4 style="margin: 0 0 8px 0; color: #333; font-size: 1.1em;">Template Preview</h4>
                  <p style="margin: 0; font-size: 0.9em; line-height: 1.4;"><?php esc_html_e('Configure your template settings and click "Generate Preview" to see how your PDF will look', 'ppdb-form'); ?></p>
                  <div style="margin-top: 15px; padding: 8px 16px; background: #e3f2fd; border-radius: 20px; font-size: 0.8em; color: #1976d2; display: inline-block;">
                    💡 Live preview updates
                  </div>
                </div>
              </div>
            </div>
            <div class="ppdb-preview-footer" style="padding: 15px; background: #f8f9fa; border-top: 1px solid #e9ecef;">
              <div style="display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.85em; color: #6c757d;">
                  <span class="dashicons dashicons-info" style="font-size: 1em;"></span>
                  <span>Preview may differ from actual PDF</span>
                </div>
                <div class="ppdb-zoom-controls" style="display: flex; align-items: center; gap: 5px;">
                  <button type="button" id="ppdb-zoom-out" class="button button-small" style="padding: 2px 6px; font-size: 11px;" title="Zoom Out">−</button>
                  <span id="ppdb-zoom-level" style="font-size: 0.8em; min-width: 35px; text-align: center; color: #6c757d;">80%</span>
                  <button type="button" id="ppdb-zoom-in" class="button button-small" style="padding: 2px 6px; font-size: 11px;" title="Zoom In">+</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <style>
      .ppdb-pdf-customizer .ppdb-section {
        background: white;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        margin-bottom: 20px;
        box-shadow: 0 1px 1px rgba(0,0,0,0.04);
      }
      
      .ppdb-section-title {
        margin: 0;
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e2e4e7;
        font-size: 1.1em;
        color: #333;
      }
      
      .ppdb-section-title .dashicons {
        margin-right: 8px;
        color: #666;
      }
      
      .ppdb-section .form-table {
        margin: 0;
        background: white;
      }
      
      .ppdb-section .form-table th,
      .ppdb-section .form-table td {
        padding: 15px 20px;
      }
      
      .ppdb-template-card:hover {
        border-color: #2196f3 !important;
        box-shadow: 0 2px 8px rgba(33, 150, 243, 0.2);
      }
      
      .ppdb-template-card input[type="radio"]:checked + .template-preview {
        background: #e3f2fd !important;
      }
      
      .ppdb-color-picker {
        width: 60px;
        height: 35px;
        border-radius: 4px;
        border: 1px solid #ddd;
        cursor: pointer;
      }
      
      .ppdb-field-item {
        user-select: none;
        transition: all 0.2s;
      }
      
      .ppdb-field-item:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      
      #ppdb-selected-fields .ppdb-field-item {
        position: relative;
      }
      
      #ppdb-selected-fields .ppdb-field-item:hover::after {
        content: "×";
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        color: #dc3545;
        font-weight: bold;
        cursor: pointer;
      }
      
      .ppdb-field-placeholder {
        height: 40px;
        background: #f0f0f0;
        border: 2px dashed #ccc;
        margin: 2px 0;
        border-radius: 3px;
      }
      
      .spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3498db;
        border-radius: 50%;
        animation: spin 2s linear infinite;
      }
      
      @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
      }
      
      .spinner.is-active {
        display: inline-block;
      }
      
      /* Enhanced Preview Animations */
      @keyframes ppdb-pulse {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.05); opacity: 0.8; }
      }
      
      @keyframes ppdb-progress {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(300%); }
      }
      
      @keyframes ppdb-fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
      }
      
      .ppdb-loading-spinner {
        display: inline-block;
        width: 12px;
        height: 12px;
        border: 2px solid #ffffff40;
        border-top: 2px solid #ffffff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-right: 5px;
      }
      
      #ppdb-preview-content {
        transition: all 0.3s ease;
      }
      
      .ppdb-pdf-preview-wrapper {
        animation: ppdb-fadeIn 0.5s ease-out;
      }
      
      /* Hover effects for template cards */
      .ppdb-template-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 20px rgba(33, 150, 243, 0.3) !important;
        transition: all 0.3s ease !important;
      }
      
      /* Enhanced button styling */
      #ppdb-generate-preview {
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
      }
      
      #ppdb-generate-preview:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      }
      
      #ppdb-generate-preview:active {
        transform: translateY(0);
      }
      
      /* Preview container enhancements */
      .ppdb-preview-container {
        transition: all 0.3s ease;
      }
      
      .ppdb-preview-container:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
      }
      
      /* Success animation for preview */
      .ppdb-preview-success {
        animation: ppdb-fadeIn 0.6s ease-out;
      }
      
      /* Responsive preview adjustments */
      @media (max-width: 1400px) {
        .ppdb-preview-panel {
          flex: 0 0 320px !important;
        }
        
        .ppdb-pdf-page {
          transform: scale(0.75) !important;
        }
      }
      
      @media (max-width: 1200px) {
        #ppdb-pdf-customizer-container {
          flex-direction: column !important;
        }
        
        .ppdb-preview-panel {
          flex: none !important;
          position: static !important;
          margin-top: 20px;
        }
      }
    </style>
    
    <script>
      jQuery(document).ready(function($) {
        // Generate Preview functionality
        $('#ppdb-generate-preview').on('click', function() {
          var $button = $(this);
          var $previewContent = $('#ppdb-preview-content');
          
          // Show enhanced loading
          $button.prop('disabled', true).html('<span class="ppdb-loading-spinner"></span> Generating...');
          $previewContent.html(`
            <div class="ppdb-loading-container" style="text-align: center; padding: 80px 20px; background: white; border-radius: 8px; margin: 20px;">
              <div class="ppdb-loading-animation" style="display: inline-block; margin-bottom: 20px;">
                <div class="ppdb-pdf-icon" style="width: 60px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin: 0 auto; position: relative; animation: ppdb-pulse 2s infinite;">
                  <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 24px;">📄</div>
                </div>
              </div>
              <h4 style="margin: 0 0 8px 0; color: #333;">Generating Preview</h4>
              <p style="margin: 0; color: #666; font-size: 0.9em;">Processing template with your settings...</p>
              <div class="ppdb-progress-bar" style="width: 200px; height: 4px; background: #f0f0f0; border-radius: 2px; margin: 20px auto; overflow: hidden;">
                <div class="ppdb-progress-fill" style="height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 2px; animation: ppdb-progress 2s infinite;"></div>
              </div>
            </div>
          `);
          
          // Collect form data
          var formData = {
            action: 'ppdb_pdf_preview',
            nonce: '<?php echo wp_create_nonce('ppdb_pdf_preview'); ?>',
            preset_type: $('input[name="preset_type"]:checked').val() || 'default',
            color_primary: $('input[name="color_primary"]').val() || '#3b82f6',
            color_secondary: $('input[name="color_secondary"]').val() || '#64748b',
            color_text: $('input[name="color_text"]').val() || '#1f2937',
            qr_position: $('select[name="qr_position"]').val() || 'bottom_right',
            institution_name: $('input[name="institution_name"]').val() || '<?php echo esc_js(get_bloginfo('name')); ?>',
            institution_logo: $('input[name="institution_logo"]').val() || '',
            institution_address: $('textarea[name="institution_address"]').val() || '',
            institution_contact: $('input[name="institution_contact"]').val() || '<?php echo esc_js(get_option('admin_email')); ?>',
            institution_tagline: $('input[name="institution_tagline"]').val() || '',
            custom_footer: $('textarea[name="custom_footer"]').val() || 'Dokumen ini adalah bukti pendaftaran resmi yang sah.',
            selected_fields: []
          };
          
          // Collect selected fields (fallback to default if none selected)
          $('#ppdb-selected-fields input[name="selected_fields[]"]').each(function() {
            formData.selected_fields.push($(this).val());
          });
          
          // Fallback to default fields if none selected
          if (formData.selected_fields.length === 0) {
            formData.selected_fields = ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan'];
          }
          
          // Make AJAX request
          $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
              console.log('Preview response:', response); // Debug
              if (response.success) {
                if (response.data.preview_html) {
                  // Add success animation
                  $previewContent.fadeOut(200, function() {
                    $(this).html('<div class="ppdb-preview-success">' + response.data.preview_html + '</div>').fadeIn(300);
                    
                    // Add success notification
                    var $notification = $('<div class="ppdb-success-notification" style="position: absolute; top: 20px; right: 20px; background: #4caf50; color: white; padding: 8px 16px; border-radius: 20px; font-size: 0.85em; z-index: 1000; animation: ppdb-fadeIn 0.3s ease;">✓ Preview updated</div>');
                    $('.ppdb-preview-container').append($notification);
                    
                    setTimeout(function() {
                      $notification.fadeOut(300, function() { $(this).remove(); });
                    }, 2000);
                  });
                } else {
                  $previewContent.html('<div style="text-align: center; padding: 50px; color: #dc3545;"><p>Preview HTML is empty</p></div>');
                }
              } else {
                console.error('Preview error:', response.data);
                var errorHtml = `
                  <div style="text-align: center; padding: 50px 20px;">
                    <div style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(220,53,69,0.1); display: inline-block;">
                      <span style="font-size: 3em; display: block; margin-bottom: 15px; color: #dc3545;">⚠️</span>
                      <h4 style="margin: 0 0 8px 0; color: #dc3545;">Preview Error</h4>
                      <p style="margin: 0; color: #666; font-size: 0.9em;">${response.data.message || 'Unknown error'}</p>
                    </div>
                  </div>
                `;
                $previewContent.html(errorHtml);
              }
            },
            error: function() {
              $previewContent.html('<div style="text-align: center; padding: 50px; color: #dc3545;"><p>Network error occurred</p></div>');
            },
            complete: function() {
              $button.prop('disabled', false).html('<span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span>Generate Preview');
            }
          });
        });
        
        // Drag and drop for fields (basic implementation)
        if (typeof $.fn.sortable !== 'undefined') {
          $('#ppdb-available-fields, #ppdb-selected-fields').sortable({
            connectWith: '#ppdb-available-fields, #ppdb-selected-fields',
            placeholder: 'ppdb-field-placeholder',
            receive: function(event, ui) {
              // Update hidden inputs when field is moved
              updateSelectedFields();
            },
            update: function(event, ui) {
              updateSelectedFields();
            }
          }).disableSelection();
        }
        
        function updateSelectedFields() {
          $('#ppdb-selected-fields input[name="selected_fields[]"]').remove();
          $('#ppdb-selected-fields .ppdb-field-item').each(function() {
            var fieldKey = $(this).data('field-key');
            if (fieldKey) {
              $(this).append('<input type="hidden" name="selected_fields[]" value="' + fieldKey + '">');
            }
          });
        }
        
        // Preset selection change
        $('input[name="preset_type"]').on('change', function() {
          var presetType = $(this).val();
          
          // Update colors based on preset (you can expand this)
          var presetColors = {
            'default': { primary: '#3b82f6', secondary: '#64748b', text: '#1f2937' },
            'modern': { primary: '#10b981', secondary: '#374151', text: '#111827' },
            'classic': { primary: '#dc2626', secondary: '#1f2937', text: '#000000' },
            'academic': { primary: '#7c3aed', secondary: '#4b5563', text: '#1f2937' }
          };
          
          if (presetColors[presetType]) {
            $('input[name="color_primary"]').val(presetColors[presetType].primary);
            $('input[name="color_secondary"]').val(presetColors[presetType].secondary);
            $('input[name="color_text"]').val(presetColors[presetType].text);
          }
        });
        
        // Zoom functionality
        var currentZoom = 0.8;
        var minZoom = 0.4;
        var maxZoom = 1.2;
        var zoomStep = 0.1;
        
        function updateZoom() {
          var $previewPage = $('.ppdb-pdf-page');
          var zoomPercent = Math.round(currentZoom * 100);
          
          $previewPage.css('transform', 'scale(' + currentZoom + ')');
          $('#ppdb-zoom-level').text(zoomPercent + '%');
          
          // Update zoom controls state
          $('#ppdb-zoom-out').prop('disabled', currentZoom <= minZoom);
          $('#ppdb-zoom-in').prop('disabled', currentZoom >= maxZoom);
          
          // Update scale indicator in preview header
          $('.ppdb-scale-indicator').text(zoomPercent + '% Scale');
        }
        
        $('#ppdb-zoom-in').on('click', function() {
          if (currentZoom < maxZoom) {
            currentZoom = Math.min(currentZoom + zoomStep, maxZoom);
            updateZoom();
          }
        });
        
        $('#ppdb-zoom-out').on('click', function() {
          if (currentZoom > minZoom) {
            currentZoom = Math.max(currentZoom - zoomStep, minZoom);
            updateZoom();
          }
        });
        
        // Initialize zoom controls
        updateZoom();
      });
    </script>
    <?php
  }

  /**
   * Handle form submission for template settings
   */
  private static function handle_form_submission(): void
  {
    $action = sanitize_text_field($_POST['action'] ?? '');

    switch ($action) {
      case 'save_template':
        self::handle_save_template();
        break;
      case 'activate_template':
        self::handle_activate_template();
        break;
      case 'delete_template':
        self::handle_delete_template();
        break;
    }
  }

  /**
   * Handle save template action
   */
  private static function handle_save_template(): void
  {
    $config = [
      'name' => sanitize_text_field($_POST['template_name'] ?? 'Custom Template'),
      'preset_type' => sanitize_text_field($_POST['preset_type'] ?? 'custom'),
      'colors' => [
        'primary' => sanitize_hex_color($_POST['color_primary'] ?? '#3b82f6'),
        'secondary' => sanitize_hex_color($_POST['color_secondary'] ?? '#64748b'),
        'text' => sanitize_hex_color($_POST['color_text'] ?? '#1f2937')
      ],
      'layout' => sanitize_text_field($_POST['layout'] ?? 'standard'),
      'header_style' => sanitize_text_field($_POST['header_style'] ?? 'logo_center'),
      'qr_position' => sanitize_text_field($_POST['qr_position'] ?? 'bottom_right'),
      'fields' => array_map('sanitize_text_field', $_POST['selected_fields'] ?? []),
      'institution' => [
        'name' => sanitize_text_field($_POST['institution_name'] ?? get_bloginfo('name')),
        'logo' => esc_url_raw($_POST['institution_logo'] ?? ''),
        'address' => sanitize_textarea_field($_POST['institution_address'] ?? ''),
        'contact' => sanitize_email($_POST['institution_contact'] ?? get_option('admin_email')),
        'tagline' => sanitize_text_field($_POST['institution_tagline'] ?? '')
      ],
      'custom_footer' => sanitize_textarea_field($_POST['custom_footer'] ?? ''),
      'is_active' => 1
    ];

    $template_id = PPDB_Form_PDF_Template::save_template_config($config);

    if ($template_id > 0) {
      add_action('admin_notices', function () {
        echo '<div class="updated"><p>' . esc_html__('Template PDF berhasil disimpan dan diaktifkan.', 'ppdb-form') . '</p></div>';
      });
    } else {
      add_action('admin_notices', function () {
        echo '<div class="error"><p>' . esc_html__('Gagal menyimpan template PDF.', 'ppdb-form') . '</p></div>';
      });
    }
  }

  /**
   * AJAX handler for generating PDF preview
   */
  public static function ajax_generate_preview(): void
  {
    try {
      if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
      }

      check_ajax_referer('ppdb_pdf_preview', 'nonce');

      // Get configuration from POST data
      $config = [
        'colors' => [
          'primary' => sanitize_hex_color($_POST['color_primary'] ?? '#3b82f6'),
          'secondary' => sanitize_hex_color($_POST['color_secondary'] ?? '#64748b'),
          'text' => sanitize_hex_color($_POST['color_text'] ?? '#1f2937')
        ],
        'preset_type' => sanitize_text_field($_POST['preset_type'] ?? 'default'),
        'qr_position' => sanitize_text_field($_POST['qr_position'] ?? 'bottom_right'),
        'fields' => array_map('sanitize_text_field', $_POST['selected_fields'] ?? ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan']),
        'institution' => [
          'name' => sanitize_text_field($_POST['institution_name'] ?? get_bloginfo('name')),
          'logo' => esc_url_raw($_POST['institution_logo'] ?? ''),
          'address' => sanitize_textarea_field($_POST['institution_address'] ?? ''),
          'contact' => sanitize_email($_POST['institution_contact'] ?? get_option('admin_email')),
          'tagline' => sanitize_text_field($_POST['institution_tagline'] ?? '')
        ],
        'custom_footer' => sanitize_textarea_field($_POST['custom_footer'] ?? '')
      ];

      // Ensure we have at least some fields
      if (empty($config['fields'])) {
        $config['fields'] = ['nama_lengkap', 'email', 'nomor_telepon', 'jurusan'];
      }

      // Generate preview HTML with dummy data
      error_log('PPDB Form: Generating preview with config: ' . json_encode($config));
      $preview_html = self::generate_preview_html($config);
      error_log('PPDB Form: Preview HTML length: ' . strlen($preview_html));

      if (empty($preview_html)) {
        error_log('PPDB Form: Preview HTML is empty');
        wp_send_json_error(['message' => 'Failed to generate preview HTML']);
        return;
      }

      wp_send_json_success([
        'preview_html' => $preview_html,
        'message' => 'Preview generated successfully',
        'config' => $config  // Debug info
      ]);
    } catch (Exception $e) {
      error_log('PPDB Form Preview Error: ' . $e->getMessage());
      wp_send_json_error(['message' => 'Error generating preview: ' . $e->getMessage()]);
    }
  }

  /**
   * Generate preview HTML with dummy data using template system
   */
  private static function generate_preview_html(array $config): string
  {
    // Use enhanced template system for preview
    if (class_exists('PPDB_Form_PDF_Generator')) {
      $preview_html = PPDB_Form_PDF_Generator::generate_preview_html($config);
      if (!empty($preview_html)) {
        return $preview_html;
      }

      // Log if template system failed
      error_log('PPDB Form: Template system failed, falling back to simple preview');
    }

    // Fallback to simple preview
    return self::generate_simple_preview($config);
  }

  /**
   * Simple preview fallback
   */
  private static function generate_simple_preview(array $config): string
  {
    $dummy_data = [
      'nama_lengkap' => 'John Doe',
      'email' => 'john.doe@email.com',
      'nomor_telepon' => '08123456789',
      'jurusan' => 'Teknik Informatika',
      'tanggal_lahir' => '2005-01-15',
      'alamat' => 'Jl. Contoh No. 123, Jakarta',
      'asal_sekolah' => 'SMA Negeri 1 Jakarta'
    ];

    $reg_number = 'REG2024000001';

    ob_start();
    ?>
    <div class="ppdb-pdf-preview" style="font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; color: <?php echo esc_attr($config['colors']['text']); ?>; border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: white;">
      <!-- Header -->
      <div class="pdf-header" style="text-align: center; padding-bottom: 20px; border-bottom: 2px solid <?php echo esc_attr($config['colors']['primary']); ?>; margin-bottom: 20px;">
        <?php if (!empty($config['institution']['logo'])): ?>
          <img src="<?php echo esc_url($config['institution']['logo']); ?>" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <h1 style="margin: 0; color: <?php echo esc_attr($config['colors']['primary']); ?>; font-size: 18px;">
          <?php echo esc_html($config['institution']['name']); ?>
        </h1>
        <?php if (!empty($config['institution']['tagline'])): ?>
          <p style="margin: 5px 0 0 0; color: <?php echo esc_attr($config['colors']['secondary']); ?>; font-style: italic;">
            <?php echo esc_html($config['institution']['tagline']); ?>
          </p>
        <?php endif; ?>
        <h2 style="margin: 15px 0 5px 0; color: <?php echo esc_attr($config['colors']['text']); ?>; font-size: 16px;">
          BUKTI PENDAFTARAN
        </h2>
        <p style="margin: 0; color: <?php echo esc_attr($config['colors']['secondary']); ?>;">
          No. Registrasi: <strong><?php echo esc_html($reg_number); ?></strong>
        </p>
      </div>
      
      <!-- Content -->
      <div class="pdf-content" style="margin-bottom: 30px;">
        <h3 style="margin: 0 0 15px 0; color: <?php echo esc_attr($config['colors']['primary']); ?>; font-size: 14px; border-bottom: 1px solid <?php echo esc_attr($config['colors']['secondary']); ?>; padding-bottom: 5px;">
          Data Pendaftar
        </h3>
        
        <table style="width: 100%; border-collapse: collapse;">
          <?php foreach ($config['fields'] as $field_key): ?>
            <?php if (isset($dummy_data[$field_key])): ?>
              <tr>
                <td style="padding: 8px 0; width: 40%; color: <?php echo esc_attr($config['colors']['secondary']); ?>;">
                  <?php echo esc_html(ucfirst(str_replace('_', ' ', $field_key))); ?>:
                </td>
                <td style="padding: 8px 0; font-weight: bold;">
                  <?php echo esc_html($dummy_data[$field_key]); ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
          <tr>
            <td style="padding: 8px 0; color: <?php echo esc_attr($config['colors']['secondary']); ?>;">
              Tanggal Pendaftaran:
            </td>
            <td style="padding: 8px 0; font-weight: bold;">
              <?php echo esc_html(date('d F Y')); ?>
            </td>
          </tr>
        </table>
      </div>
      
      <!-- QR Code Section -->
      <div class="pdf-qr" style="text-align: <?php echo $config['qr_position'] === 'bottom_center' ? 'center' : ($config['qr_position'] === 'bottom_left' ? 'left' : 'right'); ?>; margin-bottom: 20px;">
        <div style="display: inline-block; text-align: center;">
          <div style="width: 80px; height: 80px; background: #f0f0f0; border: 1px solid <?php echo esc_attr($config['colors']['secondary']); ?>; display: flex; align-items: center; justify-content: center; margin-bottom: 5px;">
            <span style="font-size: 10px; color: <?php echo esc_attr($config['colors']['secondary']); ?>;">QR Code</span>
          </div>
          <p style="margin: 0; font-size: 10px; color: <?php echo esc_attr($config['colors']['secondary']); ?>;">
            Scan untuk verifikasi
          </p>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="pdf-footer" style="border-top: 1px solid <?php echo esc_attr($config['colors']['secondary']); ?>; padding-top: 15px; text-align: center;">
        <?php if (!empty($config['custom_footer'])): ?>
          <p style="margin: 0 0 10px 0; color: <?php echo esc_attr($config['colors']['secondary']); ?>; font-size: 11px;">
            <?php echo esc_html($config['custom_footer']); ?>
          </p>
        <?php endif; ?>
        
        <?php if (!empty($config['institution']['address']) || !empty($config['institution']['contact'])): ?>
          <div style="color: <?php echo esc_attr($config['colors']['secondary']); ?>; font-size: 10px;">
            <?php if (!empty($config['institution']['address'])): ?>
              <p style="margin: 0;"><?php echo esc_html($config['institution']['address']); ?></p>
            <?php endif; ?>
            <?php if (!empty($config['institution']['contact'])): ?>
              <p style="margin: 0;">Email: <?php echo esc_html($config['institution']['contact']); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return ob_get_clean();
  }

  /**
   * AJAX handler for activating template
   */
  public static function ajax_activate_template(): void
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    check_ajax_referer('ppdb_pdf_activate', 'nonce');

    $template_id = (int) ($_POST['template_id'] ?? 0);

    if ($template_id <= 0) {
      wp_send_json_error(['message' => 'Invalid template ID']);
    }

    $success = PPDB_Form_PDF_Template::activate_template($template_id);

    if ($success) {
      wp_send_json_success(['message' => 'Template activated successfully']);
    } else {
      wp_send_json_error(['message' => 'Failed to activate template']);
    }
  }

  /**
   * AJAX handler for deleting template
   */
  public static function ajax_delete_template(): void
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    check_ajax_referer('ppdb_pdf_delete', 'nonce');

    $template_id = (int) ($_POST['template_id'] ?? 0);

    if ($template_id <= 0) {
      wp_send_json_error(['message' => 'Invalid template ID']);
    }

    $success = PPDB_Form_PDF_Template::delete_template($template_id);

    if ($success) {
      wp_send_json_success(['message' => 'Template deleted successfully']);
    } else {
      wp_send_json_error(['message' => 'Cannot delete active template or template not found']);
    }
  }

  /**
   * AJAX handler for loading preset configuration
   */
  public static function ajax_load_preset(): void
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    check_ajax_referer('ppdb_pdf_load_preset', 'nonce');

    $preset_type = sanitize_text_field($_POST['preset_type'] ?? 'default');
    $presets = PPDB_Form_PDF_Template::get_available_presets();

    if (!isset($presets[$preset_type])) {
      wp_send_json_error(['message' => 'Invalid preset type']);
    }

    $preset_config = $presets[$preset_type];

    wp_send_json_success([
      'config' => $preset_config,
      'message' => 'Preset loaded successfully'
    ]);
  }
}
