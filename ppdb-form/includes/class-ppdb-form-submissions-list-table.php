<?php declare(strict_types=1);

if (!defined('ABSPATH')) {
  exit;
}

if (!class_exists('WP_List_Table')) {
  require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class PPDB_Form_Submissions_List_Table extends WP_List_Table
{
  private $form_id = 0;

  public function __construct()
  {
    parent::__construct([
      'singular' => 'submission',
      'plural' => 'submissions',
      'ajax' => false,
    ]);
    // Only filter by form_id if explicitly set and > 0
    $this->form_id = isset($_GET['form_id']) && (int) $_GET['form_id'] > 0 ? (int) $_GET['form_id'] : 0;
  }

  public function get_columns(): array
  {
    return [
      'cb' => '<input type="checkbox" />',
      'nomor' => __('Nomor', 'ppdb-form'),
      'form_name' => __('Form', 'ppdb-form'),
      'nama_lengkap' => __('Nama', 'ppdb-form'),
      'nomor_telepon' => __('Telepon', 'ppdb-form'),
      'email' => __('Email', 'ppdb-form'),
      'jurusan' => __('Jurusan', 'ppdb-form'),
      'created_at' => __('Tanggal', 'ppdb-form'),
      'actions' => __('Aksi', 'ppdb-form'),
    ];
  }

  public function get_sortable_columns(): array
  {
    return [
      'nomor' => ['id', true],
      'created_at' => ['created_at', false],
      'nama_lengkap' => ['nama_lengkap', false],
    ];
  }

  public function get_bulk_actions(): array
  {
    return [
      'delete' => __('Hapus', 'ppdb-form'),
      'export_selected' => __('Export Terpilih', 'ppdb-form'),
      'send_certificates' => __('📧 Kirim Bukti via Email', 'ppdb-form'),
      'download_certificates' => __('📄 Download Bukti (ZIP)', 'ppdb-form'),
    ];
  }

  protected function get_default_primary_column_name(): string
  {
    return 'nama_lengkap';
  }

  public function prepare_items(): void
  {
    global $wpdb;

    $per_page = $this->get_items_per_page('ppdb_submissions_per_page', 20);
    $current_page = $this->get_pagenum();
    $offset = ($current_page - 1) * $per_page;

    $search = isset($_REQUEST['s']) ? sanitize_text_field((string) $_REQUEST['s']) : '';
    $orderby = isset($_REQUEST['orderby']) ? sanitize_text_field((string) $_REQUEST['orderby']) : 'created_at';
    $order = isset($_REQUEST['order']) && in_array(strtoupper((string) $_REQUEST['order']), ['ASC', 'DESC'], true)
      ? strtoupper((string) $_REQUEST['order'])
      : 'ASC';

    $submissions_table = $wpdb->prefix . 'ppdb_submissions';
    $forms_table = $wpdb->prefix . 'ppdb_forms';

    $where_sql = [];
    $where_args = [];

    if ($this->form_id > 0) {
      $where_sql[] = 's.form_id = %d';
      $where_args[] = $this->form_id;
    }

    if ($search !== '') {
      $where_sql[] = '(s.submission_data LIKE %s OR f.name LIKE %s)';
      $where_args[] = '%' . $wpdb->esc_like($search) . '%';
      $where_args[] = '%' . $wpdb->esc_like($search) . '%';
    }

    $where_clause = !empty($where_sql) ? 'WHERE ' . implode(' AND ', $where_sql) : '';

    // Setup column headers for WP_List_Table (columns, hidden, sortable, primary)
    $columns = $this->get_columns();
    $hidden = [];
    $sortable = $this->get_sortable_columns();
    // WP core expects _column_headers to be an array with 3 or 4 entries
    $this->_column_headers = [$columns, $hidden, $sortable, $this->get_default_primary_column_name()];

    // Count total items
    $count_sql = "SELECT COUNT(*) FROM {$submissions_table} s LEFT JOIN {$forms_table} f ON s.form_id = f.id {$where_clause}";
    $total_items = !empty($where_args)
      ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $where_args))
      : (int) $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table}");

    // Get items with proper JOIN
    $sql = "SELECT s.*, f.name as form_name 
                FROM {$submissions_table} s 
                LEFT JOIN {$forms_table} f ON s.form_id = f.id 
                {$where_clause} 
                ORDER BY s.{$orderby} {$order} 
                LIMIT %d OFFSET %d";

    $query_args = array_merge($where_args, [$per_page, $offset]);

    // Debug: Try without prepare first to check if query works
    if (empty($where_args)) {
      $simple_sql = "SELECT s.*, f.name as form_name 
                     FROM {$submissions_table} s 
                     LEFT JOIN {$forms_table} f ON s.form_id = f.id 
                     ORDER BY s.id DESC 
                     LIMIT {$per_page} OFFSET {$offset}";
      $this->items = $wpdb->get_results($simple_sql);
    } else {
      $this->items = $wpdb->get_results($wpdb->prepare($sql, $query_args));
    }

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
      error_log('PPDB List Table Debug: ' . json_encode([
        'total_items' => $total_items,
        'items_count' => count($this->items),
        'form_id_filter' => $this->form_id,
        'search' => $search,
        'sql' => $sql,
        'args' => $query_args,
        'first_item' => $this->items[0] ?? null
      ]));
    }

    $this->set_pagination_args([
      'total_items' => $total_items,
      'per_page' => $per_page,
      'total_pages' => ceil($total_items / $per_page),
    ]);
  }

  public function column_cb($item): string
  {
    return sprintf('<input type="checkbox" name="submission[]" value="%d" />', (int) $item->id);
  }

  public function column_nomor($item): string
  {
    // Calculate sequential number based on creation order
    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';
    
    $where_clause = '';
    $args = [];
    
    if ($this->form_id > 0) {
      $where_clause = 'WHERE form_id = %d AND created_at <= %s';
      $args = [$this->form_id, $item->created_at];
    } else {
      $where_clause = 'WHERE created_at <= %s';
      $args = [$item->created_at];
    }
    
    $sequential_number = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$submissions_table} {$where_clause}",
      $args
    ));
    
    return (string) $sequential_number;
  }

  public function column_form_name($item): string
  {
    return esc_html($item->form_name ?? '-');
  }

  public function column_created_at($item): string
  {
    return esc_html(mysql2date('Y-m-d H:i', (string) $item->created_at));
  }

  public function column_nama_lengkap($item): string
  {
    $data = json_decode((string) $item->submission_data, true) ?: [];
    return esc_html($data['nama_lengkap'] ?? '-');
  }

  public function column_nomor_telepon($item): string
  {
    $data = json_decode((string) $item->submission_data, true) ?: [];
    return esc_html($data['nomor_telepon'] ?? '-');
  }

  public function column_email($item): string
  {
    $data = json_decode((string) $item->submission_data, true) ?: [];
    return esc_html($data['email'] ?? '-');
  }

  public function column_jurusan($item): string
  {
    $data = json_decode((string) $item->submission_data, true) ?: [];
    return esc_html($data['jurusan'] ?? '-');
  }

  public function column_actions($item): string
  {
    $detail_url = add_query_arg([
      'page' => 'ppdb-form-registrants',
      'action' => 'view',
      'id' => (int) $item->id,
      'form_id' => $this->form_id,
    ], admin_url('admin.php'));

    $delete_url = wp_nonce_url(add_query_arg([
      'page' => 'ppdb-form-registrants',
      'action' => 'delete',
      'id' => (int) $item->id,
      'form_id' => $this->form_id,
    ], admin_url('admin.php')), 'ppdb_delete_submission_' . (int) $item->id);

    return sprintf(
      '<a href="%s" class="button button-small">%s</a> '
        . '<a href="%s" class="button button-small" onclick="return confirm(\'%s\')">%s</a>',
      esc_url($detail_url),
      __('Detail', 'ppdb-form'),
      esc_url($delete_url),
      esc_js(__('Hapus data ini?', 'ppdb-form')),
      __('Hapus', 'ppdb-form')
    );
  }

  protected function get_views(): array
  {
    global $wpdb;
    $submissions_table = $wpdb->prefix . 'ppdb_submissions';

    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$submissions_table}");
    $today = current_time('Y-m-d');
    $today_count = (int) $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM {$submissions_table} WHERE DATE(created_at) = %s",
      $today
    ));

    $views = [
      'all' => sprintf(
        '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
        admin_url('admin.php?page=ppdb-form-registrants'),
        $this->form_id === 0 ? ' class="current"' : '',
        __('Semua', 'ppdb-form'),
        $total
      ),
      'today' => sprintf(
        '<a href="%s">%s <span class="count">(%d)</span></a>',
        add_query_arg(['date_filter' => 'today'], admin_url('admin.php?page=ppdb-form-registrants')),
        __('Hari Ini', 'ppdb-form'),
        $today_count
      ),
    ];

    return $views;
  }

  public function no_items(): void
  {
    global $wpdb;
    $raw_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppdb_submissions");

    echo '<div style="padding: 20px; text-align: center;">';
    echo '<p>' . esc_html__('Belum ada data pendaftar yang ditampilkan.', 'ppdb-form') . '</p>';

    if (defined('WP_DEBUG') && WP_DEBUG) {
      echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0;">';
      echo '<strong>Debug Info:</strong><br>';
      echo 'Raw submissions in DB: ' . $raw_count . '<br>';
      echo 'Form ID filter: ' . ($this->form_id ?: 'All') . '<br>';
      echo 'Items in $this->items: ' . count($this->items) . '<br>';

      if ($raw_count > 0) {
        $sample = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ppdb_submissions LIMIT 1");
        echo 'Sample data: <pre>' . print_r($sample, true) . '</pre>';
      }
      echo '</div>';
    }

    if ($this->form_id > 0) {
      echo '<p><a href="' . admin_url('admin.php?page=ppdb-form-registrants') . '">' . esc_html__('Lihat semua data', 'ppdb-form') . '</a></p>';
    }
    echo '</div>';
  }

  public function display(): void
  {
    // Add some debug info if WP_DEBUG is on
    if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('manage_options')) {
      echo '<div style="background: #f1f1f1; padding: 10px; margin-bottom: 10px; font-size: 12px;">';
      echo '<strong>Debug Info:</strong> ';
      echo 'Total items: ' . count($this->items) . ' | ';
      echo 'Form ID filter: ' . ($this->form_id ?: 'All') . ' | ';
      echo 'Current page: ' . $this->get_pagenum();
      echo '</div>';
    }

    parent::display();
  }

  public function column_default($item, $column_name): string
  {
    switch ($column_name) {
      case 'id':
        return $this->column_id($item);
      case 'form_name':
        return $this->column_form_name($item);
      case 'nama_lengkap':
        return $this->column_nama_lengkap($item);
      case 'nomor_telepon':
        return $this->column_nomor_telepon($item);
      case 'email':
        return $this->column_email($item);
      case 'jurusan':
        return $this->column_jurusan($item);
      case 'created_at':
        return $this->column_created_at($item);
      case 'actions':
        return $this->column_actions($item);
      default:
        return esc_html($item->$column_name ?? '-');
    }
  }

  // Use core's default display_rows and rendering pipeline
}
