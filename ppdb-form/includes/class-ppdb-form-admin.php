<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class PPDB_Form_Admin
{
    public static function register_menu(): void
    {
        add_menu_page(
            __('Simpel Pendaftaran', 'ppdb-form'),
            __('Simpel Pendaftaran', 'ppdb-form'),
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
        if ($action === 'delete' && isset($_GET['id'])) {
            self::handle_delete_form((int) $_GET['id']);
        }
        if ($action === 'fields' && isset($_GET['id'])) {
            self::render_form_fields_page((int) $_GET['id']);
            return;
        }
        self::handle_save_form();
        if (isset($_GET['action']) && $_GET['action'] === 'steps' && isset($_GET['id'])) {
            $form_id = (int) $_GET['id'];
            self::render_steps_builder($form_id);
            return;
        }
        self::render_forms_list_and_editor();
    }

    private static function handle_delete_form(int $id): void
    {
        check_admin_referer('ppdb_delete_form_' . $id);
        global $wpdb;
        $wpdb->delete(self::get_forms_table(), ['id' => $id], ['%d']);
        $wpdb->delete(self::get_submissions_table(), ['form_id' => $id], ['%d']);
        echo '<div class="updated"><p>' . esc_html__('Formulir dan data terkait telah dihapus.', 'ppdb-form') . '</p></div>';
    }

    private static function handle_save_form(): void
    {
        if (!isset($_POST['ppdb_form_nonce']) || !wp_verify_nonce((string) $_POST['ppdb_form_nonce'], 'ppdb_save_form')) {
            return;
        }
        global $wpdb;
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = sanitize_text_field((string) ($_POST['name'] ?? ''));
        $description = sanitize_textarea_field((string) ($_POST['description'] ?? ''));
        $success_message = sanitize_textarea_field((string) ($_POST['success_message'] ?? ''));
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
        if ($id > 0) {
            $wpdb->update(self::get_forms_table(), $data, ['id' => $id], ['%s', '%s', '%s', '%d', '%s', '%s'], ['%d']);
            echo '<div class="updated"><p>' . esc_html__('Formulir diperbarui.', 'ppdb-form') . '</p></div>';
        } else {
            $data['created_at'] = current_time('mysql');
            $data['fields_json'] = wp_json_encode(self::generate_default_fields_config());
            $wpdb->insert(self::get_forms_table(), $data, ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);
            echo '<div class="updated"><p>' . esc_html__('Formulir dibuat.', 'ppdb-form') . '</p></div>';
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
        echo '<input type="text" class="ppdb-search" placeholder="' . esc_attr__('Cari Form', 'ppdb-form') . '" onkeyup="ppdbFilterTable(this, \' .ppdb-table\')">';
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
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $steps_config = wp_json_encode(['enabled' => true, 'steps' => $decoded]);
                $wpdb->update(self::get_forms_table(), ['steps_config' => $steps_config], ['id' => $form_id], ['%s'], ['%d']);
                echo '<div class="updated"><p>' . esc_html__('Steps berhasil disimpan.', 'ppdb-form') . '</p></div>';
                $form->steps_config = $steps_config;
            } else {
                echo '<div class="error"><p>' . esc_html__('Format steps tidak valid.', 'ppdb-form') . '</p></div>';
            }
        }

        $registry = PPDB_Form_Plugin::get_field_registry();
        $steps_data = $form->steps_config ? json_decode((string) $form->steps_config, true) : null;
        $steps = $steps_data['steps'] ?? [['title' => 'Langkah 1', 'description' => '', 'fields' => []]];

        echo '<div class="wrap"><h1>' . esc_html__('Kelola Steps', 'ppdb-form') . ' - ' . esc_html($form->name) . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('ppdb_save_steps_' . $form_id, 'ppdb_steps_nonce');
        echo '<div id="ppdb-steps-builder" style="display:flex; gap:20px; align-items:flex-start;">';
        echo '<div class="ppdb-card" style="flex:1; min-width:260px;">';
        echo '<h2>' . esc_html__('Field Tersedia', 'ppdb-form') . '</h2>';
        echo '<ul id="ppdb-available" class="ppdb-sortable" style="min-height:200px; border:1px solid #e5e7eb; padding:10px; background:#fff;">';
        foreach ($registry as $key => $meta) {
            echo '<li class="ppdb-item" data-key="' . esc_attr($key) . '" style="padding:6px 10px; border:1px solid #e5e7eb; margin:6px 0; background:#fafafa; cursor:move;">' . esc_html($meta['label']) . ' <code>' . esc_html($key) . '</code></li>';
        }
        echo '</ul></div>';
        echo '<div style="flex:2;">';
        echo '<div id="ppdb-steps-container">';
        foreach ($steps as $st) {
            echo '<div class="ppdb-step-panel" style="border:1px solid #e5e7eb; padding:12px; margin-bottom:16px; background:#fff;">';
            echo '<label>Judul <input type="text" class="ppdb-step-title" value="' . esc_attr($st['title'] ?? '') . '" /></label> ';
            echo '<label style="margin-left:10px;">Deskripsi <input type="text" class="ppdb-step-desc" value="' . esc_attr($st['description'] ?? '') . '" /></label>';
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

        echo '<script>';
        echo 'jQuery(function($){';
        echo '  $(".ppdb-sortable").sortable({connectWith: ".ppdb-sortable", placeholder: "ppdb-placeholder"}).disableSelection();';
        echo '  $("#ppdb-add-step").on("click", function(){';
        echo '    var panel = $("<div class=\"ppdb-step-panel\" style=\"border:1px solid #e5e7eb; padding:12px; margin-bottom:16px; background:#fff;\"></div>");';
        echo '    panel.append("<label>Judul <input type=\"text\" class=\"ppdb-step-title\" value=\"Langkah Baru\" /></label> ");';
        echo '    panel.append("<label style=\"margin-left:10px;\">Deskripsi <input type=\"text\" class=\"ppdb-step-desc\" value=\"\" /></label>");';
        echo '    panel.append("<ul class=\"ppdb-sortable ppdb-step-fields\" style=\"min-height:160px; border:1px dashed #cbd5e1; padding:10px; margin-top:8px; background:#f8fafc;\"></ul>");';
        echo '    panel.append("<button type=\"button\" class=\"button link-delete-step\" style=\"margin-top:6px;\">Hapus Step</button>");';
        echo '    $("#ppdb-steps-container").append(panel);';
        echo '    panel.find(".ppdb-sortable").sortable({connectWith: ".ppdb-sortable", placeholder:"ppdb-placeholder"}).disableSelection();';
        echo '  });';
        echo '  $(document).on("click", ".link-delete-step", function(){ $(this).closest(".ppdb-step-panel").remove(); });';
        echo '  $("#ppdb-save-steps").on("click", function(){';
        echo '     var steps = [];';
        echo '     $(".ppdb-step-panel").each(function(){';
        echo '        var title = $(this).find(".ppdb-step-title").val();';
        echo '        var desc = $(this).find(".ppdb-step-desc").val();';
        echo '        var fields = [];';
        echo '        $(this).find(".ppdb-step-fields .ppdb-item").each(function(){ fields.push($(this).data("key")); });';
        echo '        steps.push({title:title, description:desc, fields:fields});';
        echo '     });';
        echo '     $("#ppdb_steps_json").val(JSON.stringify(steps));';
        echo '  });';
        echo '});';
        echo '</script>';
        echo '<style>.ppdb-placeholder{border:2px dashed #93c5fd; height:34px; margin:6px 0; background:#eff6ff}</style>';
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
        $where_count = $form_id > 0 ? $wpdb->prepare('WHERE form_id = %d', $form_id) : '';
        $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $tbl . " {$where_count}");
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
        $export_url = wp_nonce_url(add_query_arg(['page' => 'ppdb-form-registrants', 'form_id' => $form_id, 'export' => 'csv'], admin_url('admin.php')), 'ppdb_export_registrants');
        echo '<a href="' . esc_url($export_url) . '" class="button button-primary" style="margin-left:auto">' . esc_html__('Export to Excel', 'ppdb-form') . '</a>';
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
        echo '</div>';
    }

    private static function export_registrants_csv(int $form_id): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Tidak diizinkan', 'ppdb-form'));
        }
        global $wpdb;
        $forms_tbl = self::get_forms_table();
        $subs_tbl = self::get_submissions_table();
        $form = null;
        $fields_config = [];
        if ($form_id > 0) {
            $form = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $forms_tbl . ' WHERE id = %d', $form_id));
            if ($form && $form->fields_json) {
                $fields_config = json_decode((string) $form->fields_json, true) ?: [];
            }
        }
        $where = $form_id > 0 ? $wpdb->prepare('WHERE form_id = %d', $form_id) : '';
        $rows = $wpdb->get_results('SELECT * FROM ' . $subs_tbl . " {$where} ORDER BY id DESC");

        $registry = PPDB_Form_Plugin::get_field_registry();
        $columns = ['ID', 'Form ID', 'Tanggal'];
        $field_keys = [];
        if (!empty($fields_config)) {
            foreach ($registry as $key => $meta) {
                if (!empty($fields_config[$key]['enabled'])) {
                    $field_keys[] = $key;
                }
            }
        } else {
            foreach ($rows as $r) {
                $data = json_decode((string) $r->submission_data, true) ?: [];
                foreach (array_keys($data) as $k) {
                    if (!in_array($k, $field_keys, true)) {
                        $field_keys[] = $k;
                    }
                }
            }
        }
        foreach ($field_keys as $k) {
            $columns[] = $registry[$k]['label'] ?? $k;
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        $filename = 'ppdb-registrants-' . ($form ? sanitize_title((string) $form->name) : 'all') . '-' . wp_date('Ymd-His') . '.csv';
        header('Content-Disposition: attachment; filename=' . $filename);
        echo "\u{FEFF}";
        $out = fopen('php://output', 'w');
        fputcsv($out, $columns);
        foreach ($rows as $r) {
            $data = json_decode((string) $r->submission_data, true) ?: [];
            $line = [(int) $r->id, (int) $r->form_id, (string) $r->created_at];
            foreach ($field_keys as $k) {
                $v = $data[$k] ?? '';
                if (is_array($v)) {
                    $v = implode(', ', $v);
                }
                $line[] = self::sanitize_csv_scalar((string) $v);
            }
            fputcsv($out, $line);
        }
        fclose($out);
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
        echo '<thead><tr><th>ID</th><th>' . esc_html__('Nama', 'ppdb-form') . '</th><th>' . esc_html__('Status', 'ppdb-form') . '</th><th>' . esc_html__('Aksi', 'ppdb-form') . '</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $edit_url = admin_url('admin.php?page=ppdb-form-departments&edit=' . (int) $r->id);
            $delete_url = wp_nonce_url(admin_url('admin.php?page=ppdb-form-departments&action=delete&id=' . (int) $r->id), 'ppdb_delete_dept_' . (int) $r->id);
            echo '<tr><td>' . (int) $r->id . '</td><td>' . esc_html($r->name) . '</td><td>' . ((int) $r->is_active === 1 ? '<span class="ppdb-badge success">Aktif</span>' : '<span class="ppdb-badge muted">Nonaktif</span>') . '</td><td class="ppdb-actions">' . '<a class="ppdb-icon btn-green dashicons dashicons-edit" href="' . esc_url($edit_url) . '"></a>' . '<a class="ppdb-icon btn-red dashicons dashicons-trash" href="' . esc_url($delete_url) . '" onclick="return confirm(\'' . esc_js(__('Hapus jurusan?', 'ppdb-form')) . '\');"></a>' . '</td></tr>';
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
        if (isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['submission'])) {
            check_admin_referer('bulk-submissions');
            global $wpdb;
            $ids = array_map('intval', (array) $_POST['submission']);
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare('DELETE FROM ' . self::get_submissions_table() . " WHERE id IN ({$placeholders})", $ids));
            echo '<div class="updated"><p>' . esc_html__('Data terpilih telah dihapus.', 'ppdb-form') . '</p></div>';
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
                // Export CSV with current filters
                self::export_current_filter_as_csv();
                return;  // headers sent
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
        $export_url = add_query_arg(['page' => 'ppdb-form-registrants', 'action' => 'export'] + wp_unslash($_GET), admin_url('admin.php'));
        echo ' <a href="' . esc_url($export_url) . '" class="page-title-action">' . esc_html__('Export CSV (Filter Saat Ini)', 'ppdb-form') . '</a>';
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

        echo '</div>';
    }

    /**
     * Export filtered submissions as CSV (Excel-compatible)
     */
    private static function export_current_filter_as_csv(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('Anda tidak memiliki izin.', 'ppdb-form'));
        }
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

        $sql = "SELECT s.*, f.name AS form_name FROM {$submissions_table} s LEFT JOIN {$forms_table} f ON s.form_id = f.id {$where_clause} ORDER BY s.created_at DESC";
        $rows = !empty($args) ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

        // Prepare CSV headers
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=submissions_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        // Header row
        fputcsv($out, ['ID', 'Form', 'Created At', 'Field', 'Value']);

        foreach ($rows as $row) {
            $data = json_decode((string) $row->submission_data, true) ?: [];
            foreach ($data as $field => $value) {
                $display = is_array($value) ? implode('; ', $value) : (string) $value;
                // Prevent CSV injection
                if (preg_match('/^[=+\-@]/', ltrim($display)) === 1) {
                    $display = "'" . $display;
                }
                fputcsv($out, [(int) $row->id, (string) $row->form_name, (string) $row->created_at, $field, $display]);
            }
        }
        fclose($out);
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
}
