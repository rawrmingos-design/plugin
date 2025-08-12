<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

class PPDB_Form_Notifications
{
  public static function send_admin_notification(array $submission_data, object $form): void
  {
    if (!(bool) get_option('ppdb_form_enable_notifications', true)) {
      return;
    }

    $admin_email = get_option('ppdb_form_email_admin', get_option('admin_email'));
    if (empty($admin_email)) {
      return;
    }

    $subject = get_option('ppdb_form_email_subject', __('Pendaftaran Baru - {nama_lengkap}', 'ppdb-form'));
    $template = get_option('ppdb_form_email_template', self::get_default_admin_template());

    $subject = self::replace_placeholders($subject, $submission_data);
    $message = self::replace_placeholders($template, $submission_data);

    $headers = [
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    ];

    wp_mail($admin_email, $subject, nl2br(esc_html($message)), $headers);
  }

  public static function send_user_notification(array $submission_data, object $form): void
  {
    if (!(bool) get_option('ppdb_form_enable_notifications', true)) {
      return;
    }

    $user_email = $submission_data['email'] ?? '';
    if (empty($user_email) || !is_email($user_email)) {
      return;
    }

    $subject = get_option('ppdb_form_user_email_subject', __('Konfirmasi Pendaftaran - {nama_lengkap}', 'ppdb-form'));
    $template = get_option('ppdb_form_user_email_template', self::get_default_user_template());

    $subject = self::replace_placeholders($subject, $submission_data);
    $message = self::replace_placeholders($template, $submission_data);

    $headers = [
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
    ];

    wp_mail($user_email, $subject, nl2br(esc_html($message)), $headers);
  }

  private static function replace_placeholders(string $text, array $data): string
  {
    $registry = PPDB_Form_Plugin::get_field_registry();

    foreach ($data as $key => $value) {
      $placeholder = '{' . $key . '}';
      $text = str_replace($placeholder, (string) $value, $text);
    }

    // Additional placeholders
    $text = str_replace('{site_name}', get_bloginfo('name'), $text);
    $text = str_replace('{site_url}', home_url(), $text);
    $text = str_replace('{date}', wp_date('Y-m-d H:i'), $text);

    return $text;
  }

  private static function get_default_admin_template(): string
  {
    return __('Pendaftaran baru telah diterima:

Nama: {nama_lengkap}
Email: {email}
Telepon: {nomor_telepon}
Jurusan: {jurusan}

Data lengkap dapat dilihat di admin dashboard.', 'ppdb-form');
  }

  private static function get_default_user_template(): string
  {
    return __('Halo {nama_lengkap},

Terima kasih telah mendaftar. Pendaftaran Anda telah kami terima dan sedang diproses.

Data pendaftaran:
- Nama: {nama_lengkap}
- Email: {email}
- Jurusan: {jurusan}

Kami akan menghubungi Anda jika ada informasi lebih lanjut.

Salam,
Tim Penerimaan', 'ppdb-form');
  }

  /**
   * Queue email sending to avoid blocking form submission
   */
  public static function queue_notifications(array $submission_data, object $form): void
  {
    // Schedule immediate background task
    wp_schedule_single_event(time(), 'ppdb_send_notifications', [$submission_data, $form->id]);
  }

  public static function process_queued_notifications(array $submission_data, int $form_id): void
  {
    global $wpdb;
    $forms_table = $wpdb->prefix . 'ppdb_forms';
    $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $forms_table . ' WHERE id = %d', $form_id));

    if (!$form) {
      return;
    }

    self::send_admin_notification($submission_data, $form);
    self::send_user_notification($submission_data, $form);
  }
}
