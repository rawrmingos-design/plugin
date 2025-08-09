<?php
if (!defined('ABSPATH')) {
  exit;
}
require_once plugin_dir_path(__FILE__) . 'wp-chatbot-dashboard.php';


// Tambahkan menu di admin WordPress
function ai_chatbot_menu()
{
  add_menu_page(
    'AI Chatbot',
    'AI Chatbot',
    'manage_options',
    'ai-chatbot',
    'ai_chatbot_settings_page',
    'dashicons-format-chat',
    25
  );

  add_submenu_page(
    'ai-chatbot',
    'Chatbot Logs',
    'Chatbot Logs',
    'manage_options',
    'ai-chatbot-logs',
    'ai_chatbot_logs_page'
  );
  add_submenu_page(
    'ai-chatbot',
    'Chatbot Dashboard',
    'Dashboard',
    'manage_options',
    'ai-chatbot-dashboard',
    'ai_chatbot_dashboard_page'
  );
}

// Callback function untuk submenu logs
function ai_chatbot_logs_page()
{
  echo '<div class="wrap"><h1>Chatbot Logs</h1><p>Daftar percakapan chatbot.</p></div>';
}

add_action('admin_menu', 'ai_chatbot_menu');


// Halaman pengaturan plugin
function ai_chatbot_settings_page()
{
  if (isset($_POST['submit'])) {
    if (isset($_POST['ai_chatbot_api_key'])) {
      update_option('ai_chatbot_api_key', sanitize_text_field($_POST['ai_chatbot_api_key']));
      add_settings_error('ai_chatbot_messages', 'api_key_updated', 'Your API Key was updated!', 'updated');
      exit;
    }
  }

  settings_errors('ai_chatbot_messages'); // Menampilkan notifikasi

?>
  <div class="wrap">
    <h1>AI Chatbot Settings</h1>
    <form method="post">
      <table class="form-table">
        <tr valign="top">
          <th scope="row">OpenAI API Key</th>
          <td><input type="text" name="ai_chatbot_api_key" value="<?php echo esc_attr(get_option('ai_chatbot_api_key')); ?>" /></td>
        </tr>
      </table>
      <?php submit_button('Save API Key'); ?>
    </form>
  </div>
<?php
}


// Daftarkan setting
function ai_chatbot_register_settings()
{
  register_setting('ai_chatbot_options', 'ai_chatbot_api_key');
}
add_action('admin_init', 'ai_chatbot_register_settings');
