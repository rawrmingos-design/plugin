<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Email Certificate Sender for PPDB Form
 * Handles automatic sending of registration certificates via email
 */
class PPDB_Form_Email_Certificate
{
  /**
   * Initialize email certificate hooks
   */
  public static function init(): void
  {
    // Hook into form submission success
    add_action('ppdb_form_submission_success', [self::class, 'send_certificate_email'], 10, 2);

    // Hook into manual certificate sending from admin
    add_action('wp_ajax_ppdb_send_certificate', [self::class, 'ajax_send_certificate']);
    add_action('wp_ajax_ppdb_bulk_send_certificates', [self::class, 'ajax_bulk_send_certificates']);
  }

  /**
   * Send certificate email after successful submission
   */
  public static function send_certificate_email(int $submission_id, array $data): void
  {
    // Check if auto-send is enabled
    $auto_send = get_option('ppdb_certificate_auto_send', false);
    if (!$auto_send) {
      return;
    }

    // Check if email is provided
    $email = $data['email'] ?? '';
    if (empty($email) || !is_email($email)) {
      return;
    }

    self::send_certificate_to_email($submission_id, $email, $data);
  }

  /**
   * Send certificate to specific email
   */
  public static function send_certificate_to_email(int $submission_id, string $email, array $data = []): bool
  {
    if (empty($data)) {
      // Get submission data
      global $wpdb;
      $submissions_table = $wpdb->prefix . 'ppdb_submissions';
      $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$submissions_table} WHERE id = %d",
        $submission_id
      ));

      if (!$submission) {
        return false;
      }

      $data = json_decode($submission->submission_data, true) ?: [];
    }

    $nama = $data['nama_lengkap'] ?? 'Pendaftar';
    $reg_number = self::get_registration_number($submission_id);

    // Email subject
    $subject_template = get_option('ppdb_certificate_email_subject', 'Bukti Pendaftaran - {nama_lengkap}');
    $subject = str_replace(['{nama_lengkap}', '{reg_number}'], [$nama, $reg_number], $subject_template);

    // Email body
    $body_template = get_option('ppdb_certificate_email_template', self::get_default_email_template());
    $body = self::replace_email_placeholders($body_template, $data, $submission_id);

    // Certificate URL
    $certificate_url = '';
    if (class_exists('PPDB_Form_Certificate')) {
      $certificate_url = PPDB_Form_Certificate::get_certificate_url($submission_id);
    }

    // PDF URL
    $pdf_url = '';
    if (class_exists('PPDB_Form_PDF_Generator')) {
      $pdf_url = PPDB_Form_PDF_Generator::get_pdf_download_url($submission_id);
    }

    // Add certificate links to email body
    $certificate_links = self::get_certificate_links_html($certificate_url, $pdf_url);
    $body .= "\n\n" . $certificate_links;

    // Email headers
    $headers = [
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . get_option('ppdb_institution_name', get_bloginfo('name')) . ' <' . get_option('ppdb_form_email_admin', get_option('admin_email')) . '>'
    ];

    // Send email using configured provider
    $sent = self::send_email_with_provider($email, $subject, $body, $headers);

    if ($sent) {
      // Log successful send
      self::log_certificate_email($submission_id, $email, 'sent');
    } else {
      // Log failed send
      self::log_certificate_email($submission_id, $email, 'failed');
    }

    return $sent;
  }

  /**
   * Replace email template placeholders
   */
  private static function replace_email_placeholders(string $template, array $data, int $submission_id): string
  {
    $reg_number = self::get_registration_number($submission_id);

    $replacements = [
      '{submission_id}' => (string) $submission_id,
      '{reg_number}' => $reg_number,
      '{nama_lengkap}' => $data['nama_lengkap'] ?? '',
      '{email}' => $data['email'] ?? '',
      '{nomor_telepon}' => $data['nomor_telepon'] ?? '',
      '{jurusan}' => $data['jurusan'] ?? '',
      '{alamat}' => $data['alamat'] ?? '',
      '{tanggal_lahir}' => $data['tanggal_lahir'] ?? '',
      '{jenis_kelamin}' => $data['jenis_kelamin'] ?? '',
      '{institution_name}' => get_option('ppdb_institution_name', get_bloginfo('name')),
      '{admin_email}' => get_option('ppdb_form_email_admin', get_option('admin_email')),
      '{site_url}' => home_url(),
      '{current_date}' => date('d F Y'),
    ];

    return strtr($template, $replacements);
  }

  /**
   * Get certificate links HTML for email
   */
  private static function get_certificate_links_html(string $certificate_url, string $pdf_url): string
  {
    $html = '<div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #28a745;">';
    $html .= '<h3 style="margin-top: 0; color: #28a745;">📄 Bukti Pendaftaran Anda</h3>';
    $html .= '<p>Silakan klik link di bawah untuk mengakses bukti pendaftaran Anda:</p>';
    $html .= '<div style="margin: 15px 0;">';

    if (!empty($certificate_url)) {
      $html .= '<a href="' . esc_url($certificate_url) . '" style="display: inline-block; padding: 12px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 5px 10px 5px 0; font-weight: bold;">🔗 Lihat Bukti Online</a>';
    }

    if (!empty($pdf_url)) {
      $html .= '<a href="' . esc_url($pdf_url) . '" style="display: inline-block; padding: 12px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 5px; margin: 5px 10px 5px 0; font-weight: bold;">📄 Download PDF</a>';
    }

    $html .= '</div>';
    $html .= '<p style="font-size: 14px; color: #6c757d; margin-bottom: 0;"><strong>Catatan:</strong> Simpan bukti pendaftaran ini dengan baik. Link ini berlaku permanen untuk verifikasi.</p>';
    $html .= '</div>';

    return $html;
  }

  /**
   * Get registration number
   */
  private static function get_registration_number(int $submission_id): string
  {
    $prefix = get_option('ppdb_reg_number_prefix', 'REG');
    $year = date('Y');
    $padded_id = str_pad((string) $submission_id, 6, '0', STR_PAD_LEFT);

    return $prefix . $year . $padded_id;
  }

  /**
   * Log certificate email sending
   */
  private static function log_certificate_email(int $submission_id, string $email, string $status): void
  {
    $log_entry = [
      'submission_id' => $submission_id,
      'email' => $email,
      'status' => $status,
      'timestamp' => current_time('mysql'),
      'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    // Store in transient for admin notifications
    $logs = get_transient('ppdb_certificate_email_logs') ?: [];
    $logs[] = $log_entry;

    // Keep only last 100 logs
    if (count($logs) > 100) {
      $logs = array_slice($logs, -100);
    }

    set_transient('ppdb_certificate_email_logs', $logs, DAY_IN_SECONDS);

    // Log to WordPress debug log if enabled
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
      error_log("PPDB Certificate Email: {$status} - Submission #{$submission_id} to {$email}");
    }
  }

  /**
   * AJAX handler for manual certificate sending
   */
  public static function ajax_send_certificate(): void
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $submission_id = isset($_POST['submission_id']) ? (int) $_POST['submission_id'] : 0;
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

    if ($submission_id <= 0 || empty($email)) {
      wp_send_json_error(['message' => 'Invalid parameters']);
    }

    check_ajax_referer('ppdb_send_certificate_' . $submission_id, 'nonce');

    $sent = self::send_certificate_to_email($submission_id, $email);

    if ($sent) {
      wp_send_json_success(['message' => 'Certificate email sent successfully']);
    } else {
      wp_send_json_error(['message' => 'Failed to send certificate email']);
    }
  }

  /**
   * AJAX handler for bulk certificate sending
   */
  public static function ajax_bulk_send_certificates(): void
  {
    if (!current_user_can('manage_options')) {
      wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    check_ajax_referer('ppdb_bulk_send_certificates', 'nonce');

    $submission_ids = isset($_POST['submission_ids']) ? array_map('intval', $_POST['submission_ids']) : [];

    if (empty($submission_ids)) {
      wp_send_json_error(['message' => 'No submissions selected']);
    }

    $sent_count = 0;
    $failed_count = 0;

    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';

    foreach ($submission_ids as $submission_id) {
      // Get submission data
      $submission = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$submissions_table} WHERE id = %d",
        $submission_id
      ));

      if (!$submission) {
        $failed_count++;
        continue;
      }

      $data = json_decode($submission->submission_data, true) ?: [];
      $email = $data['email'] ?? '';

      if (empty($email) || !is_email($email)) {
        $failed_count++;
        continue;
      }

      $sent = self::send_certificate_to_email($submission_id, $email, $data);

      if ($sent) {
        $sent_count++;
      } else {
        $failed_count++;
      }

      // Small delay to prevent overwhelming mail server
      usleep(100000);  // 0.1 second
    }

    wp_send_json_success([
      'message' => "Bulk send completed: {$sent_count} sent, {$failed_count} failed",
      'sent_count' => $sent_count,
      'failed_count' => $failed_count
    ]);
  }

  /**
   * Get default email template
   */
  private static function get_default_email_template(): string
  {
    return 'Halo {nama_lengkap},

Terima kasih telah mendaftar di {institution_name}.

Pendaftaran Anda telah berhasil diterima dengan detail sebagai berikut:
- Nomor Registrasi: {reg_number}
- Nama Lengkap: {nama_lengkap}
- Email: {email}
- Jurusan: {jurusan}

Bukti pendaftaran resmi Anda dapat diakses melalui link yang disediakan di bawah. Silakan simpan bukti ini dengan baik sebagai tanda bukti pendaftaran yang sah.

Untuk pertanyaan lebih lanjut, silakan hubungi kami di: {admin_email}

Terima kasih,
Tim Penerimaan
{institution_name}

---
Email ini dikirim secara otomatis pada {current_date}';
  }

  /**
   * Send email with configured provider
   */
  private static function send_email_with_provider(string $to, string $subject, string $message, array $headers = []): bool
  {
    // Use the email provider system
    return wp_mail($to, $subject, $message, $headers);
  }

  /**
   * Get email logs for admin
   */
  public static function get_email_logs(int $limit = 50): array
  {
    $logs = get_transient('ppdb_certificate_email_logs') ?: [];

    // Sort by timestamp descending
    usort($logs, function ($a, $b) {
      return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    return array_slice($logs, 0, $limit);
  }
}

// Initialize email certificate system
add_action('init', ['PPDB_Form_Email_Certificate', 'init']);
