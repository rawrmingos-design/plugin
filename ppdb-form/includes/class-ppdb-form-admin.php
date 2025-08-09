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
        add_submenu_page('ppdb-form', __('Data Pendaftar', 'ppdb-form'), __('Data Pendaftar', 'ppdb-form'), 'manage_options', 'ppdb-form-registrants', [self::class, 'render_registrants_page']);
        add_submenu_page('ppdb-form', __('Pengaturan Jurusan', 'ppdb-form'), __('Pengaturan Jurusan', 'ppdb-form'), 'manage_options', 'ppdb-form-departments', [self::class, 'render_departments_page']);
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
        $data = ['name' => $name, 'description' => $description, 'success_message' => $success_message, 'is_active' => $is_active, 'updated_at' => current_time('mysql')];
        if ($id > 0) {
            $wpdb->update(self::get_forms_table(), $data, ['id' => $id], ['%s', '%s', '%s', '%d', '%s'], ['%d']);
            echo '<div class="updated"><p>' . esc_html__('Formulir diperbarui.', 'ppdb-form') . '</p></div>';
        } else {
            $data['created_at'] = current_time('mysql');
            $data['fields_json'] = wp_json_encode(self::generate_default_fields_config());
            $wpdb->insert(self::get_forms_table(), $data, ['%s', '%s', '%s', '%d', '%s', '%s', '%s']);
            echo '<div class="updated"><p>' . esc_html__('Formulir dibuat.', 'ppdb-form') . '</p></div>';
        }
    }

    private static function generate_default_fields_config(): array
    {
        $registry = PPDB_Form_Plugin::get_field_registry();
        $enabled_keys = [
            'nisn', 'nama_lengkap', 'jenis_kelamin', 'agama', 'alamat', 'nomor_telepon', 'email', 'jurusan'
        ];
        $required_keys = ['nisn', 'nama_lengkap', 'jenis_kelamin', 'nomor_telepon', 'jurusan'];
        $config = [];
        foreach ($registry as $key => $_meta) {
            $config[$key] = [
                'enabled' => in_array($key, $enabled_keys, true) ? 1 : 0,
                'required' => in_array($key, $required_keys, true) ? 1 : 0,
            ];
        }
        return $config;
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
        echo '<label class="ppdb-checkbox"><input type="checkbox" name="is_active" ' . checked((int) ($form->is_active ?? 1), 1, false) . '> ' . esc_html__('Active', 'ppdb-form') . '</label>';
        echo '</div>';
        echo '<p><button class="button button-primary">' . esc_html__('Simpan Form', 'ppdb-form') . '</button></p>';
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
        $where = $form_id > 0 ? $wpdb->prepare('WHERE form_id = %d', $form_id) : '';
        $rows = $wpdb->get_results('SELECT * FROM ' . self::get_submissions_table() . " {$where} ORDER BY id DESC");

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
        echo '</select> <button class="button">' . esc_html__('Filter', 'ppdb-form') . '</button>';
        echo '</form>';
        echo '<input type="text" class="ppdb-search" placeholder="' . esc_attr__('Cari di semua kolom', 'ppdb-form') . '" onkeyup="ppdbFilterTable(this, \'.ppdb-table\')">';
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
                $line[] = $v;
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
}
