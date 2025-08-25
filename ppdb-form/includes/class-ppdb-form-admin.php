<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PPDB_Form_Admin
{
    /**
     * Initialize admin hooks and handle early actions
     */
    public static function init(): void
    {
        // Handle download/export actions early before any output
        add_action('admin_init', [self::class, 'handle_early_actions'], 5);
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    /**
     * Handle actions that send headers before any output
     */
    public static function handle_early_actions(): void
    {
        // Only process on our admin pages with proper validation
        if (!isset($_REQUEST['page']) || strpos(sanitize_text_field($_REQUEST['page']), 'ppdb-form') === false) {
            return;
        }
        
        // Verify user capabilities early
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle CSV export action with proper validation
        if (isset($_GET['action']) && sanitize_text_field($_GET['action']) === 'export') {
            // Verify nonce for export action
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ppdb_export_action')) {
                wp_die(__('Token keamanan tidak valid.', 'ppdb-form'));
            }
            self::handle_csv_export();
        }

        // Handle export field configuration with validation
        if (isset($_POST['ppdb_export_action']) && sanitize_text_field($_POST['ppdb_export_action']) === 'configure_fields') {
            // Verify nonce is already handled in the function
            self::handle_export_field_configuration();
        }

        // Handle bulk actions that send files with proper validation
        if (isset($_POST['action']) && isset($_POST['submission_ids'])) {
            // Verify nonce for bulk actions
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'bulk-submissions')) {
                wp_die(__('Token keamanan tidak valid untuk bulk action.', 'ppdb-form'));
            }
            
            $action = sanitize_text_field($_POST['action']);
            $ids = array_map('intval', (array) $_POST['submission_ids']);
            
            // Validate IDs are not empty
            if (empty($ids)) {
                wp_die(__('Tidak ada item yang dipilih.', 'ppdb-form'));
            }

            switch ($action) {
                case 'download_certificates':
                    self::handle_bulk_download($ids);
                    break;
                case 'export_selected':
                    self::handle_export_selected($ids);
                    break;
            }
        }
    }

    public static function register_menu(): void
    {
        add_menu_page(
            __('ppdb-form', 'ppdb-form'),
            __('ppdb-form', 'ppdb-form'),
            'manage_options',
            'ppdb-form',
            [self::class, 'render_forms_page'],
            'dashicons-forms',
            56
        );

        add_submenu_page('ppdb-form', __('Manajemen Formulir', 'ppdb-form'), __('Manajemen Formulir', 'ppdb-form'), 'manage_options', 'ppdb-form', [self::class, 'render_forms_page']);
        add_submenu_page('ppdb-form', __('Data Pendaftar', 'ppdb-form'), __('Data Pendaftar', 'ppdb-form'), 'manage_options', 'ppdb-form-registrants', [self::class, 'render_registrants_page_new']);
        add_submenu_page('ppdb-form', __('Pengaturan Jurusan', 'ppdb-form'), __('Pengaturan Jurusan', 'ppdb-form'), 'manage_options', 'ppdb-form-departments', [self::class, 'render_departments_page']);
        add_submenu_page('ppdb-form', __('Pengaturan', 'ppdb-form'), __('Pengaturan', 'ppdb-form'), 'manage_options', 'ppdb-form-settings', ['PPDB_Form_Settings', 'render_settings_page']);
    }

    private static function get_forms_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ppdb_forms';
    }

    private static function get_submissions_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ppdb_submissions';
    }

    private static function get_departments_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ppdb_departments';
    }

    /* ---------- Forms Page ---------- */
    public static function render_forms_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }
        $action = isset($_GET['action']) ? sanitize_text_field((string) $_GET['action']) : '';
        
        // Validate action parameter
        $allowed_actions = ['delete', 'fields', 'steps', 'edit'];
        if (!empty($action) && !in_array($action, $allowed_actions, true)) {
            wp_die(__('Aksi tidak valid.', 'ppdb-form'));
        }
        if ($action === 'delete' && isset($_GET['id'])) {
            $form_id = (int) $_GET['id'];
            // Validate form ID and check if form exists
            if ($form_id <= 0) {
                wp_die(__('ID form tidak valid.', 'ppdb-form'));
            }
            
            // Verify form exists in database
            global $wpdb;
            $form_exists = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::get_forms_table() . ' WHERE id = %d', $form_id));
            if (!$form_exists) {
                wp_die(__('Form tidak ditemukan.', 'ppdb-form'));
            }
            self::handle_delete_form($form_id);
        }
        if ($action === 'fields' && isset($_GET['id'])) {
            $form_id = (int) $_GET['id'];
            // Validate form ID and check if form exists
            if ($form_id <= 0) {
                wp_die(__('ID form tidak valid.', 'ppdb-form'));
            }
            
            // Verify form exists in database
            global $wpdb;
            $form_exists = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::get_forms_table() . ' WHERE id = %d', $form_id));
            if (!$form_exists) {
                wp_die(__('Form tidak ditemukan.', 'ppdb-form'));
            }
            self::render_form_fields_page($form_id);
            return;
        }
        self::handle_save_form();
        if (isset($_GET['action']) && sanitize_text_field($_GET['action']) === 'steps' && isset($_GET['id'])) {
            $form_id = (int) $_GET['id'];
            // Validate form ID and check if form exists
            if ($form_id <= 0) {
                wp_die(__('ID form tidak valid.', 'ppdb-form'));
            }
            
            // Verify form exists in database
            global $wpdb;
            $form_exists = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::get_forms_table() . ' WHERE id = %d', $form_id));
            if (!$form_exists) {
                wp_die(__('Form tidak ditemukan.', 'ppdb-form'));
            }
            self::render_steps_builder($form_id);
            return;
        }
        self::render_forms_list_and_editor();
    }

    private static function handle_delete_form(int $id): void
    {
        check_admin_referer('ppdb_delete_form_' . $id);
        global $wpdb;
        
        // Use transaction for data integrity
        $wpdb->query('START TRANSACTION');
        
        try {
            $result1 = $wpdb->delete(self::get_forms_table(), ['id' => $id], ['%d']);
            $result2 = $wpdb->delete(self::get_submissions_table(), ['form_id' => $id], ['%d']);
            
            if ($result1 !== false && $result2 !== false) {
                $wpdb->query('COMMIT');
                echo '<div class="updated"><p>' . esc_html__('Formulir dan data terkait telah dihapus.', 'ppdb-form') . '</p></div>';
            } else {
                $wpdb->query('ROLLBACK');
                echo '<div class="error"><p>' . esc_html__('Gagal menghapus formulir.', 'ppdb-form') . '</p></div>';
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            echo '<div class="error"><p>' . esc_html__('Terjadi kesalahan saat menghapus formulir.', 'ppdb-form') . '</p></div>';
        }
    }

    private static function handle_save_form(): void
    {
        if (!isset($_POST['ppdb_form_nonce']) || !wp_verify_nonce((string) $_POST['ppdb_form_nonce'], 'ppdb_save_form')) {
            return;
        }
        global $wpdb;
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        // Enhanced input validation
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $description = sanitize_textarea_field((string) ($_POST['description'] ?? ''));
        $success_message = sanitize_textarea_field((string) ($_POST['success_message'] ?? ''));
        
        // Validate required fields
        if (empty(trim($name))) {
            echo '<div class="error"><p>' . esc_html__('Nama form wajib diisi.', 'ppdb-form') . '</p></div>';
            return;
        }
        
        // Validate name length
        if (strlen($name) > 255) {
            echo '<div class="error"><p>' . esc_html__('Nama form terlalu panjang (maksimal 255 karakter).', 'ppdb-form') . '</p></div>';
            return;
        }
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Handle multi-step configuration
        $enable_multistep = isset($_POST['enable_multistep']) ? 1 : 0;
        $steps_config = null;
        if ($enable_multistep) {
            $steps_config = wp_json_encode([
                'enabled' => true,
                'steps' => self::get_default_steps_config()
            ]);
        }

        $data = ['name' => $name, 'description' => $description, 'success_message' => $success_message, 'is_active' => $is_active, 'steps_config' => $steps_config, 'updated_at' => current_time('mysql')];
        
        // Use transaction for data integrity
        $wpdb->query('START TRANSACTION');
        
        try {
            if ($id > 0) {
                $result = $wpdb->update(self::get_forms_table(), $data, ['id' => $id], ['%s', '%s', '%s', '%d', '%s', '%s'], ['%d']);
                if ($result !== false) {
                    $wpdb->query('COMMIT');
                    echo '<div class="updated"><p>' . esc_html__('Formulir diperbarui.', 'ppdb-form') . '</p></div>';
                } else {
                    $wpdb->query('ROLLBACK');
                    echo '<div class="error"><p>' . esc_html__('Gagal memperbarui formulir.', 'ppdb-form') . '</p></div>';
                }
            } else {
                $data['created_at'] = current_time('mysql');
                $data['fields_json'] = wp_json_encode(self::generate_default_fields_config());
                $result = $wpdb->insert(self::get_forms_table(), $data, ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
                if ($result !== false) {
                    $wpdb->query('COMMIT');
                    echo '<div class="updated"><p>' . esc_html__('Formulir dibuat.', 'ppdb-form') . '</p></div>';
                } else {
                    $wpdb->query('ROLLBACK');
                    echo '<div class="error"><p>' . esc_html__('Gagal membuat formulir.', 'ppdb-form') . '</p></div>';
                }
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            echo '<div class="error"><p>' . esc_html__('Terjadi kesalahan saat menyimpan formulir.', 'ppdb-form') . '</p></div>';
        }
    }

    private static function generate_default_fields_config(): array
    {
        $registry = PPDB_Form_Plugin::get_field_registry();
        $enabled_keys = [
            'nisn', 'nama_lengkap', 'jenis_kelamin', 'agama', 'alamat', 'nomor_telepon', 'email', 'jurusan',
            // Dokumen rapor per semester default diaktifkan
            'dok_rapor_1', 'dok_rapor_2', 'dok_rapor_3', 'dok_rapor_4', 'dok_rapor_5', 'dok_rapor_6'
        ];
        $required_keys = [
            'nisn', 'nama_lengkap', 'jenis_kelamin', 'nomor_telepon', 'jurusan',
            // Wajib unggah rapor per semester
            'dok_rapor_1', 'dok_rapor_2', 'dok_rapor_3', 'dok_rapor_4', 'dok_rapor_5', 'dok_rapor_6'
        ];
        $config = [];
        foreach ($registry as $key => $_meta) {
            $config[$key] = [
                'enabled' => in_array($key, $enabled_keys, true) ? 1 : 0,
                'required' => in_array($key, $required_keys, true) ? 1 : 0,
            ];
        }
        return $config;
    }

    private static function get_default_steps_config(): array
    {
        return [
            [
                'title' => 'Data Pribadi',
                'description' => 'Informasi dasar tentang siswa',
                'fields' => ['nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kewarganegaraan', 'golongan_darah']
            ],
            [
                'title' => 'Kontak & Alamat',
                'description' => 'Informasi kontak dan alamat siswa',
                'fields' => ['alamat', 'nomor_telepon', 'email']
            ],
            [
                'title' => 'Data Orang Tua',
                'description' => 'Informasi tentang orang tua/wali',
                'fields' => ['nama_ayah', 'nama_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'pendidikan_ayah', 'pendidikan_ibu', 'penghasilan_ayah', 'penghasilan_ibu', 'telepon_ayah', 'telepon_ibu', 'alamat_ortu']
            ],
            [
                'title' => 'Pendidikan & Jurusan',
                'description' => 'Riwayat pendidikan dan pilihan jurusan',
                'fields' => ['asal_sekolah', 'alamat_sekolah_asal', 'tahun_lulus', 'jurusan', 'jurusan_pilihan_2', 'prestasi_akademik']
            ],
            [
                'title' => 'Unggah Dokumen',
                'description' => 'Unggah dokumen yang diminta (format PDF/JPG/PNG).',
                'fields' => ['dok_rapor_1_3', 'dok_rapor_4_6', 'dok_rapor_1_6']
            ]
        ];
    }

    private static function render_forms_list_and_editor(): void
    {
        global $wpdb;
        $editing_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $form = $editing_id > 0 ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d', $editing_id)) : null;
        echo '<div class="wrap ppdb-admin">';
        echo '<h2>' . esc_html__('Manajemen Data Form', 'ppdb-form') . '</h2>';
        echo '<div class="ppdb-card">';
        echo '<form method="post">';
        wp_nonce_field('ppdb_save_form', 'ppdb_form_nonce');
        echo '<input type="hidden" name="id" value="' . esc_attr($form->id ?? 0) . '" />';
        echo '<div class="ppdb-grid">';
        echo '<label>' . esc_html__('Nama Form', 'ppdb-form') . '<input type="text" name="name" value="' . esc_attr($form->name ?? '') . '" required></label>';
        echo '<label>' . esc_html__('Keterangan', 'ppdb-form') . '<textarea name="description" rows="2">' . esc_textarea($form->description ?? '') . '</textarea></label>';
        echo '<label>' . esc_html__('Pesan Ketika Berhasil Mendaftar', 'ppdb-form') . '<textarea name="success_message" rows="2">' . esc_textarea($form->success_message ?? '') . '</textarea></label>';

        // Multi-step configuration
        $steps_config = $form->steps_config ?? null;
        $steps_data = $steps_config ? json_decode($steps_config, true) : null;
        $is_multistep = !empty($steps_data['enabled']);

        echo '<label class="ppdb-checkbox"><input type="checkbox" name="enable_multistep" value="1" ' . checked($is_multistep, true, false) . '> ' . esc_html__('Aktifkan Multi-Step Form', 'ppdb-form') . '</label>';
        echo '<label class="ppdb-checkbox"><input type="checkbox" name="is_active" ' . checked((int) ($form->is_active ?? 1), 1, false) . '> ' . esc_html__('Active', 'ppdb-form') . '</label>';
        echo '</div>';
        echo '<p><button class="button button-primary">' . esc_html__('Simpan Form', 'ppdb-form') . '</button> ';
        if ($form && (int) ($form->id ?? 0) > 0) {
            $steps_builder_url = wp_nonce_url(admin_url('admin.php?page=ppdb-form&action=steps&id=' . (int) $form->id), 'ppdb_steps_' . (int) $form->id);
            echo '<a class="button" href="' . esc_url($steps_builder_url) . '">' . esc_html__('Kelola Steps (Drag & Drop)', 'ppdb-form') . '</a>';
        }
        echo '</p>';
        echo '</form>';
        echo '</div>';

        $forms = $wpdb->get_results('SELECT * FROM ' . self::get_forms_table() . ' ORDER BY id DESC');
        echo '<h2>' . esc_html__('Daftar Form', 'ppdb-form') . '</h2>';
        echo '<div class="ppdb-card">';
        echo '<input type="text" class="ppdb-search" placeholder="' . esc_attr__('Cari Form', 'ppdb-form') . '" onkeyup="ppdbFilterTable(this, \'.ppdb-table\')">';
        echo '<table class="ppdb-table">';
        echo '<thead><tr><th>' . esc_html__('Nama Form', 'ppdb-form') . '</th><th>Shortcode</th><th>' . esc_html__('Keterangan', 'ppdb-form') . '</th><th>' . esc_html__('Pesan Sukses', 'ppdb-form') . '</th><th>' . esc_html__('Status', 'ppdb-form') . '</th><th>' . esc_html__('Aksi', 'ppdb-form') . '</th></tr></thead><tbody>';
        foreach ($forms as $f) {
            $shortcode = '[simpel_pendaftaran id="' . (int) $f->id . '"]';
            echo '<tr>';
            echo '<td>' . esc_html($f->name) . '</td>';
            echo '<td><code>' . esc_html($shortcode) . '</code></td>';
            echo '<td>' . esc_html(wp_trim_words((string) $f->description, 12)) . '</td>';
            echo '<td>' . esc_html(wp_trim_words((string) $f->success_message, 12)) . '</td>';
            echo '<td>' . ((int) $f->is_active === 1 ? '<span class="ppdb-badge success">Aktif</span>' : '<span class="ppdb-badge muted">Nonaktif</span>') . '</td>';
            echo '<td class="ppdb-actions">';
            $fields_url = wp_nonce_url(admin_url('admin.php?page=ppdb-form&action=fields&id=' . (int) $f->id), 'ppdb_edit_fields_' . (int) $f->id);
            $view_url = admin_url('admin.php?page=ppdb-form-registrants&form_id=' . (int) $f->id);
            $edit_url = admin_url('admin.php?page=ppdb-form&edit=' . (int) $f->id);
            $delete_url = wp_nonce_url(admin_url('admin.php?page=ppdb-form&action=delete&id=' . (int) $f->id), 'ppdb_delete_form_' . (int) $f->id);
            echo '<a class="ppdb-icon btn-yellow dashicons dashicons-admin-generic" title="Pengaturan Field" href="' . esc_url($fields_url) . '"></a>';
            echo '<a class="ppdb-icon btn-blue dashicons dashicons-visibility" title="Lihat Data" href="' . esc_url($view_url) . '"></a>';
            echo '<a class="ppdb-icon btn-green dashicons dashicons-edit" title="Edit" href="' . esc_url($edit_url) . '"></a>';
            echo '<a class="ppdb-icon btn-red dashicons dashicons-trash" title="Hapus" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Hapus form dan semua data pendaftar?', 'ppdb-form')) . '\');"></a>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Simple steps builder page (drag & drop using jQuery UI Sortable)
     */
    private static function render_steps_builder(int $form_id): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string) $_GET['_wpnonce'], 'ppdb_steps_' . $form_id)) {
            wp_die(__('Token tidak valid.', 'ppdb-form'));
        }
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d', $form_id));
        if (!$form) {
            wp_die(__('Form tidak ditemukan.', 'ppdb-form'));
        }

        if (isset($_POST['ppdb_steps_nonce']) && wp_verify_nonce((string) $_POST['ppdb_steps_nonce'], 'ppdb_save_steps_' . $form_id)) {
            $payload = wp_unslash((string) ($_POST['ppdb_steps_json'] ?? ''));
            
            // Validate JSON payload
            if (empty(trim($payload))) {
                echo '<div class="error"><p>' . esc_html__('Data steps kosong.', 'ppdb-form') . '</p></div>';
                return;
            }
            
            $decoded = json_decode($payload, true);
            
            // Check for JSON decode errors
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo '<div class="error"><p>' . esc_html__('Format JSON steps tidak valid: ', 'ppdb-form') . json_last_error_msg() . '</p></div>';
                return;
            }
            if (is_array($decoded)) {
                $steps_config = wp_json_encode(['enabled' => true, 'steps' => $decoded]);
                // Use transaction for data integrity
                $wpdb->query('START TRANSACTION');
                try {
                    $result = $wpdb->update(self::get_forms_table(), ['steps_config' => $steps_config], ['id' => $form_id], ['%s'], ['%d']);
                    if ($result !== false) {
                        $wpdb->query('COMMIT');
                    } else {
                        $wpdb->query('ROLLBACK');
                        echo '<div class="error"><p>' . esc_html__('Gagal menyimpan steps.', 'ppdb-form') . '</p></div>';
                        return;
                    }
                } catch (Exception $e) {
                    $wpdb->query('ROLLBACK');
                    echo '<div class="error"><p>' . esc_html__('Terjadi kesalahan saat menyimpan steps.', 'ppdb-form') . '</p></div>';
                    return;
                }
                echo '<div class="updated"><p>' . esc_html__('Steps berhasil disimpan.', 'ppdb-form') . '</p></div>';
                $form->steps_config = $steps_config;
            } else {
                echo '<div class="error"><p>' . esc_html__('Format steps tidak valid.', 'ppdb-form') . '</p></div>';
            }
        }

        $registry = PPDB_Form_Plugin::get_field_registry();
        $steps_data = $form->steps_config ? json_decode((string) $form->steps_config, true) : null;
        $steps = $steps_data['steps'] ?? [['id' => 'step-1', 'title' => 'Langkah 1', 'description' => '', 'fields' => []]];
        $fields_config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
        // Build used fields set
        $used = [];
        foreach ($steps as $st) {
            foreach (($st['fields'] ?? []) as $fk) {
                $used[$fk] = true;
            }
        }
        // Compute missing required fields (enabled+required but not placed)
        $missing_required = [];
        foreach ($fields_config as $k => $cfg) {
            if (!empty($cfg['enabled']) && !empty($cfg['required']) && empty($used[$k])) {
                $missing_required[] = $k;
            }
        }

        echo '<div class="wrap"><h1>' . esc_html__('Kelola Steps', 'ppdb-form') . ' - ' . esc_html($form->name) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('ppdb_save_steps_' . $form_id, 'ppdb_steps_nonce');
        echo '<div id="ppdb-steps-builder" style="display:flex; gap:20px; align-items:flex-start;">';
        echo '<div class="ppdb-card" style="flex:1; min-width:260px;">';
        echo '<h2 style="margin-bottom:8px;">' . esc_html__('Field Tersedia', 'ppdb-form') . '</h2>';
        echo '<div style="display:flex; gap:8px; align-items:center; margin-bottom:8px;">';
        echo '  <input type="text" id="ppdb-avail-search" class="regular-text" placeholder="' . esc_attr__('Cari field...', 'ppdb-form') . '" style="flex:1;" />';
        echo '  <label style="white-space:nowrap;"><input type="checkbox" id="ppdb-show-all-fields" /> ' . esc_html__('Tampilkan semua', 'ppdb-form') . '</label>';
        echo '</div>';
        echo '<ul id="ppdb-available" class="ppdb-sortable" style="min-height:200px; border:1px solid #e5e7eb; padding:10px; background:#fff;">';
        foreach ($registry as $key => $meta) {
            if (!empty($used[$key])) {
                continue;  // do not show if already used in a step
            }
            $is_enabled = (int) ($fields_config[$key]['enabled'] ?? 0) === 1;
            $is_required = (int) ($fields_config[$key]['required'] ?? 0) === 1;
            $classes = 'ppdb-item' . ($is_enabled ? '' : ' ppdb-disabled');
            $style = 'padding:6px 10px; border:1px solid #e5e7eb; margin:6px 0; background:#fafafa; cursor:move;';
            $badge = $is_required ? ' <span class="ppdb-badge-req">' . esc_html__('Wajib', 'ppdb-form') . '</span>' : '';
            if (!$is_enabled) {
                $badge .= ' <span class="ppdb-badge-dis">' . esc_html__('Nonaktif', 'ppdb-form') . '</span>';
            }
            $label = isset($meta['label']) ? (string) $meta['label'] : (string) $key;
            echo '<li class="' . esc_attr($classes) . '" data-key="' . esc_attr($key) . '" data-enabled="' . ($is_enabled ? '1' : '0') . '" data-label="' . esc_attr($label) . '" style="' . esc_attr($style) . '">' . esc_html($label) . ' <code>' . esc_html($key) . '</code>' . $badge . '</li>';
        }
        echo '</ul></div>';
        echo '<div style="flex:2;">';
        // Missing required indicator
        if (!empty($missing_required)) {
            echo '<div class="notice notice-warning" style="margin-bottom:12px;"><p>' . esc_html__('Beberapa field wajib belum ditempatkan:', 'ppdb-form') . ' ';
            foreach ($missing_required as $mk) {
                echo '<code style="margin-right:6px;">' . esc_html($mk) . '</code>';
            }
            echo '</p></div>';
        }

        echo '<div id="ppdb-steps-container">';
        foreach ($steps as $st) {
            $sid = isset($st['id']) ? (string) $st['id'] : ('step-' . md5(json_encode($st)));
            echo '<div class="ppdb-step-panel" data-step-id="' . esc_attr($sid) . '" style="border:1px solid #e5e7eb; padding:12px; margin-bottom:16px; background:#fff;">';
            echo '<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;">';
            echo '<span class="ppdb-step-handle dashicons dashicons-move" title="Drag step" style="cursor:move;"></span>';
            echo '<label>Judul <input type="text" class="ppdb-step-title" value="' . esc_attr($st['title'] ?? '') . '" /></label>';
            echo '<label style="margin-left:10px;">Deskripsi <input type="text" class="ppdb-step-desc" value="' . esc_attr($st['description'] ?? '') . '" /></label>';
            echo '<button type="button" class="button link-dup-step" style="margin-left:auto;">' . esc_html__('Duplikasi Step', 'ppdb-form') . '</button>';
            echo '</div>';
            echo '<ul class="ppdb-sortable ppdb-step-fields" style="min-height:160px; border:1px dashed #cbd5e1; padding:10px; margin-top:8px; background:#f8fafc;">';
            foreach (($st['fields'] ?? []) as $fk) {
                if (!isset($registry[$fk])) {
                    continue;
                }
                echo '<li class="ppdb-item" data-key="' . esc_attr($fk) . '" style="padding:6px 10px; border:1px solid #e5e7eb; margin:6px 0; background:#fff; cursor:move;">' . esc_html($registry[$fk]['label']) . ' <code>' . esc_html($fk) . '</code></li>';
            }
            echo '</ul>';
            echo '<button type="button" class="button link-delete-step" style="margin-top:6px;">' . esc_html__('Hapus Step', 'ppdb-form') . '</button>';
            echo '</div>';
        }
        echo '</div>';  // container
        echo '<p><button type="button" class="button" id="ppdb-add-step">' . esc_html__('Tambah Step', 'ppdb-form') . '</button></p>';
        echo '</div></div>';
        echo '<input type="hidden" name="ppdb_steps_json" id="ppdb_steps_json" />';
        echo '<p><button class="button button-primary" id="ppdb-save-steps">' . esc_html__('Simpan Steps', 'ppdb-form') . '</button> ';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=ppdb-form&edit=' . (int) $form_id)) . '">' . esc_html__('Kembali', 'ppdb-form') . '</a></p>';
        echo '</form></div>';

        ?>
        <script>
        jQuery(function($){
          function normalizeAvailable(){
            var used = {};
            $('.ppdb-step-fields .ppdb-item').each(function(){ used[$(this).data('key')] = true; });
            $('#ppdb-available .ppdb-item').each(function(){ var k = $(this).data('key'); if(used[k]) $(this).remove(); });
          }
          function applyAvailableFilters(){
            var q = ($('#ppdb-avail-search').val() || '').toString().toLowerCase();
            var showAll = $('#ppdb-show-all-fields').is(':checked');
            $('#ppdb-available .ppdb-item').each(function(){
              var $it = $(this);
              var label = ($it.data('label') || '') + ' ' + ($it.data('key') || '');
              var match = q === '' || label.toString().toLowerCase().indexOf(q) !== -1;
              var enabled = String($it.data('enabled')) === '1';
              if(!match){ $it.hide(); return; }
              if(!showAll && !enabled){ $it.hide(); return; }
              $it.show();
            });
          }
          function ensureUnique(){
            var seen = {};
            $('.ppdb-step-fields .ppdb-item').each(function(){ var k = $(this).data('key'); if(seen[k]) { $(this).remove(); } else { seen[k] = true; } });
            normalizeAvailable();
          }
          function refreshSortable(ctx){
            (ctx || $(document)).find('.ppdb-sortable').sortable({
              connectWith: '.ppdb-sortable',
              placeholder: 'ppdb-placeholder',
              receive: function(event, ui){
                var toStep = $(this).hasClass('ppdb-step-fields');
                var enabled = String(ui.item.data('enabled')) === '1' || ui.item.data('enabled') === undefined;
                if(toStep && !enabled){
                  $(this).sortable('cancel');
                  $('#ppdb-available').append(ui.item);
                  applyAvailableFilters();
                  return;
                }
                ensureUnique(); applyAvailableFilters();
              },
              update: function(){ ensureUnique(); applyAvailableFilters(); }
            }).disableSelection();
          }
          $('#ppdb-steps-container').sortable({ items: '> .ppdb-step-panel', handle: '.ppdb-step-handle', placeholder: 'ppdb-placeholder' });
          refreshSortable();
          normalizeAvailable();
          applyAvailableFilters();
          $(document).on('keyup', '#ppdb-avail-search', applyAvailableFilters);
          $(document).on('change', '#ppdb-show-all-fields', applyAvailableFilters);
          $('#ppdb-add-step').on('click', function(){
            var sid = 'step-' + (Date.now()) + '-' + Math.floor(Math.random()*1000);
            var panel = $('<div class="ppdb-step-panel" data-step-id="'+sid+'" style="border:1px solid #e5e7eb; padding:12px; margin-bottom:16px; background:#fff;"></div>');
            panel.append('<div style="display:flex; align-items:center; gap:10px; margin-bottom:6px;"><span class="ppdb-step-handle dashicons dashicons-move" title="Drag step" style="cursor:move;"></span> <label>Judul <input type="text" class="ppdb-step-title" value="Langkah Baru" /></label> <label style="margin-left:10px;">Deskripsi <input type="text" class="ppdb-step-desc" value="" /></label> <button type="button" class="button link-dup-step" style="margin-left:auto;">Duplikasi Step</button></div>');
            panel.append('<ul class="ppdb-sortable ppdb-step-fields" style="min-height:160px; border:1px dashed #cbd5e1; padding:10px; margin-top:8px; background:#f8fafc;"></ul>');
            panel.append('<button type="button" class="button link-delete-step" style="margin-top:6px;">Hapus Step</button>');
            $('#ppdb-steps-container').append(panel);
            refreshSortable(panel);
          });
          $(document).on('click', '.link-delete-step', function(){ $(this).closest('.ppdb-step-panel').remove(); });
          $(document).on('click', '.link-dup-step', function(){
            var src = $(this).closest('.ppdb-step-panel');
            var clone = src.clone(true, true);
            var sid = 'step-' + (Date.now()) + '-' + Math.floor(Math.random()*1000);
            clone.attr('data-step-id', sid);
            src.after(clone); refreshSortable(clone); ensureUnique();
          });
          $('#ppdb-save-steps').on('click', function(){
             var steps = [];
             $('.ppdb-step-panel').each(function(){
                var title = $(this).find('.ppdb-step-title').val();
                var desc = $(this).find('.ppdb-step-desc').val();
                var sid = $(this).attr('data-step-id') || ('step-'+Math.random());
                var fields = [];
                $(this).find('.ppdb-step-fields .ppdb-item').each(function(){ fields.push($(this).data('key')); });
                steps.push({id:sid, title:title, description:desc, fields:fields});
             });
             $('#ppdb_steps_json').val(JSON.stringify(steps));
          });
        });
        </script>
        <?php
        echo '<style>
          .ppdb-placeholder{border:2px dashed #93c5fd; height:34px; margin:6px 0; background:#eff6ff}
          #ppdb-available .ppdb-disabled{opacity:.55; background:#f3f4f6}
          .ppdb-badge-req{display:inline-block;background:#10b981;color:#fff;border-radius:4px;padding:2px 6px;font-size:11px;margin-left:6px}
          .ppdb-badge-dis{display:inline-block;background:#9ca3af;color:#fff;border-radius:4px;padding:2px 6px;font-size:11px;margin-left:4px}
        </style>';
    }

    private static function render_form_fields_page(int $form_id): void
    {
        if (!wp_verify_nonce((string) ($_REQUEST['_wpnonce'] ?? ''), 'ppdb_edit_fields_' . $form_id)) {
            wp_die(__('Token tidak valid.', 'ppdb-form'));
        }
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d', $form_id));
        if (!$form) {
            echo '<div class="error"><p>' . esc_html__('Form tidak ditemukan.', 'ppdb-form') . '</p></div>';
            return;
        }

        if (isset($_POST['ppdb_fields_nonce']) && wp_verify_nonce((string) $_POST['ppdb_fields_nonce'], 'ppdb_save_fields_' . $form_id)) {
            $registry = PPDB_Form_Plugin::get_field_registry();
            $config = [];
            foreach ($registry as $key => $_meta) {
                $config[$key] = [
                    'enabled' => isset($_POST['enabled'][$key]) ? 1 : 0,
                    'required' => isset($_POST['required'][$key]) ? 1 : 0,
                ];
            }
            $wpdb->update(self::get_forms_table(), ['fields_json' => wp_json_encode($config), 'updated_at' => current_time('mysql')], ['id' => $form_id], ['%s', '%s'], ['%d']);
            echo '<div class="updated"><p>' . esc_html__('Pengaturan field disimpan.', 'ppdb-form') . '</p></div>';
            $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_forms_table() . ' WHERE id = %d', $form_id));
        }

        $config = $form->fields_json ? json_decode((string) $form->fields_json, true) : [];
        $registry = PPDB_Form_Plugin::get_field_registry();

        echo '<div class="wrap ppdb-admin">';
        echo '<h2>' . esc_html__('Pengaturan Form', 'ppdb-form') . ' ' . esc_html($form->name) . '</h2>';
        echo '<div class="ppdb-card">';
        echo '<form method="post">';
        wp_nonce_field('ppdb_save_fields_' . $form_id, 'ppdb_fields_nonce');
        echo '<table class="ppdb-table">';
        echo '<thead><tr><th>' . esc_html__('Category', 'ppdb-form') . '</th><th>' . esc_html__('Field', 'ppdb-form') . '</th><th style="text-align:center">Opsi</th><th style="text-align:center">Required</th></tr></thead><tbody>';

        $current_cat = '';
        foreach ($registry as $key => $meta) {
            $cat = $meta['category'];
            $enabled = (int) ($config[$key]['enabled'] ?? 0);
            $required = (int) ($config[$key]['required'] ?? 0);
            echo '<tr>';
            echo '<td>' . ($cat !== $current_cat ? '<strong>' . esc_html($cat) . '</strong>' : '') . '</td>';
            echo '<td>' . esc_html($meta['label']) . '</td>';
            echo '<td class="center"><label class="ppdb-checkbox"><input type="checkbox" name="enabled[' . esc_attr($key) . ']" ' . checked($enabled, 1, false) . '></label></td>';
            echo '<td class="center"><label class="ppdb-checkbox"><input type="checkbox" name="required[' . esc_attr($key) . ']" ' . checked($required, 1, false) . '></label></td>';
            echo '</tr>';
            $current_cat = $cat;
        }
        echo '</tbody></table>';
        echo '<p><button class="button button-primary">' . esc_html__('Simpan', 'ppdb-form') . '</button></p>';
        echo '</form>';
        echo '</div>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=ppdb-form')) . '">' . esc_html__('Kembali ke daftar form', 'ppdb-form') . '</a></p>';
        echo '</div>';
    }

    /* ---------- Registrants Page ---------- */
    public static function render_registrants_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }
        global $wpdb;

        $form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            check_admin_referer('ppdb_export_registrants');
            self::export_registrants_csv($form_id);
            return;
        }
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
            $sid = (int) $_GET['id'];
            check_admin_referer('ppdb_delete_submission_' . $sid);
            $wpdb->delete(self::get_submissions_table(), ['id' => $sid], ['%d']);
            echo '<div class="updated"><p>' . esc_html__('Data pendaftar dihapus.', 'ppdb-form') . '</p></div>';
        }

        $forms = $wpdb->get_results('SELECT id, name FROM ' . self::get_forms_table() . ' ORDER BY id DESC');
        // Server-side pagination & search
        $per_page = max(1, (int) ($_GET['per_page'] ?? 20));
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $offset = ($page - 1) * $per_page;
        $search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';

        $tbl_rows = self::get_submissions_table();
        $where_sql = [];
        $where_args = [];
        if ($form_id > 0) {
            $where_sql[] = 'form_id = %d';
            $where_args[] = $form_id;
        }
        if ($search !== '') {
            $where_sql[] = 'submission_data LIKE %s';
            $where_args[] = '%' . $wpdb->esc_like($search) . '%';
        }
        $where_clause = !empty($where_sql) ? ('WHERE ' . implode(' AND ', $where_sql)) : '';

        $total_rows = !empty($where_args)
            ? (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $tbl_rows . ' ' . $where_clause, $where_args))
            : (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tbl_rows);

        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . $tbl_rows . ' ' . $where_clause . ' ORDER BY id DESC LIMIT %d OFFSET %d', array_merge($where_args, [$per_page, $offset])));

        // stats
        $tbl = self::get_submissions_table();
        $today = current_time('Y-m-d');
        if ($form_id > 0) {
            $where_count = 'WHERE form_id = ' . (int) $form_id;
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tbl} {$where_count}");
        } else {
            $where_count = '';
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tbl}");
        }
        $today_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $tbl . " {$where_count} " . ($where_count ? ' AND ' : ' WHERE ') . ' DATE(created_at) = %s', $today));
        $week_count = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $tbl . " {$where_count} " . ($where_count ? ' AND ' : ' WHERE ') . ' YEARWEEK(created_at, 1) = YEARWEEK(%s, 1)', current_time('mysql')));

        echo '<div class="wrap ppdb-admin">';
        echo '<h2>' . esc_html__('Data PPDB', 'ppdb-form') . '</h2>';
        echo '<div class="ppdb-stats">';
        echo '<div class="ppdb-stat"><div class="num">' . esc_html((string) $total) . '</div><div class="label">' . esc_html__('Total Pendaftar', 'ppdb-form') . '</div></div>';
        echo '<div class="ppdb-stat"><div class="num">' . esc_html((string) $today_count) . '</div><div class="label">' . esc_html__('Pendaftar Hari Ini', 'ppdb-form') . '</div></div>';
        echo '<div class="ppdb-stat"><div class="num">' . esc_html((string) $week_count) . '</div><div class="label">' . esc_html__('Pendaftar Minggu Ini', 'ppdb-form') . '</div></div>';
        echo '</div>';

        echo '<div class="ppdb-card">';
        echo '<div class="ppdb-toolbar">';
        echo '<form method="get" style="margin-right:10px">';
        echo '<input type="hidden" name="page" value="ppdb-form-registrants">';
        echo '<select name="form_id">';
        echo '<option value="0">' . esc_html__('Semua Form', 'ppdb-form') . '</option>';
        foreach ($forms as $f) {
            echo '<option value="' . (int) $f->id . '" ' . selected($form_id, (int) $f->id, false) . '>' . esc_html($f->name) . '</option>';
        }
        echo '</select> ';
        echo '<input type="text" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Cari...', 'ppdb-form') . '" /> ';
        echo '<select name="per_page"><option ' . selected($per_page, 20, false) . ' value="20">20</option><option ' . selected($per_page, 50, false) . ' value="50">50</option><option ' . selected($per_page, 100, false) . ' value="100">100</option></select> ';
        echo '<button class="button">' . esc_html__('Terapkan', 'ppdb-form') . '</button>';
        echo '</form>';
        // Fix JS string: pass literal CSS selector
        $selector = '.ppdb-table';
        echo '<input type="text" class="ppdb-search" placeholder="' . esc_attr__('Cari di semua kolom (client-side)', 'ppdb-form') . '" onkeyup="ppdbFilterTable(this, \'' . $selector . '\')">';
        echo '<input type="button" name="export_current_filter" class="button button-primary" value="' . esc_attr__('Export to Excel', 'ppdb-form') . '" style="margin-left:auto">';
        echo '</div>';

        echo '<table class="ppdb-table">';
        echo '<thead><tr><th>ID</th><th>Form</th><th>' . esc_html__('Waktu', 'ppdb-form') . '</th><th>Nama</th><th>Telepon</th><th>Email</th><th>Jurusan</th><th>OPSI</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $data = json_decode((string) $r->submission_data, true) ?: [];
            $form_name = '';
            foreach ($forms as $f) {
                if ((int) $f->id === (int) $r->form_id) {
                    $form_name = $f->name;
                    break;
                }
            }
            $detail_url = add_query_arg(['page' => 'ppdb-form-registrants', 'view' => (int) $r->id, 'form_id' => $form_id], admin_url('admin.php'));
            $delete_url = wp_nonce_url(add_query_arg(['page' => 'ppdb-form-registrants', 'action' => 'delete', 'id' => (int) $r->id, 'form_id' => $form_id], admin_url('admin.php')), 'ppdb_delete_submission_' . (int) $r->id);
            echo '<tr>';
            echo '<td>' . (int) $r->id . '</td>';
            echo '<td>' . esc_html($form_name) . '</td>';
            echo '<td>' . esc_html(mysql2date('Y-m-d H:i', (string) $r->created_at)) . '</td>';
            echo '<td>' . esc_html($data['nama_lengkap'] ?? '-') . '</td>';
            echo '<td>' . esc_html($data['nomor_telepon'] ?? '-') . '</td>';
            echo '<td>' . esc_html($data['email'] ?? '-') . '</td>';
            echo '<td>' . esc_html($data['jurusan'] ?? '-') . '</td>';
            echo '<td class="ppdb-actions">';
            echo '<a class="ppdb-icon btn-blue dashicons dashicons-visibility" title="Detail" href="' . esc_url($detail_url) . '"></a>';
            echo '<a class="ppdb-icon btn-red dashicons dashicons-trash" title="Hapus" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Hapus data ini?', 'ppdb-form')) . '\');"></a>';
            echo '</td>';
            echo '</tr>';
            if (isset($_GET['view']) && (int) $_GET['view'] === (int) $r->id) {
                echo '<tr class="ppdb-detail-row"><td colspan="8">';
                echo '<div class="ppdb-detail">';
                foreach ($data as $k => $v) {
                    echo '<div class="ppdb-detail-item"><strong>' . esc_html($k) . ':</strong> ' . esc_html(is_array($v) ? implode(', ', $v) : (string) $v) . '</div>';
                }
                echo '</div>';
                echo '</td></tr>';
            }
        }
        echo '</tbody></table>';
        // Pagination links
        $total_pages = (int) ceil($total_rows / $per_page);
        if ($total_pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            for ($p = 1; $p <= $total_pages; $p++) {
                $url = add_query_arg(['page' => 'ppdb-form-registrants', 'form_id' => $form_id, 's' => $search, 'per_page' => $per_page, 'paged' => $p], admin_url('admin.php'));
                $class = $p === $page ? ' class="page-numbers current"' : ' class="page-numbers"';
                echo '<a' . $class . ' href="' . esc_url($url) . '">' . (int) $p . '</a> ';
            }
            echo '</div></div>';
        }
        echo '</div>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=ppdb-form')) . '">' . esc_html__('Kembali ke daftar form', 'ppdb-form') . '</a></p>';
        
        // Add export field selection modal
        self::render_export_modal();
        
        echo '</div>';
    }

    /**
     * Render export field selection modal
     */
    private static function render_export_modal(): void
    {
        $field_registry = PPDB_Form_Plugin::get_field_registry();
        $saved_fields = get_option('ppdb_export_selected_fields', []);
        
        // Group fields by category
        $grouped_fields = [];
        foreach ($field_registry as $key => $meta) {
            $category = $meta['category'] ?? 'Lainnya';
            $grouped_fields[$category][] = ['key' => $key, 'meta' => $meta];
        }
        
        echo '<div id="ppdb-export-modal" class="ppdb-modal" style="display:none;">';
        echo '<div class="ppdb-modal-content">';
        echo '<div class="ppdb-modal-header">';
        echo '<h2>' . esc_html__('Pilih Field untuk Export CSV', 'ppdb-form') . '</h2>';
        echo '<span class="ppdb-modal-close">&times;</span>';
        echo '</div>';
        
        echo '<form id="ppdb-export-form" method="post">';
        wp_nonce_field('ppdb_export_fields', 'ppdb_export_nonce');
        echo '<input type="hidden" name="ppdb_export_action" value="configure_fields">';
        echo '<input type="hidden" name="ppdb_export_type" value="">';
        echo '<input type="hidden" name="ppdb_form_id" value="">';
        
        echo '<div class="ppdb-modal-body">';
        echo '<div class="ppdb-export-notice">';
        echo '<p><strong>' . esc_html__('Info:', 'ppdb-form') . '</strong> ' . esc_html__('Pilih field yang ingin diexport ke CSV. Field yang dipilih akan menjadi kolom dalam file Excel.', 'ppdb-form') . '</p>';
        echo '</div>';
        
        echo '<div class="ppdb-field-groups">';
        
        foreach ($grouped_fields as $category => $fields) {
            echo '<div class="ppdb-field-group">';
            echo '<h3 class="ppdb-category-header">';
            echo '<label>';
            echo '<input type="checkbox" class="ppdb-category-toggle" data-category="' . esc_attr(sanitize_title($category)) . '">';
            echo '<strong>' . esc_html($category) . '</strong>';
            echo '</label>';
            echo '</h3>';
            
            echo '<div class="ppdb-fields-list" data-category="' . esc_attr(sanitize_title($category)) . '">';
            foreach ($fields as $field) {
                $key = $field['key'];
                $label = $field['meta']['label'];
                $checked = in_array($key, $saved_fields) ? 'checked' : '';
                
                echo '<label class="ppdb-field-item">';
                echo '<input type="checkbox" name="export_fields[]" value="' . esc_attr($key) . '" ' . $checked . '>';
                echo '<span class="ppdb-field-label">' . esc_html($label) . '</span>';
                echo '<span class="ppdb-field-key">(' . esc_html($key) . ')</span>';
                echo '</label>';
            }
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '<div class="ppdb-modal-footer">';
        echo '<button type="button" class="button ppdb-select-all">' . esc_html__('Pilih Semua', 'ppdb-form') . '</button>';
        echo '<button type="button" class="button ppdb-select-none">' . esc_html__('Hapus Semua', 'ppdb-form') . '</button>';
        echo '<button type="button" class="button button-secondary ppdb-modal-cancel">' . esc_html__('Batal', 'ppdb-form') . '</button>';
        echo '<button type="submit" class="button button-primary ppdb-export-submit">' . esc_html__('Export CSV', 'ppdb-form') . '</button>';
        echo '</div>';
        
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Handle export field configuration from modal
     */
    private static function handle_export_field_configuration(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }

        if (!isset($_POST['ppdb_export_nonce']) || !wp_verify_nonce($_POST['ppdb_export_nonce'], 'ppdb_export_fields')) {
            wp_die(__('Nonce verification failed', 'ppdb-form'));
        }

        $selected_fields = isset($_POST['export_fields']) ? array_map('sanitize_text_field', $_POST['export_fields']) : [];
        $export_type = sanitize_text_field($_POST['ppdb_export_type'] ?? '');
        $form_id = (int) ($_POST['ppdb_form_id'] ?? 0);

        // Validate selected fields against registry
        $field_registry = PPDB_Form_Plugin::get_field_registry();
        $valid_fields = array_filter($selected_fields, function($field) use ($field_registry) {
            return isset($field_registry[$field]);
        });

        if (empty($valid_fields)) {
            wp_die(__('Tidak ada field yang dipilih untuk export.', 'ppdb-form'));
        }

        // Save selected fields to WordPress options
        update_option('ppdb_export_selected_fields', $valid_fields);

        // Clean any output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Perform export based on type
        switch ($export_type) {
            case 'current_filter':
                self::export_current_filter_as_csv();
                break;
            case 'registrants':
                self::export_registrants_csv($form_id);
                break;
            case 'selected':
                $submission_ids = [];
                if (isset($_POST['submission_ids'])) {
                    // Handle comma-separated string from JavaScript
                    if (is_string($_POST['submission_ids'])) {
                        $submission_ids = array_map('intval', explode(',', $_POST['submission_ids']));
                    } else {
                        $submission_ids = array_map('intval', $_POST['submission_ids']);
                    }
                }
                self::export_selected_submissions($submission_ids);
                break;
            default:
                self::export_current_filter_as_csv();
                break;
        }
    }

    /**
     * Get selected fields for export or return default if none selected
     */
    private static function get_export_fields(): array
    {
        $selected_fields = get_option('ppdb_export_selected_fields', []);
        
        // If no fields selected, require admin to configure first
        if (empty($selected_fields)) {
            return [];
        }

        $field_registry = PPDB_Form_Plugin::get_field_registry();
        
        // Filter out invalid fields and maintain order
        return array_filter($selected_fields, function($field) use ($field_registry) {
            return isset($field_registry[$field]);
        });
    }

    /**
     * Check if export fields are configured
     */
    private static function are_export_fields_configured(): bool
    {
        $selected_fields = get_option('ppdb_export_selected_fields', []);
        return !empty($selected_fields);
    }

    private static function export_registrants_csv(int $form_id): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Tidak diizinkan', 'ppdb-form'));
        }

        global $wpdb;
        $submissions_table = self::get_submissions_table();
        $forms_table = self::get_forms_table();

        $where = '';
        $args = [];
        if ($form_id > 0) {
            $where = 'WHERE s.form_id = %d';
            $args[] = $form_id;
        }

        $sql = "SELECT s.*, f.name AS form_name FROM {$submissions_table} s LEFT JOIN {$forms_table} f ON s.form_id = f.id {$where} ORDER BY s.created_at ASC";
        $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

        if (empty($rows)) {
            wp_die(__('Tidak ada data untuk diekspor', 'ppdb-form'));
        }

        // Collect all unique fields
        $all_fields = [];
        $registry = PPDB_Form_Plugin::get_field_registry();
        foreach ($rows as $r) {
            $data = json_decode((string) $r->submission_data, true) ?: [];
            foreach (array_keys($data) as $field) {
                if (!in_array($field, $all_fields)) {
                    $all_fields[] = $field;
                }
            }
        }

        // Sort fields logically
        $priority_fields = ['nama_lengkap', 'email', 'nomor_telepon', 'tanggal_lahir', 'jenis_kelamin', 'alamat', 'jurusan', 'sekolah_asal'];
        $sorted_fields = [];
        foreach ($priority_fields as $field) {
            if (in_array($field, $all_fields)) {
                $sorted_fields[] = $field;
            }
        }
        foreach ($all_fields as $field) {
            if (!in_array($field, $sorted_fields)) {
                $sorted_fields[] = $field;
            }
        }

        // Create columns
        $columns = ['Nomor', 'Form ID', 'Tanggal Daftar'];
        foreach ($sorted_fields as $field) {
            $label = $registry[$field]['label'] ?? ucfirst(str_replace('_', ' ', $field));
            $columns[] = $label;
        }

        $form = null;
        if ($form_id > 0) {
            $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $forms_table . ' WHERE id = %d', $form_id));
        }

        nocache_headers();
        header('Content-Type: application/octet-stream; charset=UTF-8');
        $filename = 'ppdb-registrants-' . ($form ? sanitize_title((string) $form->name) : 'all') . '-' . wp_date('Ymd-His') . '.csv';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Create output buffer
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Get export settings
        $export_settings = get_option('ppdb_export_settings', ['delimiter' => ';']);
        $delimiter = $export_settings['delimiter'] ?? ';';
        
        // Write headers with configured delimiter
        fputcsv($output, $columns, $delimiter, '"');
        
        $nomor = 1;
        foreach ($rows as $r) {
            $data = json_decode((string) $r->submission_data, true) ?: [];
            $line = [$nomor, (int) $r->form_id, date('d/m/Y H:i', strtotime($r->created_at))];
            
            foreach ($sorted_fields as $field) {
                $v = $data[$field] ?? '';
                if (is_array($v)) {
                    $v = implode(', ', $v);
                } elseif (strpos($field, 'tanggal') !== false && !empty($v)) {
                    $v = date('d/m/Y', strtotime($v));
                } elseif (strpos($field, 'dok_') === 0 || strpos($field, 'file_') === 0) {
                    $v = !empty($v) ? 'File Uploaded' : 'Tidak ada file';
                }
                
                // Clean data
                $v = trim((string) $v);
                if (preg_match('/^[=+\-@]/', $v) === 1) {
                    $v = "'" . $v;
                }
                $v = str_replace(["\r", "\n", "\r\n"], ' ', $v);
                
                $line[] = $v;
            }
            
            fputcsv($output, $line, $delimiter, '"');
            $nomor++;
        }
        
        // Output the CSV
        rewind($output);
        echo stream_get_contents($output);
        fclose($output);
        exit;
    }

    /* ---------- Departments Page ---------- */
    public static function render_departments_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }
        global $wpdb;
        if (isset($_POST['ppdb_dept_nonce']) && wp_verify_nonce((string) $_POST['ppdb_dept_nonce'], 'ppdb_save_dept')) {
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $data = ['name' => $name, 'is_active' => $is_active];
            if ($id > 0) {
                $wpdb->update(self::get_departments_table(), $data, ['id' => $id], ['%s', '%d'], ['%d']);
            } else {
                $data['created_at'] = current_time('mysql');
                $wpdb->insert(self::get_departments_table(), $data, ['%s', '%d', '%s']);
            }
            echo '<div class="updated"><p>' . esc_html__('Data jurusan tersimpan.', 'ppdb-form') . '</p></div>';
        }
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
            $id = (int) $_GET['id'];
            check_admin_referer('ppdb_delete_dept_' . $id);
            $wpdb->delete(self::get_departments_table(), ['id' => $id], ['%d']);
            echo '<div class="updated"><p>' . esc_html__('Jurusan dihapus.', 'ppdb-form') . '</p></div>';
        }
        $editing_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
        $edit_row = $editing_id > 0 ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_departments_table() . ' WHERE id = %d', $editing_id)) : null;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::get_departments_table() . ' ORDER BY id DESC');
        echo '<div class="wrap ppdb-admin">';
        echo '<h2>' . esc_html__('Pengaturan Jurusan', 'ppdb-form') . '</h2>';
        echo '<div class="ppdb-card">';
        echo '<form method="post" class="ppdb-grid">';
        wp_nonce_field('ppdb_save_dept', 'ppdb_dept_nonce');
        echo '<input type="hidden" name="id" value="' . esc_attr($edit_row->id ?? 0) . '">';
        echo '<label>' . esc_html__('Nama Jurusan', 'ppdb-form') . '<input type="text" name="name" value="' . esc_attr($edit_row->name ?? '') . '" required></label>';
        echo '<label class="ppdb-checkbox"><input type="checkbox" name="is_active" ' . checked((int) ($edit_row->is_active ?? 1), 1, false) . '> ' . esc_html__('Active', 'ppdb-form') . '</label>';
        echo '<p><button class="button button-primary">' . esc_html__('Simpan', 'ppdb-form') . '</button></p>';
        echo '</form></div>';
        echo '<div class="ppdb-card">';
        echo '<input type="text" class="ppdb-search" placeholder="' . esc_attr__('Cari jurusan', 'ppdb-form') . '" onkeyup="ppdbFilterTable(this, \'.ppdb-table\')">';
        echo '<table class="ppdb-table">';
        echo '<thead><tr><th>' . esc_html__('Nomor', 'ppdb-form') . '</th><th>' . esc_html__('Nama', 'ppdb-form') . '</th><th>' . esc_html__('Status', 'ppdb-form') . '</th><th>' . esc_html__('Aksi', 'ppdb-form') . '</th></tr></thead><tbody>';
        $nomor = 1;
        foreach ($rows as $r) {
            $edit_url = admin_url('admin.php?page=ppdb-form-departments&edit=' . (int) $r->id);
            $delete_url = wp_nonce_url(admin_url('admin.php?page=ppdb-form-departments&action=delete&id=' . (int) $r->id), 'ppdb_delete_dept_' . (int) $r->id);
            echo '<tr><td>' . $nomor . '</td><td>' . esc_html($r->name) . '</td><td>' . ((int) $r->is_active === 1 ? '<span class="ppdb-badge success">Aktif</span>' : '<span class="ppdb-badge muted">Nonaktif</span>') . '</td><td class="ppdb-actions">' . '<a class="ppdb-icon btn-green dashicons dashicons-edit" href="' . esc_url($edit_url) . '"></a>' . '<a class="ppdb-icon btn-red dashicons dashicons-trash" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Hapus jurusan?', 'ppdb-form')) . '\');"></a>' . '</td></tr>';
            $nomor++;
        }
        echo '</tbody></table></div></div>';
    }

    private static function render_fallback_submissions_table(): void
    {
        global $wpdb;

        $submissions = $wpdb->get_results('
            SELECT s.*, f.name as form_name 
            FROM ' . self::get_submissions_table() . ' s 
            LEFT JOIN ' . self::get_forms_table() . ' f ON s.form_id = f.id 
            ORDER BY s.id DESC 
            LIMIT 20
        ');

        if (empty($submissions)) {
            echo '<p>Tidak ada data submission ditemukan.</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>ID</th>';
        echo '<th>Form</th>';
        echo '<th>Nama</th>';
        echo '<th>Email</th>';
        echo '<th>Telepon</th>';
        echo '<th>Tanggal</th>';
        echo '<th>Aksi</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($submissions as $submission) {
            $data = json_decode($submission->submission_data, true) ?: [];

            echo '<tr>';
            echo '<td>' . (int) $submission->id . '</td>';
            echo '<td>' . esc_html($submission->form_name ?: 'Unknown') . '</td>';
            echo '<td>' . esc_html($data['nama_lengkap'] ?? '-') . '</td>';
            echo '<td>' . esc_html($data['email'] ?? '-') . '</td>';
            echo '<td>' . esc_html($data['nomor_telepon'] ?? '-') . '</td>';
            echo '<td>' . esc_html($submission->created_at) . '</td>';
            echo '<td>';

            $view_url = add_query_arg([
                'page' => 'ppdb-form-registrants',
                'action' => 'view',
                'id' => (int) $submission->id,
            ], admin_url('admin.php'));

            $delete_url = wp_nonce_url(add_query_arg([
                'page' => 'ppdb-form-registrants',
                'action' => 'delete',
                'id' => (int) $submission->id,
            ], admin_url('admin.php')), 'ppdb_delete_submission_' . (int) $submission->id);

            echo '<a href="' . esc_url($view_url) . '" class="button button-small">👁️ Lihat</a> ';
            echo '<a href="' . esc_url($delete_url) . '" class="button button-small" onclick="return confirm(\'Hapus data ini?\')">🗑️ Hapus</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p style="margin-top: 15px;"><em>💡 Ini adalah tampilan fallback. Jika data muncul di sini tapi tidak di table utama, ada masalah dengan WP_List_Table implementation.</em></p>';
    }

    /* ---------- New WP_List_Table Implementation ---------- */
    public static function render_registrants_page_new(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }

        // Handle bulk actions
        if (isset($_POST['action']) && !empty($_POST['submission'])) {
            check_admin_referer('bulk-submissions');
            $action = sanitize_text_field((string) $_POST['action']);
            $ids = array_map('intval', (array) $_POST['submission']);

            switch ($action) {
                case 'delete':
                    global $wpdb;
                    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                    $wpdb->query($wpdb->prepare('DELETE FROM ' . self::get_submissions_table() . " WHERE id IN ({$placeholders})", $ids));
                    echo '<div class="updated"><p>' . esc_html__('Data terpilih telah dihapus.', 'ppdb-form') . '</p></div>';
                    break;

                case 'send_certificates':
                    $result = self::bulk_send_certificates($ids);
                    echo '<div class="updated"><p>' . esc_html($result['message']) . '</p></div>';
                    break;

                case 'download_certificates':
                case 'export_selected':
                    // These should be handled in early actions
                    return;
            }
        }

        // Handle single actions
        if (isset($_GET['action'])) {
            $action = sanitize_text_field((string) $_GET['action']);
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

            if ($action === 'delete' && $id > 0) {
                check_admin_referer('ppdb_delete_submission_' . $id);
                global $wpdb;
                $wpdb->delete(self::get_submissions_table(), ['id' => $id], ['%d']);
                echo '<div class="updated"><p>' . esc_html__('Data pendaftar dihapus.', 'ppdb-form') . '</p></div>';
            } elseif ($action === 'export') {
                // This should be handled in early actions, but fallback
                return;
            } elseif ($action === 'view' && $id > 0) {
                self::render_submission_detail($id);
                return;
            }
        }

        // Check if we should use fallback display
        global $wpdb;
        $raw_count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . self::get_submissions_table());

        if ($raw_count > 0 && !defined('WP_DEBUG')) {
            // Enable debug mode temporarily for this page
            if (!defined('WP_DEBUG')) {
                define('WP_DEBUG', true);
            }
        }

        $list_table = new PPDB_Form_Submissions_List_Table();
        $list_table->prepare_items();

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Data Pendaftar', 'ppdb-form') . '</h1>';
        // Export filtered CSV button
        echo ' <input type="button" name="export_current_filter" class="page-title-action" value="' . esc_attr__('Export CSV (Filter Saat Ini)', 'ppdb-form') . '">';
        echo '<hr class="wp-header-end">';

        // Show raw data count for debugging
        if ($raw_count > 0) {
            echo '<div class="notice notice-info"><p><strong>Info:</strong> Ada ' . $raw_count . ' data submission di database.</p></div>';
        }

        $list_table->views();

        echo '<form method="post">';
        $list_table->search_box(__('Cari pendaftar', 'ppdb-form'), 'ppdb-search');
        $list_table->display();
        echo '</form>';

        // Fallback: Show raw data only if list table is empty but data exists
        if ($raw_count > 0 && count($list_table->items) === 0) {
            echo '<div class="card" style="margin-top: 20px;">';
            echo '<h3>🔧 Fallback Data Display (Debug Mode)</h3>';
            echo '<p><strong>Status:</strong> WP_List_Table tidak memiliki items tapi ada ' . $raw_count . ' data di database.</p>';
            self::render_fallback_submissions_table();
            echo '</div>';
        }

        // Show debug info if WP_List_Table has items but might not display correctly
        if (defined('WP_DEBUG') && WP_DEBUG && $raw_count > 0 && count($list_table->items) > 0) {
            echo '<div class="notice notice-warning" style="margin-top: 20px;">';
            echo '<p><strong>Debug Info:</strong> WP_List_Table memiliki ' . count($list_table->items) . ' items. Jika data tidak muncul di atas, ada masalah dengan display_rows().</p>';
            echo '</div>';
        }

        // Add export field selection modal
        self::render_export_modal();
        
        echo '</div>';
    }

    /**
     * Handle CSV export properly without header conflicts
     */
    private static function handle_csv_export(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }

        // Clean any output that might have been sent
        if (ob_get_level()) {
            ob_end_clean();
        }

        self::export_current_filter_as_csv();
        exit;
    }

    /**
     * Export filtered submissions as CSV (Excel-compatible)
     */
    private static function export_current_filter_as_csv(): void
    {
        global $wpdb;
        $submissions_table = self::get_submissions_table();
        $forms_table = self::get_forms_table();

        $where = [];
        $args = [];
        $form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
        $search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
        if ($form_id > 0) {
            $where[] = 's.form_id = %d';
            $args[] = $form_id;
        }
        if ($search !== '') {
            $where[] = 's.submission_data LIKE %s';
            $args[] = '%' . $wpdb->esc_like($search) . '%';
        }
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT s.*, f.name AS form_name FROM {$submissions_table} s LEFT JOIN {$forms_table} f ON s.form_id = f.id {$where_clause} ORDER BY s.created_at ASC";
        $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

        // Prepare CSV headers - check if headers not already sent
        if (!headers_sent()) {
            nocache_headers();
            // Use application/octet-stream to force download and avoid encoding issues
            header('Content-Type: application/octet-stream; charset=UTF-8');
            header('Content-Disposition: attachment; filename="submissions_' . date('Ymd_His') . '.csv"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
        }

        // Create output buffer for CSV
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for proper UTF-8 encoding in Excel
        fwrite($output, "\xEF\xBB\xBF");

        // Get selected fields for export
        $selected_fields = self::get_export_fields();
        $field_registry = PPDB_Form_Plugin::get_field_registry();

        // If no fields configured, require setup
        if (empty($selected_fields)) {
            wp_die(__('Field export belum dikonfigurasi. Silakan pilih field yang ingin diexport terlebih dahulu.', 'ppdb-form'));
        }

        // Use only selected fields
        $sorted_fields = $selected_fields;

        // Create header row with proper labels
        $headers = ['ID', 'Form', 'Tanggal Daftar', 'Status'];
        foreach ($sorted_fields as $field) {
            $label = $field_registry[$field]['label'] ?? ucfirst(str_replace('_', ' ', $field));
            $headers[] = $label;
        }

        // Get export settings
        $export_settings = get_option('ppdb_export_settings', ['delimiter' => ';']);
        $delimiter = $export_settings['delimiter'] ?? ';';
        
        // Write headers with configured delimiter
        fputcsv($output, $headers, $delimiter, '"');

        // Output data rows
        foreach ($rows as $row) {
            $data = json_decode((string) $row->submission_data, true) ?: [];

            // Base row data
            $row_data = [
                (int) $row->id,
                (string) $row->form_name ?: 'Default Form',
                date('d/m/Y H:i', strtotime($row->created_at)),
                'Terdaftar'
            ];

            // Add field values in correct order
            foreach ($sorted_fields as $field) {
                $value = $data[$field] ?? '';

                // Format value properly
                if (is_array($value)) {
                    $display = implode(', ', $value);
                } elseif (is_string($value) && strpos($field, 'tanggal') !== false && !empty($value)) {
                    // Format dates nicely
                    $display = date('d/m/Y', strtotime($value));
                } elseif (strpos($field, 'dok_') === 0 || strpos($field, 'file_') === 0) {
                    // Handle file uploads
                    $display = !empty($value) ? 'File Uploaded' : 'Tidak ada file';
                } else {
                    $display = (string) $value;
                }

                // Clean and sanitize data for CSV
                $display = trim($display);
                // Prevent CSV injection
                if (preg_match('/^[=+\-@]/', $display) === 1) {
                    $display = "'" . $display;
                }
                // Remove line breaks that could break CSV structure
                $display = str_replace(["\r", "\n", "\r\n"], ' ', $display);

                $row_data[] = $display;
            }

            // Write row with configured delimiter
            fputcsv($output, $row_data, $delimiter, '"');
        }

        // Output the CSV content
        rewind($output);
        echo stream_get_contents($output);
        fclose($output);
        exit;
    }

    private static function render_submission_detail(int $id): void
    {
        global $wpdb;
        $submission = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::get_submissions_table() . ' WHERE id = %d', $id));

        if (!$submission) {
            echo '<div class="error"><p>' . esc_html__('Data tidak ditemukan.', 'ppdb-form') . '</p></div>';
            return;
        }

        $data = json_decode((string) $submission->submission_data, true) ?: [];
        $registry = PPDB_Form_Plugin::get_field_registry();

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Detail Pendaftar', 'ppdb-form') . '</h1>';

        echo '<div class="card">';
        echo '<h2>' . esc_html($data['nama_lengkap'] ?? __('Tidak ada nama', 'ppdb-form')) . '</h2>';
        echo '<p><strong>' . esc_html__('Tanggal Daftar:', 'ppdb-form') . '</strong> ' . esc_html(mysql2date('Y-m-d H:i', (string) $submission->created_at)) . '</p>';

        echo '<table class="form-table">';
        foreach ($data as $key => $value) {
            $label = $registry[$key]['label'] ?? $key;
            $is_file = isset($registry[$key]['type']) && $registry[$key]['type'] === 'file';

            echo '<tr>';
            echo '<th scope="row">' . esc_html($label) . '</th>';
            echo '<td>';

            if ($is_file) {
                $values = is_array($value) ? $value : ((string) $value !== '' ? [(string) $value] : []);
                if (empty($values)) {
                    echo '<em>-</em>';
                } else {
                    $i = 0;
                    foreach ($values as $val) {
                        $i++;
                        $url = esc_url((string) $val);
                        if ($url === '') {
                            continue;
                        }
                        $btn_text = count($values) > 1 ? sprintf(__('Lihat Dokumen %d', 'ppdb-form'), $i) : __('Lihat Dokumen', 'ppdb-form');
                        echo '<p style="margin:0 0 6px 0;">'
                            . '<a class="button button-small" href="' . $url . '" target="_blank" rel="noopener noreferrer">'
                            . '<span class="dashicons dashicons-media-document" style="vertical-align:middle;margin-right:4px;"></span>'
                            . esc_html($btn_text)
                            . '</a> '
                            . '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html($url) . '</a>'
                            . '</p>';
                    }
                }
            } else {
                $display_value = is_array($value) ? implode(', ', $value) : (string) $value;
                echo esc_html($display_value);
            }

            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';

        echo '<p><a href="' . esc_url(admin_url('admin.php?page=ppdb-form-registrants')) . '" class="button">' . esc_html__('Kembali', 'ppdb-form') . '</a></p>';
        echo '</div>';
    }

    /**
     * Sanitize scalar for CSV to prevent formula injection in spreadsheet apps.
     */
    private static function sanitize_csv_scalar(string $value): string
    {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && preg_match('/^[=+\-@]/', $trimmed) === 1) {
            return "'" . $value;
        }
        return $value;
    }

    /**
     * Bulk send certificates via email
     */
    private static function bulk_send_certificates(array $submission_ids): array
    {
        if (!class_exists('PPDB_Form_Email_Certificate')) {
            return ['success' => false, 'message' => 'Email certificate system not available'];
        }

        $sent_count = 0;
        $failed_count = 0;
        $failed_reasons = [];

        global $wpdb;
        $submissions_table = self::get_submissions_table();

        foreach ($submission_ids as $submission_id) {
            // Get submission data
            $submission = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$submissions_table} WHERE id = %d",
                $submission_id
            ));

            if (!$submission) {
                $failed_count++;
                $failed_reasons[] = "Submission #{$submission_id} not found";
                continue;
            }

            $data = json_decode($submission->submission_data, true) ?: [];
            $email = $data['email'] ?? '';

            if (empty($email) || !is_email($email)) {
                $failed_count++;
                $failed_reasons[] = "Submission #{$submission_id}: Invalid email";
                continue;
            }

            $sent = PPDB_Form_Email_Certificate::send_certificate_to_email($submission_id, $email, $data);

            if ($sent) {
                $sent_count++;
            } else {
                $failed_count++;
                $failed_reasons[] = "Submission #{$submission_id}: Email send failed";
            }

            // Small delay to prevent overwhelming mail server
            usleep(200000);  // 0.2 second
        }

        $message = sprintf(
            __('Bulk email selesai: %d berhasil, %d gagal.', 'ppdb-form'),
            $sent_count,
            $failed_count
        );

        if (!empty($failed_reasons) && $failed_count < 5) {
            $message .= ' Gagal: ' . implode(', ', array_slice($failed_reasons, 0, 3));
        }

        return [
            'success' => $sent_count > 0,
            'message' => $message,
            'sent_count' => $sent_count,
            'failed_count' => $failed_count
        ];
    }

    /**
     * Handle bulk download with proper output buffer cleaning
     */
    private static function handle_bulk_download(array $submission_ids): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }

        // Clean any output that might have been sent
        if (ob_get_level()) {
            ob_end_clean();
        }

        self::bulk_download_certificates($submission_ids);
        exit;
    }

    /**
     * Bulk download certificates as ZIP
     */
    private static function bulk_download_certificates(array $submission_ids): void
    {
        if (!class_exists('ZipArchive')) {
            wp_die(__('ZipArchive extension not available', 'ppdb-form'));
        }

        $temp_dir = sys_get_temp_dir() . '/ppdb_certificates_' . uniqid();
        if (!wp_mkdir_p($temp_dir)) {
            wp_die(__('Cannot create temporary directory', 'ppdb-form'));
        }

        $zip = new ZipArchive();
        $zip_filename = $temp_dir . '/bukti_pendaftaran_' . date('Y-m-d_H-i-s') . '.zip';

        if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
            wp_die(__('Cannot create ZIP file', 'ppdb-form'));
        }

        global $wpdb;
        $submissions_table = self::get_submissions_table();
        $added_count = 0;

        foreach ($submission_ids as $submission_id) {
            // Get certificate HTML
            if (class_exists('PPDB_Form_Certificate')) {
                $certificate_html = PPDB_Form_Certificate::generate_certificate($submission_id);

                if (!empty($certificate_html)) {
                    // Get submission data for filename
                    $submission = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$submissions_table} WHERE id = %d",
                        $submission_id
                    ));

                    $data = $submission ? json_decode($submission->submission_data, true) : [];
                    $nama = sanitize_file_name($data['nama_lengkap'] ?? 'Pendaftar');
                    $reg_number = self::get_registration_number($submission_id);

                    $filename = "bukti_pendaftaran_{$reg_number}_{$nama}.html";

                    // Add to ZIP
                    $zip->addFromString($filename, $certificate_html);
                    $added_count++;
                }
            }
        }

        $zip->close();

        if ($added_count === 0) {
            unlink($zip_filename);
            rmdir($temp_dir);
            wp_die(__('No certificates generated', 'ppdb-form'));
        }

        // Send ZIP file - check if headers not already sent
        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="bukti_pendaftaran_bulk_' . date('Y-m-d') . '.zip"');
            header('Content-Length: ' . filesize($zip_filename));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
        }

        readfile($zip_filename);

        // Clean up
        unlink($zip_filename);
        rmdir($temp_dir);
        exit;
    }

    /**
     * Handle export selected with proper output buffer cleaning
     */
    private static function handle_export_selected(array $submission_ids): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }

        // Clean any output that might have been sent
        if (ob_get_level()) {
            ob_end_clean();
        }

        self::export_selected_submissions($submission_ids);
        exit;
    }

    /**
     * Export selected submissions to CSV
     */
    private static function export_selected_submissions(array $submission_ids): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }

        global $wpdb;
        $submissions_table = self::get_submissions_table();
        $forms_table = self::get_forms_table();

        if (empty($submission_ids)) {
            wp_die(__('No submissions selected', 'ppdb-form'));
        }

        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
        $sql = "SELECT s.*, f.name AS form_name FROM {$submissions_table} s LEFT JOIN {$forms_table} f ON s.form_id = f.id WHERE s.id IN ({$placeholders}) ORDER BY s.created_at ASC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $submission_ids));

        if (empty($results)) {
            wp_die(__('No data to export', 'ppdb-form'));
        }

        // Generate CSV
        $filename = 'ppdb_selected_submissions_' . date('Y-m-d_H-i-s') . '.csv';

        // Send headers only if not already sent
        if (!headers_sent()) {
            header('Content-Type: application/octet-stream; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: 0');
        }

        // Create output buffer
        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8 Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        // Get selected fields for export
        $selected_fields = self::get_export_fields();
        $field_registry = PPDB_Form_Plugin::get_field_registry();

        // If no fields configured, require setup
        if (empty($selected_fields)) {
            wp_die(__('Field export belum dikonfigurasi. Silakan pilih field yang ingin diexport terlebih dahulu.', 'ppdb-form'));
        }

        // Use only selected fields that exist in the data
        $sorted_fields = $selected_fields;

        // Create headers
        $headers = ['Nomor', 'Form', 'No. Registrasi', 'Tanggal Daftar'];
        foreach ($sorted_fields as $field) {
            $label = $field_registry[$field]['label'] ?? ucfirst(str_replace('_', ' ', $field));
            $headers[] = $label;
        }

        // Get export settings
        $export_settings = get_option('ppdb_export_settings', ['delimiter' => ';']);
        $delimiter = $export_settings['delimiter'] ?? ';';
        
        // Write headers with configured delimiter
        fputcsv($output, $headers, $delimiter, '"');

        // Output data rows
        $nomor = 1;
        foreach ($results as $row) {
            $data = json_decode((string) $row->submission_data, true) ?: [];
            $reg_number = self::get_registration_number($row->id);

            // Base row data
            $csv_row = [
                $nomor,
                $row->form_name ?: 'Default Form',
                $reg_number,
                date('d/m/Y H:i', strtotime($row->created_at))
            ];

            // Add field values in correct order
            foreach ($sorted_fields as $field) {
                $value = $data[$field] ?? '';

                // Format value properly
                if (is_array($value)) {
                    $display = implode(', ', $value);
                } elseif (is_string($value) && strpos($field, 'tanggal') !== false && !empty($value)) {
                    $display = date('d/m/Y', strtotime($value));
                } elseif (strpos($field, 'dok_') === 0 || strpos($field, 'file_') === 0) {
                    $display = !empty($value) ? 'File Uploaded' : 'Tidak ada file';
                } else {
                    $display = (string) $value;
                }

                // Clean data
                $display = trim($display);
                if (preg_match('/^[=+\-@]/', $display) === 1) {
                    $display = "'" . $display;
                }
                $display = str_replace(["\r", "\n", "\r\n"], ' ', $display);

                $csv_row[] = $display;
            }

            // Write row with configured delimiter
            fputcsv($output, $csv_row, $delimiter, '"');
            $nomor++;
        }

        // Output the CSV
        rewind($output);
        echo stream_get_contents($output);
        fclose($output);
        exit;
    }
    private static function get_registration_number(int $submission_id): string
    {
        $prefix = get_option('ppdb_reg_number_prefix', 'REG');
        $year = date('Y');
        $padded_id = str_pad((string) $submission_id, 6, '0', STR_PAD_LEFT);

        return $prefix . $year . $padded_id;
    }

    /**
     * Sanitize CSV value to prevent injection and formatting issues
     */
    private static function sanitize_csv_value(string $value): string
    {
        // Trim whitespace
        $value = trim($value);

        // Prevent CSV injection attacks
        if (preg_match('/^[=+\-@]/', $value) === 1) {
            $value = "'" . $value;
        }

        // Clean up common formatting issues
        $value = str_replace(["\r\n", "\n", "\r"], ' ', $value);  // Replace line breaks
        $value = preg_replace('/\s+/', ' ', $value);  // Multiple spaces to single

        return $value;
    }

}
