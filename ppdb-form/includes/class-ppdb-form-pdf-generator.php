<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * PDF Generator for PPDB Form Certificates
 * Enhanced with template system support
 */
class PPDB_Form_PDF_Generator
{
  /**
   * Generate PDF certificate using template system
   */
  public static function generate_certificate_pdf(int $submission_id): string
  {
    // Get active template configuration
    $template_config = PPDB_Form_PDF_Template::get_active_template();

    // Method 1: Try using template-based HTML generation
    $template_html = self::generate_template_certificate($submission_id, $template_config);
    if (!empty($template_html)) {
      return $template_html;
    }

    // Method 2: Try using TCPDF if available
    if (self::can_use_tcpdf()) {
      return self::generate_tcpdf_certificate($submission_id);
    }

    // Method 3: Try using DomPDF if available
    if (self::can_use_dompdf()) {
      return self::generate_dompdf_certificate($submission_id);
    }

    // Method 4: Fallback to HTML with print styles
    return self::generate_html_pdf_certificate($submission_id);
  }

  /**
   * Generate certificate using template system
   */
  public static function generate_template_certificate(int $submission_id, array $template_config = []): string
  {
    global $wpdb;

    // Get submission data
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';
    $submission = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$submissions_table} WHERE id = %d",
      $submission_id
    ));

    if (!$submission) {
      return '';
    }

    // Parse submission data
    $submission_data = json_decode($submission->submission_data, true) ?: [];
    $registration_number = self::get_registration_number($submission_id);
    $submission_date = $submission->created_at;

    // Get template configuration if not provided
    if (empty($template_config)) {
      $template_config = PPDB_Form_PDF_Template::get_active_template();
    }

    $preset_type = $template_config['preset_type'] ?? 'default';

    // Load template file
    $template_file = self::get_template_file($preset_type);

    if (!file_exists($template_file)) {
      // Fallback to default template
      $template_file = self::get_template_file('default');
      if (!file_exists($template_file)) {
        return '';  // No template available
      }
    }

    // Extract variables for template
    extract([
      'template_config' => $template_config,
      'submission_data' => $submission_data,
      'submission_id' => $submission_id,
      'registration_number' => $registration_number,
      'submission_date' => $submission_date
    ]);

    // Buffer template output
    ob_start();
    include $template_file;
    return ob_get_clean();
  }

  /**
   * Get template file path
   */
  private static function get_template_file(string $preset_type): string
  {
    $safe_preset = sanitize_file_name($preset_type);
    return PPDB_FORM_DIR . "templates/pdf/{$safe_preset}.php";
  }

  /**
   * Check if TCPDF is available
   */
  private static function can_use_tcpdf(): bool
  {
    return class_exists('TCPDF');
  }

  /**
   * Check if DomPDF is available
   */
  private static function can_use_dompdf(): bool
  {
    return class_exists('Dompdf\Dompdf');
  }

  /**
   * Generate PDF using TCPDF
   */
  private static function generate_tcpdf_certificate(int $submission_id): string
  {
    // TODO: Implement TCPDF generation
    // For now, fallback to HTML
    return self::generate_html_pdf_certificate($submission_id);
  }

  /**
   * Generate PDF using DomPDF
   */
  private static function generate_dompdf_certificate(int $submission_id): string
  {
    // TODO: Implement DomPDF generation
    // For now, fallback to HTML
    return self::generate_html_pdf_certificate($submission_id);
  }

  /**
   * Generate HTML optimized for PDF printing (fallback method)
   */
  private static function generate_html_pdf_certificate(int $submission_id): string
  {
    // Try template system first
    $template_html = self::generate_template_certificate($submission_id);
    if (!empty($template_html)) {
      return $template_html;
    }

    // Get certificate HTML from main certificate class as fallback
    if (!class_exists('PPDB_Form_Certificate')) {
      return '';
    }

    $certificate_html = PPDB_Form_Certificate::generate_certificate($submission_id);

    if (empty($certificate_html)) {
      return '';
    }

    // Wrap in PDF-optimized HTML structure
    $pdf_html = self::wrap_for_pdf($certificate_html, $submission_id);

    return $pdf_html;
  }

  /**
   * Wrap certificate HTML for PDF generation
   */
  private static function wrap_for_pdf(string $certificate_html, int $submission_id): string
  {
    global $wpdb;

    // Get submission data for filename
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';
    $submission = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$submissions_table} WHERE id = %d",
      $submission_id
    ));

    $data = $submission ? json_decode($submission->submission_data, true) : [];
    $nama = $data['nama_lengkap'] ?? 'Pendaftar';
    $reg_number = self::get_registration_number($submission_id);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Bukti Pendaftaran - <?php echo esc_html($nama); ?></title>
      <style>
        @media print {
          body { margin: 0; padding: 0; }
          .no-print { display: none !important; }
          .page-break { page-break-before: always; }
        }
        
        body {
          font-family: 'Arial', 'Helvetica', sans-serif;
          margin: 0;
          padding: 20px;
          background: white;
          color: #333;
          line-height: 1.4;
        }
        
        .pdf-container {
          max-width: 210mm; /* A4 width */
          margin: 0 auto;
          background: white;
          min-height: 297mm; /* A4 height */
          padding: 20mm;
          box-sizing: border-box;
        }
        
        .pdf-header {
          text-align: center;
          margin-bottom: 30px;
          border-bottom: 2px solid #333;
          padding-bottom: 20px;
        }
        
        .pdf-title {
          font-size: 24px;
          font-weight: bold;
          margin: 10px 0;
          text-transform: uppercase;
          letter-spacing: 2px;
        }
        
        .pdf-content {
          margin: 20px 0;
        }
        
        .pdf-footer {
          position: absolute;
          bottom: 20mm;
          left: 20mm;
          right: 20mm;
          border-top: 1px solid #ccc;
          padding-top: 15px;
          display: flex;
          justify-content: space-between;
          align-items: flex-end;
        }
        
        .qr-section {
          text-align: center;
        }
        
        .qr-section img {
          width: 80px;
          height: 80px;
          border: 1px solid #ccc;
        }
        
        .signature-section {
          text-align: center;
          min-width: 150px;
        }
        
        .signature-line {
          width: 150px;
          height: 60px;
          border-bottom: 1px solid #333;
          margin-bottom: 10px;
        }
        
        .verification-info {
          font-size: 10px;
          color: #666;
          margin-top: 20px;
          text-align: center;
        }
        
        /* Override certificate styles for PDF */
        .ppdb-certificate {
          border: none !important;
          margin: 0 !important;
          padding: 0 !important;
          max-width: none !important;
        }
        
        .ppdb-actions {
          display: none !important;
        }
        
        .no-print {
          display: none !important;
        }
      </style>
    </head>
    <body>
      <div class="pdf-container">
        <?php echo $certificate_html; ?>
        
        <!-- PDF-specific footer -->
        <div class="verification-info">
          <p><strong>Dokumen ini digenerate secara otomatis pada:</strong> <?php echo esc_html(current_time('d F Y H:i:s')); ?></p>
          <p><strong>Verifikasi online:</strong> <?php echo esc_url(home_url('?ppdb_verify=1')); ?></p>
        </div>
      </div>
      
      <script>
        // Auto-trigger print dialog for PDF generation
        document.addEventListener('DOMContentLoaded', function() {
          const urlParams = new URLSearchParams(window.location.search);
          if (urlParams.has('auto_print')) {
            setTimeout(function() {
              window.print();
            }, 1000);
          }
        });
      </script>
    </body>
    </html>
    <?php

    return ob_get_clean();
  }

  /**
   * Get registration number for submission
   */
  private static function get_registration_number(int $submission_id): string
  {
    $prefix = get_option('ppdb_reg_number_prefix', 'REG');
    $year = date('Y');
    $padded_id = str_pad((string) $submission_id, 6, '0', STR_PAD_LEFT);

    return $prefix . $year . $padded_id;
  }

  /**
   * Generate PDF download URL
   */
  public static function get_pdf_download_url(int $submission_id): string
  {
    return add_query_arg([
      'ppdb_pdf' => 1,
      'sid' => $submission_id,
      'hash' => wp_hash($submission_id . '|pdf|' . wp_salt('auth'))
    ], home_url());
  }

  /**
   * Handle PDF download request
   */
  public static function handle_pdf_request(): void
  {
    if (!isset($_GET['ppdb_pdf'], $_GET['sid'], $_GET['hash'])) {
      return;
    }

    $submission_id = (int) $_GET['sid'];
    $provided_hash = (string) $_GET['hash'];
    $expected_hash = wp_hash($submission_id . '|pdf|' . wp_salt('auth'));

    if (!hash_equals($expected_hash, $provided_hash)) {
      wp_die('Hash tidak valid');
    }

    $pdf_html = self::generate_certificate_pdf($submission_id);

    if (empty($pdf_html)) {
      wp_die('Certificate tidak ditemukan');
    }

    // Set headers for PDF display
    $reg_number = self::get_registration_number($submission_id);
    $filename = "bukti-pendaftaran-{$reg_number}.html";

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Add auto-print parameter for PDF generation
    if (!isset($_GET['auto_print'])) {
      $current_url = add_query_arg('auto_print', '1');
      echo '<script>window.location.href = "' . esc_js($current_url) . '";</script>';
      exit;
    }

    echo $pdf_html;
    exit;
  }

  /**
   * Add PDF download option to certificate buttons
   */
  public static function get_pdf_button_html(int $submission_id): string
  {
    $pdf_url = self::get_pdf_download_url($submission_id);

    return '<a href="' . esc_url($pdf_url) . '" target="_blank" class="ppdb-btn ppdb-btn-pdf" style="display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 0 5px;">'
      . '📄 ' . esc_html__('Download PDF', 'ppdb-form')
      . '</a>';
  }

  /**
   * Generate preview HTML for admin (without QR code and signatures)
   */
  public static function generate_preview_html(array $template_config, array $dummy_data = []): string
  {
    // Use dummy data if not provided
    if (empty($dummy_data)) {
      $dummy_data = [
        'nama_lengkap' => 'John Doe',
        'email' => 'john.doe@email.com',
        'nomor_telepon' => '08123456789',
        'jurusan' => 'Teknik Informatika',
        'tanggal_lahir' => '2005-01-15',
        'alamat' => 'Jl. Contoh No. 123, Jakarta',
        'asal_sekolah' => 'SMA Negeri 1 Jakarta',
        'jenis_kelamin' => 'Laki-laki'
      ];
    }

    $preset_type = $template_config['preset_type'] ?? 'default';
    $registration_number = 'REG2024000001';
    $submission_date = current_time('mysql');
    $submission_id = 1;

    // Load template file
    $template_file = self::get_template_file($preset_type);

    if (!file_exists($template_file)) {
      $template_file = self::get_template_file('default');
      if (!file_exists($template_file)) {
        // Log error for debugging
        error_log('PPDB Form: Template file not found: ' . $template_file);
        return '<div style="text-align: center; padding: 50px; color: #666;">Template tidak ditemukan: ' . esc_html($preset_type) . '</div>';
      }
    }

    // Extract variables for template
    extract([
      'template_config' => $template_config,
      'submission_data' => $dummy_data,
      'submission_id' => $submission_id,
      'registration_number' => $registration_number,
      'submission_date' => $submission_date
    ]);

    // Buffer template output
    ob_start();
    try {
      include $template_file;
      $template_html = ob_get_clean();

      if (empty($template_html)) {
        return '<div style="text-align: center; padding: 50px; color: #dc3545;">Template generated empty output</div>';
      }

      // Wrap in enhanced preview container
      return '<div class="ppdb-pdf-preview-wrapper" style="padding: 20px; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.1);">'
        . '<div class="ppdb-pdf-page" style="background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); transform: scale(0.8); transform-origin: top center; margin: 0 auto; max-width: 600px; overflow: hidden; position: relative;">'
        . '<div class="ppdb-pdf-header" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 12px 15px; border-bottom: 1px solid #dee2e6; font-size: 12px; color: #495057; display: flex; justify-content: space-between; align-items: center; position: relative;">'
        . '<div style="display: flex; align-items: center; gap: 8px;">'
        . '<span class="ppdb-template-badge" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4px 10px; border-radius: 12px; font-size: 10px; font-weight: 600; text-transform: uppercase;">' . esc_html($config['preset_type'] ?? 'default') . '</span>'
        . '<span><strong>Live Preview</strong></span>'
        . '</div>'
        . '<div style="display: flex; align-items: center; gap: 8px;">'
        . '<span class="ppdb-page-indicator" style="background: #28a745; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px; font-weight: 600;">A4 Size</span>'
        . '<span class="ppdb-scale-indicator" style="background: #6c757d; color: white; padding: 3px 8px; border-radius: 10px; font-size: 9px;">80% Scale</span>'
        . '</div>'
        . '</div>'
        . '<div class="ppdb-pdf-content" style="padding: 20px; min-height: 400px;">'
        . $template_html
        . '</div>'
        . '</div>'
        . '</div>';
    } catch (Exception $e) {
      ob_end_clean();
      error_log('PPDB Form Template Error: ' . $e->getMessage());
      return '<div style="text-align: center; padding: 50px; color: #dc3545;">Template error: ' . esc_html($e->getMessage()) . '</div>';
    }
  }
}

// Hook PDF request handler
add_action('init', ['PPDB_Form_PDF_Generator', 'handle_pdf_request']);
