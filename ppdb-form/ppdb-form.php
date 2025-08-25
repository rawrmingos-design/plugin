<?php

/**
 * Plugin Name: PPDB Form
 * Description: Plugin untuk membuat formulir pendaftaran online yang dapat dikustomisasi dan mengelola data pendaftar.
 * Version: 1.0
 * Author:
 * Plugin URI:
 * Author URI:
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

final class PPDB_Form_Plugin
{
  const VERSION = '1.1';
  const DB_VERSION = '1.2.0';

  /** @var PPDB_Form_Plugin|null */
  private static $instance = null;

  /**
   * @return PPDB_Form_Plugin
   */
  public static function instance(): PPDB_Form_Plugin
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    $this->define_constants();
    $this->includes();

    register_activation_hook(__FILE__, ['PPDB_Form_Installer', 'activate']);
    add_action('plugins_loaded', ['PPDB_Form_Installer', 'maybe_upgrade']);

    // Initialize admin with early action handling
    PPDB_Form_Admin::init();
    add_action('admin_menu', ['PPDB_Form_Debug', 'register_menu']);
    add_action('admin_init', ['PPDB_Form_Settings', 'register_settings']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);

    // Initialize email provider system
    PPDB_Form_Email_Provider::init();
    PPDB_Form_Email_Settings::init();

    add_shortcode('simpel_pendaftaran', ['PPDB_Form_Frontend', 'render_shortcode']);
    add_shortcode('ppdb_form', ['PPDB_Form_Frontend', 'render_shortcode']);
    add_action('init', ['PPDB_Form_Frontend', 'handle_submission']);
    add_action('ppdb_send_notifications', ['PPDB_Form_Notifications', 'process_queued_notifications'], 10, 2);

    // Initialize PDF template system
    PPDB_Form_PDF_Customizer::init();

    // Optional: reCAPTCHA site key configured via option, enqueue only on form pages if present
    add_action('wp_enqueue_scripts', static function () {
      $site_key = (string) get_option('ppdb_recaptcha_site_key', '');
      $page_content = get_post_field('post_content', get_the_ID() ?: 0);
      if ($site_key !== '' && (has_shortcode($page_content, 'simpel_pendaftaran') || has_shortcode($page_content, 'ppdb_form'))) {
        wp_enqueue_script('recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
      }
    });
  }

  private function define_constants(): void
  {
    define('PPDB_FORM_VERSION', self::VERSION);
    define('PPDB_FORM_DB_VERSION', self::DB_VERSION);
    define('PPDB_FORM_FILE', __FILE__);
    define('PPDB_FORM_DIR', plugin_dir_path(__FILE__));
    define('PPDB_FORM_URL', plugin_dir_url(__FILE__));
    define('PPDB_FORM_ASSETS', PPDB_FORM_URL . 'assets/');
  }

  private function includes(): void
  {
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-installer.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-admin.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-frontend.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-settings.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-notifications.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-submissions-list-table.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-debug.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-certificate.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-qr-generator.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-verification.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-pdf-generator.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-email-certificate.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-email-provider.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-email-settings.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-pdf-template.php';
    require_once PPDB_FORM_DIR . 'includes/class-ppdb-form-pdf-customizer.php';
  }

  public function enqueue_admin_assets(string $hook_suffix): void
  {
    if (strpos($hook_suffix, 'ppdb-form') === false) {
      return;
    }
    wp_enqueue_style('ppdb-form-admin', PPDB_FORM_ASSETS . 'css/admin.css', [], self::VERSION);
    // Ensure jQuery UI Sortable is available for steps builder
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('ppdb-form-admin', PPDB_FORM_ASSETS . 'js/admin.js', ['jquery', 'jquery-ui-sortable'], self::VERSION, true);
  }

  public function enqueue_front_assets(): void
  {
    // Enqueue on pages with shortcode or success page
    global $post;
    $should_enqueue = false;

    // Check if page has shortcode
    if (is_a($post, 'WP_Post') && (has_shortcode($post->post_content, 'simpel_pendaftaran') || has_shortcode($post->post_content, 'ppdb_form'))) {
      $should_enqueue = true;
    }

    // Also enqueue when success page parameters are present (for redirected success)
    if (isset($_GET['ppdb_thanks']) || isset($_GET['sid'])) {
      $should_enqueue = true;
    }

    if (!$should_enqueue) {
      return;
    }

    wp_enqueue_style('ppdb-form-frontend', PPDB_FORM_ASSETS . 'css/frontend.css', [], self::VERSION);
    wp_enqueue_style('ppdb-form-success', PPDB_FORM_ASSETS . 'css/success-page.css', [], self::VERSION);
    wp_enqueue_script('ppdb-form-frontend', PPDB_FORM_ASSETS . 'js/frontend.js', ['jquery'], self::VERSION, true);
    wp_enqueue_script('ppdb-form-success-animations', PPDB_FORM_ASSETS . 'js/success-animations.js', [], self::VERSION, true);

    // Localize script for translations
    wp_localize_script('ppdb-form-frontend', 'ppdbForm', [
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('ppdb_form_ajax'),
      'btnColors' => [
        'start' => get_option('ppdb_btn_color_start', '#3b82f6'),
        'end' => get_option('ppdb_btn_color_end', '#2563eb'),
      ],
      'messages' => [
        'saving' => __('Menyimpan...', 'ppdb-form'),
        'saved' => __('Data tersimpan', 'ppdb-form'),
        'error' => __('Terjadi kesalahan', 'ppdb-form'),
        'required' => __('Field ini wajib diisi', 'ppdb-form'),
        'invalid' => __('Format tidak valid', 'ppdb-form'),
      ]
    ]);
  }

  /**
   * Registry field metadata used by admin settings and frontend renderer
   * @return array<string, array{label:string, type:string, category:string}>
   */
  public static function get_field_registry(): array
  {
    return [
      // Informasi Pribadi
      'nisn' => ['label' => __('NISN', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'nik' => ['label' => __('NIK', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'no_kk' => ['label' => __('No. KK', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'nama_lengkap' => ['label' => __('Nama lengkap', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'tempat' => ['label' => __('Tempat Lahir', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'tanggal_lahir' => ['label' => __('Tanggal Lahir', 'ppdb-form'), 'type' => 'date', 'category' => 'Informasi Pribadi'],
      'jenis_kelamin' => ['label' => __('Jenis Kelamin', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Pribadi'],
      'agama' => ['label' => __('Agama', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Pribadi'],
      'kewarganegaraan' => ['label' => __('Kewarganegaraan', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Pribadi'],
      'anak_ke' => ['label' => __('Anak ke-', 'ppdb-form'), 'type' => 'number', 'category' => 'Informasi Pribadi'],
      'jumlah_saudara' => ['label' => __('Jumlah Saudara', 'ppdb-form'), 'type' => 'number', 'category' => 'Informasi Pribadi'],
      'golongan_darah' => ['label' => __('Golongan Darah', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Pribadi'],
      'tinggi_badan' => ['label' => __('Tinggi Badan (cm)', 'ppdb-form'), 'type' => 'number', 'category' => 'Informasi Pribadi'],
      'berat_badan' => ['label' => __('Berat Badan (kg)', 'ppdb-form'), 'type' => 'number', 'category' => 'Informasi Pribadi'],
      'alamat' => ['label' => __('Alamat Lengkap', 'ppdb-form'), 'type' => 'textarea', 'category' => 'Informasi Pribadi'],
      'rt' => ['label' => __('RT', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'rw' => ['label' => __('RW', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'kelurahan' => ['label' => __('Kelurahan/Desa', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'kecamatan' => ['label' => __('Kecamatan', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'kota_kabupaten' => ['label' => __('Kota/Kabupaten', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'provinsi' => ['label' => __('Provinsi', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'kode_pos' => ['label' => __('Kode Pos', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'nomor_telepon' => ['label' => __('Nomor Telepon', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Pribadi'],
      'email' => ['label' => __('Email', 'ppdb-form'), 'type' => 'email', 'category' => 'Informasi Pribadi'],
      // Orang Tua / Wali
      'nama_ayah' => ['label' => __('Nama Ayah', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'pendidikan_ayah' => ['label' => __('Pendidikan Ayah', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Orang Tua/Wali'],
      'pekerjaan_ayah' => ['label' => __('Pekerjaan Ayah', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'penghasilan_ayah' => ['label' => __('Penghasilan Ayah', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Orang Tua/Wali'],
      'telepon_ayah' => ['label' => __('Telepon Ayah', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'nama_ibu' => ['label' => __('Nama Ibu', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'pendidikan_ibu' => ['label' => __('Pendidikan Ibu', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Orang Tua/Wali'],
      'pekerjaan_ibu' => ['label' => __('Pekerjaan Ibu', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'penghasilan_ibu' => ['label' => __('Penghasilan Ibu', 'ppdb-form'), 'type' => 'select', 'category' => 'Informasi Orang Tua/Wali'],
      'telepon_ibu' => ['label' => __('Telepon Ibu', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'nama_wali' => ['label' => __('Nama Wali', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'pekerjaan_wali' => ['label' => __('Pekerjaan Wali', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'telepon_wali' => ['label' => __('Telepon Wali', 'ppdb-form'), 'type' => 'text', 'category' => 'Informasi Orang Tua/Wali'],
      'alamat_ortu' => ['label' => __('Alamat Orang Tua/Wali', 'ppdb-form'), 'type' => 'textarea', 'category' => 'Informasi Orang Tua/Wali'],
      // Riwayat Pendidikan
      'npsn_sekolah_asal' => ['label' => __('NPSN Sekolah Asal', 'ppdb-form'), 'type' => 'text', 'category' => 'Riwayat Pendidikan'],
      'sekolah_asal' => ['label' => __('Sekolah Asal', 'ppdb-form'), 'type' => 'text', 'category' => 'Riwayat Pendidikan'],
      'alamat_sekolah_asal' => ['label' => __('Alamat Sekolah Asal', 'ppdb-form'), 'type' => 'text', 'category' => 'Riwayat Pendidikan'],
      'tahun_lulus' => ['label' => __('Tahun Lulus', 'ppdb-form'), 'type' => 'number', 'category' => 'Riwayat Pendidikan'],
      'nilai_rata_rata' => ['label' => __('Nilai Rata-rata', 'ppdb-form'), 'type' => 'number', 'category' => 'Riwayat Pendidikan'],
      'prestasi_akademik' => ['label' => __('Prestasi Akademik', 'ppdb-form'), 'type' => 'textarea', 'category' => 'Riwayat Pendidikan'],
      // Pilihan
      'jurusan' => ['label' => __('Jurusan Pilihan 1', 'ppdb-form'), 'type' => 'select', 'category' => 'Pilihan'],
      'jurusan_pilihan_2' => ['label' => __('Jurusan Pilihan 2', 'ppdb-form'), 'type' => 'select', 'category' => 'Pilihan'],
      // Dokumen (Upload)
      'dok_kk' => ['label' => __('Kartu Keluarga (KK)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_akta' => ['label' => __('Akta Kelahiran', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_skl_ijazah' => ['label' => __('Surat Keterangan Lulus (SKL) / Ijazah', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_pas_foto' => ['label' => __('Pas Foto', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_ktp_wali' => ['label' => __('KTP Orang Tua/Wali', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_kip_kks_pkh' => ['label' => __('KIP/KKS/PKH (jika ada)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_piagam' => ['label' => __('Piagam/Sertifikat/Prestasi (opsional)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor' => ['label' => __('Rapor Semester Sebelumnya', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_1' => ['label' => __('Rapor Semester 1', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_2' => ['label' => __('Rapor Semester 2', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_3' => ['label' => __('Rapor Semester 3', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_4' => ['label' => __('Rapor Semester 4', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_5' => ['label' => __('Rapor Semester 5', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_6' => ['label' => __('Rapor Semester 6', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      // Opsi gabungan untuk memudahkan verifikasi
      'dok_rapor_1_3' => ['label' => __('Rapor Semester 1–3 (gabung PDF/ZIP)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_4_6' => ['label' => __('Rapor Semester 4–6 (gabung PDF/ZIP)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_rapor_1_6' => ['label' => __('Rapor Semester 1–6 (gabung PDF/ZIP)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_penugasan' => ['label' => __('Surat Penugasan Resmi (jika pindahan)', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
      'dok_pindah_domisili' => ['label' => __('Surat Keterangan Pindah Domisili', 'ppdb-form'), 'type' => 'file', 'category' => 'Dokumen'],
    ];
  }
}

PPDB_Form_Plugin::instance();
