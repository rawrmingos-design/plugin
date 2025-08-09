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
  const VERSION = '1.0';

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

    add_action('admin_menu', ['PPDB_Form_Admin', 'register_menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('wp_enqueue_scripts', [$this, 'enqueue_front_assets']);

    add_shortcode('simpel_pendaftaran', ['PPDB_Form_Frontend', 'render_shortcode']);
    add_action('init', ['PPDB_Form_Frontend', 'handle_submission']);
  }

  private function define_constants(): void
  {
    define('PPDB_FORM_VERSION', self::VERSION);
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
  }

  public function enqueue_admin_assets(string $hook_suffix): void
  {
    if (strpos($hook_suffix, 'ppdb-form') === false) {
      return;
    }
    wp_enqueue_style('ppdb-form-admin', PPDB_FORM_ASSETS . 'css/admin.css', [], self::VERSION);
    wp_enqueue_script('ppdb-form-admin', PPDB_FORM_ASSETS . 'js/admin.js', ['jquery'], self::VERSION, true);
  }

  public function enqueue_front_assets(): void
  {
    wp_enqueue_style('ppdb-form-frontend', PPDB_FORM_ASSETS . 'css/frontend.css', [], self::VERSION);
    wp_enqueue_script('ppdb-form-frontend', PPDB_FORM_ASSETS . 'js/frontend.js', ['jquery'], self::VERSION, true);
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
    ];
  }
}

PPDB_Form_Plugin::instance();
