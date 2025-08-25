<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * PPDB Form Certificate Generator
 * Generates registration certificates/receipts for submissions
 */
class PPDB_Form_Certificate
{
  /**
   * Generate registration certificate using template system
   */
  public static function generate_certificate(int $submission_id): string
  {
    // Use new template-based PDF generator
    if (class_exists('PPDB_Form_PDF_Generator')) {
      $template_html = PPDB_Form_PDF_Generator::generate_template_certificate($submission_id);
      if (!empty($template_html)) {
        return $template_html;
      }
    }

    // Fallback to legacy method
    return self::generate_legacy_certificate($submission_id);
  }

  /**
   * Legacy certificate generation (fallback)
   */
  private static function generate_legacy_certificate(int $submission_id): string
  {
    global $wpdb;

    $submissions_table = $wpdb->prefix . 'ppdb_submissions';
    $forms_table = $wpdb->prefix . 'ppdb_forms';

    // Get submission data with form info
    $sql = "SELECT s.*, f.name as form_name 
            FROM {$submissions_table} s 
            LEFT JOIN {$forms_table} f ON s.form_id = f.id 
            WHERE s.id = %d";

    $submission = $wpdb->get_row($wpdb->prepare($sql, $submission_id));

    if (!$submission) {
      return '';
    }

    $data = json_decode($submission->submission_data, true) ?: [];

    // Generate unique registration number
    $reg_number = self::generate_registration_number($submission_id);

    // Generate verification hash
    $verification_hash = self::generate_verification_hash($submission_id, $reg_number);

    // Generate PDF or HTML certificate
    $certificate_type = get_option('ppdb_certificate_type', 'html');  // html|pdf

    if ($certificate_type === 'pdf') {
      return self::generate_pdf_certificate($submission, $data, $reg_number, $verification_hash);
    } else {
      return self::generate_html_certificate($submission, $data, $reg_number, $verification_hash);
    }
  }

  /**
   * Generate unique registration number
   */
  private static function generate_registration_number(int $submission_id): string
  {
    $prefix = get_option('ppdb_reg_number_prefix', 'REG');
    $year = date('Y');
    $padded_id = str_pad((string) $submission_id, 6, '0', STR_PAD_LEFT);

    return $prefix . $year . $padded_id;
  }

  /**
   * Generate verification hash for certificate authenticity
   */
  private static function generate_verification_hash(int $submission_id, string $reg_number): string
  {
    $secret = wp_salt('auth') . get_option('ppdb_certificate_secret', wp_generate_password(32, false));
    return wp_hash($submission_id . '|' . $reg_number . '|' . $secret);
  }

