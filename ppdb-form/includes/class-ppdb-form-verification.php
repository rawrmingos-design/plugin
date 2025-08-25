<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Certificate Verification Handler
 * Handles QR code verification and public certificate validation
 */
class PPDB_Form_Verification
{
  /**
   * Initialize verification hooks
   */
  public static function init(): void
  {
    add_action('init', [self::class, 'handle_verification_request']);
    add_action('wp_enqueue_scripts', [self::class, 'enqueue_verification_assets']);
  }
  
  /**
   * Handle verification page request
   */
  public static function handle_verification_request(): void
  {
    if (!isset($_GET['ppdb_verify'])) {
      return;
    }
    
    // Handle verification display
    add_filter('template_include', [self::class, 'load_verification_template']);
  }
  
  /**
   * Load verification template
   */
  public static function load_verification_template($template): string
  {
    // Use theme's page template or create our own
    $verification_template = locate_template('page-ppdb-verification.php');
    
    if (!$verification_template) {
      // Create our own template
      self::render_verification_page();
      exit;
    }
    
    return $verification_template;
  }
  
  /**
   * Render verification page
   */
  public static function render_verification_page(): void
  {
    $reg_number = sanitize_text_field($_GET['reg'] ?? '');
    $provided_hash = sanitize_text_field($_GET['hash'] ?? '');
    
    $verification_result = null;
    $show_form = true;
    
    // If parameters provided via QR code
    if (!empty($reg_number) && !empty($provided_hash)) {
      $verification_result = self::verify_certificate($reg_number, $provided_hash);
      $show_form = false;
    }
    
    // If manual verification submitted
    if (isset($_POST['verify_submit'])) {
      $reg_number = sanitize_text_field($_POST['reg_number'] ?? '');
      $verification_result = self::verify_certificate_by_number($reg_number);
      $show_form = false;
    }
    
    get_header();
    
    ?>
    <div class="ppdb-verification-page" style="max-width: 800px; margin: 40px auto; padding: 20px; font-family: Arial, sans-serif;">
      <style>
        .ppdb-verification-page { line-height: 1.6; color: #333; }
        .ppdb-verify-header { text-align: center; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #e5e7eb; }
        .ppdb-verify-title { font-size: 28px; font-weight: bold; color: #1f2937; margin-bottom: 10px; }
        .ppdb-verify-subtitle { font-size: 16px; color: #6b7280; }
        .ppdb-verify-form { background: #f9fafb; padding: 30px; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 30px; }
        .ppdb-verify-result { padding: 30px; border-radius: 12px; margin-bottom: 30px; }
        .ppdb-verify-result.valid { background: #ecfdf5; border: 2px solid #10b981; }
        .ppdb-verify-result.invalid { background: #fef2f2; border: 2px solid #ef4444; }
        .ppdb-verify-input { width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 16px; margin-bottom: 15px; }
        .ppdb-verify-btn { background: #3b82f6; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; font-size: 16px; font-weight: 600; }
        .ppdb-verify-btn:hover { background: #2563eb; }
        .ppdb-data-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .ppdb-data-table th, .ppdb-data-table td { padding: 12px; border: 1px solid #e5e7eb; text-align: left; }
        .ppdb-data-table th { background: #f3f4f6; font-weight: 600; }
        .ppdb-status-valid { color: #10b981; font-weight: bold; }
        .ppdb-status-invalid { color: #ef4444; font-weight: bold; }
        .ppdb-instructions { background: #eff6ff; padding: 20px; border-radius: 8px; border-left: 4px solid #3b82f6; margin-bottom: 30px; }
      </style>
      
      <!-- Header -->
      <div class="ppdb-verify-header">
        <h1 class="ppdb-verify-title">🔍 Verifikasi Bukti Pendaftaran</h1>
        <p class="ppdb-verify-subtitle"><?php echo esc_html(get_option('ppdb_institution_name', get_bloginfo('name'))); ?></p>
      </div>
      
      <!-- Instructions -->
      <div class="ppdb-instructions">
        <h3 style="margin-top: 0;">📋 Cara Verifikasi:</h3>
        <ul style="margin-bottom: 0;">
          <li><strong>QR Code:</strong> Scan QR code pada bukti pendaftaran dengan kamera ponsel</li>
          <li><strong>Manual:</strong> Masukkan nomor registrasi pada form di bawah</li>
          <li><strong>Hash:</strong> Jika ada hash verifikasi, masukkan juga untuk validasi tambahan</li>
        </ul>
      </div>
      
      <?php if ($verification_result): ?>
        <!-- Verification Result -->
        <div class="ppdb-verify-result <?php echo $verification_result['valid'] ? 'valid' : 'invalid'; ?>">
          <h2 style="margin-top: 0;">
            <?php if ($verification_result['valid']): ?>
              ✅ Bukti Pendaftaran Valid
            <?php else: ?>
              ❌ Bukti Pendaftaran Tidak Valid
            <?php endif; ?>
          </h2>
          
          <p style="font-size: 16px; margin-bottom: 20px;">
            <?php echo esc_html($verification_result['message']); ?>
          </p>
          
          <?php if ($verification_result['valid'] && !empty($verification_result['data'])): ?>
            <table class="ppdb-data-table">
              <tr>
                <th>Nomor Registrasi</th>
                <td><strong><?php echo esc_html($verification_result['data']['reg_number']); ?></strong></td>
              </tr>
              <tr>
                <th>Nama Lengkap</th>
                <td><?php echo esc_html($verification_result['data']['nama_lengkap']); ?></td>
              </tr>
              <tr>
                <th>Tanggal Daftar</th>
                <td><?php echo esc_html(mysql2date('d F Y H:i', $verification_result['data']['submitted_at'])); ?></td>
              </tr>
              <tr>
                <th>Status</th>
                <td><span class="ppdb-status-valid"><?php echo esc_html($verification_result['data']['status']); ?></span></td>
              </tr>
            </table>
          <?php endif; ?>
          
          <div style="margin-top: 20px; text-align: center;">
            <button onclick="location.reload()" class="ppdb-verify-btn">🔄 Verifikasi Lagi</button>
          </div>
        </div>
      <?php endif; ?>
      
      <?php if ($show_form): ?>
        <!-- Verification Form -->
        <div class="ppdb-verify-form">
          <h2 style="margin-top: 0;">📝 Verifikasi Manual</h2>
          <form method="post">
            <label for="reg_number" style="display: block; margin-bottom: 8px; font-weight: 600;">
              Nomor Registrasi:
            </label>
            <input 
              type="text" 
              id="reg_number" 
              name="reg_number" 
              class="ppdb-verify-input"
              placeholder="Contoh: REG2024000123"
              value="<?php echo esc_attr($reg_number); ?>"
              required
            />
            
            <label for="verify_hash" style="display: block; margin-bottom: 8px; font-weight: 600;">
              Hash Verifikasi (Opsional):
            </label>
            <input 
              type="text" 
              id="verify_hash" 
              name="verify_hash" 
              class="ppdb-verify-input"
              placeholder="Hash verifikasi dari bukti pendaftaran"
            />
            
            <button type="submit" name="verify_submit" class="ppdb-verify-btn">
              🔍 Verifikasi
            </button>
          </form>
        </div>
      <?php endif; ?>
      
      <!-- Footer Info -->
      <div style="text-align: center; padding: 20px; color: #6b7280; font-size: 14px; border-top: 1px solid #e5e7eb;">
        <p><strong>Sistem Verifikasi Bukti Pendaftaran</strong></p>
        <p>Untuk bantuan, hubungi: <?php echo esc_html(get_option('ppdb_form_email_admin', get_option('admin_email'))); ?></p>
      </div>
    </div>
    
    <script>
    // Auto-submit if QR parameters are present
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('reg') && urlParams.has('hash')) {
        // QR code scan detected
        console.log('QR Code verification initiated');
      }
    });
    </script>
    <?php
    
    get_footer();
  }
  
  /**
   * Verify certificate with registration number and hash
   */
  private static function verify_certificate(string $reg_number, string $provided_hash): array
  {
    if (empty($reg_number)) {
      return ['valid' => false, 'message' => 'Nomor registrasi tidak boleh kosong'];
    }
    
    // Use certificate verification from main class
    if (class_exists('PPDB_Form_Certificate')) {
      return PPDB_Form_Certificate::verify_certificate($reg_number, $provided_hash);
    }
    
    return ['valid' => false, 'message' => 'Sistem verifikasi tidak tersedia'];
  }
  
  /**
   * Verify certificate by registration number only
   */
  private static function verify_certificate_by_number(string $reg_number): array
  {
    if (empty($reg_number)) {
      return ['valid' => false, 'message' => 'Nomor registrasi tidak boleh kosong'];
    }
    
    global $wpdb;
    
    // Extract submission ID from registration number
    $submission_id = self::extract_submission_id_from_reg_number($reg_number);
    
    if (!$submission_id) {
      return ['valid' => false, 'message' => 'Format nomor registrasi tidak valid'];
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
      'message' => 'Bukti pendaftaran ditemukan dan valid',
      'data' => [
        'reg_number' => $reg_number,
        'nama_lengkap' => $data['nama_lengkap'] ?? 'Tidak tersedia',
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
   * Enqueue verification page assets
   */
  public static function enqueue_verification_assets(): void
  {
    if (isset($_GET['ppdb_verify'])) {
      // Add any additional CSS/JS for verification page
      wp_enqueue_style('ppdb-verification', PPDB_FORM_ASSETS . 'css/verification.css', [], PPDB_Form_Plugin::VERSION);
    }
  }
}

// Initialize verification system
add_action('init', ['PPDB_Form_Verification', 'init']);