  /**
   * Generate HTML certificate (printable)
   */
  private static function generate_html_certificate($submission, array $data, string $reg_number, string $verification_hash): string
  {
    $institution_name = get_option('ppdb_institution_name', get_bloginfo('name'));
    $institution_logo = get_option('ppdb_institution_logo', '');
    $certificate_title = get_option('ppdb_certificate_title', 'BUKTI PENDAFTARAN');

    ob_start();
    ?>
    <div id="ppdb-certificate" class="ppdb-certificate" style="max-width: 800px; margin: 0 auto; padding: 40px; border: 2px solid #333; font-family: Arial, sans-serif; background: #fff;">
      <style>
        @media print {
          body * { visibility: hidden; }
          #ppdb-certificate, #ppdb-certificate * { visibility: visible; }
          #ppdb-certificate { position: absolute; left: 0; top: 0; width: 100%; }
          .no-print { display: none !important; }
        }
        .ppdb-certificate { line-height: 1.6; color: #333; }
        .ppdb-header { text-align: center; margin-bottom: 30px; border-bottom: 1px solid #ccc; padding-bottom: 20px; }
        .ppdb-logo { max-width: 100px; height: auto; margin-bottom: 10px; }
        .ppdb-title { font-size: 24px; font-weight: bold; margin: 10px 0; text-transform: uppercase; }
        .ppdb-subtitle { font-size: 16px; color: #666; }
        .ppdb-reg-number { font-size: 20px; font-weight: bold; color: #2563eb; margin: 20px 0; }
        .ppdb-data { margin: 20px 0; }
        .ppdb-data-row { display: flex; margin: 8px 0; }
        .ppdb-data-label { width: 200px; font-weight: bold; }
        .ppdb-data-value { flex: 1; }
        .ppdb-footer { margin-top: 40px; border-top: 1px solid #ccc; padding-top: 20px; display: flex; justify-content: space-between; }
        .ppdb-qr { text-align: center; }
        .ppdb-signature { text-align: center; }
        .ppdb-verification { font-size: 12px; color: #666; margin-top: 20px; text-align: center; }
        .ppdb-actions { margin: 20px 0; text-align: center; }
        .ppdb-btn { padding: 10px 20px; margin: 0 5px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .ppdb-btn-print { background: #2563eb; color: white; }
        .ppdb-btn-download { background: #059669; color: white; }
      </style>
      
      <!-- Header -->
      <div class="ppdb-header">
        <?php if ($institution_logo): ?>
          <img src="<?php echo esc_url($institution_logo); ?>" alt="Logo" class="ppdb-logo">
        <?php endif; ?>
        <h1 class="ppdb-title"><?php echo esc_html($certificate_title); ?></h1>
        <p class="ppdb-subtitle"><?php echo esc_html($institution_name); ?></p>
      </div>
      
      <!-- Registration Number -->
      <div style="text-align: center;">
        <div class="ppdb-reg-number">No. Registrasi: <?php echo esc_html($reg_number); ?></div>
      </div>
      
      <!-- Submission Data -->
      <div class="ppdb-data">
        <h3 style="margin-bottom: 15px;">Data Pendaftar:</h3>
        
        <?php if (!empty($data['nama_lengkap'])): ?>
        <div class="ppdb-data-row">
          <div class="ppdb-data-label">Nama Lengkap:</div>
          <div class="ppdb-data-value"><?php echo esc_html($data['nama_lengkap']); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($data['email'])): ?>
        <div class="ppdb-data-row">
          <div class="ppdb-data-label">Email:</div>
          <div class="ppdb-data-value"><?php echo esc_html($data['email']); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($data['nomor_telepon'])): ?>
        <div class="ppdb-data-row">
          <div class="ppdb-data-label">Nomor Telepon:</div>
          <div class="ppdb-data-value"><?php echo esc_html($data['nomor_telepon']); ?></div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($data['jurusan'])): ?>
        <div class="ppdb-data-row">
          <div class="ppdb-data-label">Jurusan:</div>
          <div class="ppdb-data-value"><?php echo esc_html($data['jurusan']); ?></div>
        </div>
        <?php endif; ?>
        
        <div class="ppdb-data-row">
          <div class="ppdb-data-label">Tanggal Daftar:</div>
          <div class="ppdb-data-value"><?php echo esc_html(mysql2date('d F Y H:i', $submission->submitted_at)); ?></div>
        </div>
        
        <div class="ppdb-data-row">
          <div class="ppdb-data-label">Status:</div>
          <div class="ppdb-data-value"><strong style="color: #059669;">TERDAFTAR</strong></div>
        </div>
      </div>
      
      <!-- Footer -->
      <div class="ppdb-footer">
                <div class="ppdb-qr">
          <?php
          // Generate QR Code for verification
          $qr_code_url = '';
          if (class_exists('PPDB_Form_QR_Generator')) {
            $qr_code_url = PPDB_Form_QR_Generator::generate_certificate_qr($reg_number, $verification_hash, 100);
          }
          ?>
          <?php if ($qr_code_url): ?>
            <img src="<?php echo esc_attr($qr_code_url); ?>" alt="QR Code Verifikasi" style="width: 100px; height: 100px; border: 1px solid #ccc;" />
          <?php else: ?>
            <div style="width: 100px; height: 100px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 1px solid #ccc;">
              QR Code<br>Verifikasi
            </div>
          <?php endif; ?>
          <p style="font-size: 10px; margin: 5px 0;">Scan untuk verifikasi</p>
        </div>
        
        <div class="ppdb-signature">
          <div style="width: 200px; height: 80px; border-bottom: 1px solid #333; margin-bottom: 10px;"></div>
          <p><strong>Petugas Pendaftaran</strong></p>
          <p style="font-size: 12px;"><?php echo esc_html(date('d F Y')); ?></p>
        </div>
      </div>
      
      <!-- Verification Info -->
      <div class="ppdb-verification">
        <p><strong>Verifikasi:</strong> Kunjungi <?php echo esc_url(home_url()); ?> dan masukkan nomor registrasi untuk memverifikasi keaslian bukti ini.</p>
        <p><strong>Hash Verifikasi:</strong> <?php echo esc_html(substr($verification_hash, 0, 16)); ?>...</p>
      </div>
      
      <!-- Action Buttons -->
      <div class="ppdb-actions no-print">
        <button onclick="window.print()" class="ppdb-btn ppdb-btn-print">🖨️ Cetak</button>
        <button onclick="downloadCertificate()" class="ppdb-btn ppdb-btn-download">💾 Download</button>
        <button onclick="window.close()" class="ppdb-btn" style="background: #6b7280; color: white;">❌ Tutup</button>
      </div>
    </div>
    
    <script>
    function downloadCertificate() {
      // Create a blob with the certificate HTML
      const element = document.getElementById('ppdb-certificate');
      const clone = element.cloneNode(true);
      
      // Remove no-print elements
      const noPrintElements = clone.querySelectorAll('.no-print');
      noPrintElements.forEach(el => el.remove());
      
      const htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Bukti Pendaftaran - <?php echo esc_js($reg_number); ?></title>
          <style>
            body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
            ${document.querySelector('#ppdb-certificate style').innerHTML}
          </style>
        </head>
        <body>
          ${clone.outerHTML}
        </body>
        </html>
      `;
      
      const blob = new Blob([htmlContent], { type: 'text/html' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'bukti-pendaftaran-<?php echo esc_js($reg_number); ?>.html';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }
    </script>
    <?php
    return ob_get_clean();
  }

  /**
   * Generate PDF certificate (requires PDF library)
   */
  private static function generate_pdf_certificate($submission, array $data, string $reg_number, string $verification_hash): string
  {
    // TODO: Implement PDF generation using library like TCPDF or DOMPDF
    // For now, return HTML version
    return self::generate_html_certificate($submission, $data, $reg_number, $verification_hash);
  }

  /**
   * Verify certificate authenticity
   */
  public static function verify_certificate(string $reg_number, string $provided_hash): array
  {
    global $wpdb;

    // Extract submission ID from registration number
    $submission_id = self::extract_submission_id_from_reg_number($reg_number);

    if (!$submission_id) {
      return ['valid' => false, 'message' => 'Nomor registrasi tidak valid'];
    }

    // Generate expected hash
    $expected_hash = self::generate_verification_hash($submission_id, $reg_number);

    if (!hash_equals($expected_hash, $provided_hash)) {
      return ['valid' => false, 'message' => 'Hash verifikasi tidak cocok'];
    }

    // Get submission data
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';
    $submission = $wpdb->get_row($wpdb->prepare(
      "SELECT * FROM {$submissions_table} WHERE id = %d",
      $submission_id
    ));

    if (!$submission) {
      return ['valid' => false, 'message' => 'Data pendaftaran tidak ditemukan'];
    }

    $data = json_decode($submission->submission_data, true) ?: [];

    return [
      'valid' => true,
      'message' => 'Bukti pendaftaran valid',
      'data' => [
        'reg_number' => $reg_number,
        'nama_lengkap' => $data['nama_lengkap'] ?? '',
        'submitted_at' => $submission->submitted_at,
        'status' => 'TERDAFTAR'
      ]
    ];
  }

  /**
   * Extract submission ID from registration number
   */
  private static function extract_submission_id_from_reg_number(string $reg_number): ?int
  {
    $prefix = get_option('ppdb_reg_number_prefix', 'REG');

    // Remove prefix and year (REG2024123456 -> 123456)
    $pattern = '/^' . preg_quote($prefix, '/') . '\d{4}(\d{6})$/';

    if (preg_match($pattern, $reg_number, $matches)) {
      return (int) ltrim($matches[1], '0');
    }

    return null;
  }

  /**
   * Get certificate URL for submission
   */
  public static function get_certificate_url(int $submission_id): string
  {
    return add_query_arg([
      'ppdb_certificate' => 1,
      'sid' => $submission_id,
      'hash' => wp_hash($submission_id . '|' . wp_salt('auth'))
    ], home_url());
  }

  /**
   * Handle certificate display request
   */
  public static function handle_certificate_request(): void
  {
    if (!isset($_GET['ppdb_certificate'], $_GET['sid'], $_GET['hash'])) {
      return;
    }

    $submission_id = (int) $_GET['sid'];
    $provided_hash = (string) $_GET['hash'];
    $expected_hash = wp_hash($submission_id . '|' . wp_salt('auth'));

    if (!hash_equals($expected_hash, $provided_hash)) {
      wp_die('Hash tidak valid');
    }

    $certificate_html = self::generate_certificate($submission_id);

    if (empty($certificate_html)) {
      wp_die('Bukti pendaftaran tidak ditemukan');
    }

    // Output certificate
    echo $certificate_html;
    exit;
  }
}

// Hook certificate request handler
add_action('init', ['PPDB_Form_Certificate', 'handle_certificate_request']);
