<?php
if (!defined('ABSPATH')) {
  exit;
}

function ai_chatbot_logs_page()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'ai_chatbot_logs';
  $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");

  echo '<div class="wrap">';
  echo '<h1>Chatbot Logs</h1>';
  echo '<table class="wp-list-table widefat fixed striped">';
  echo '<thead><tr><th width="40%">User Message</th><th width="40%">Bot Response</th><th width="20%">Timestamp</th></tr></thead>';
  echo '<tbody>';

  foreach ($logs as $log) {
    echo '<tr>';
    echo '<td>' . esc_html($log->user_message) . '</td>';
    echo '<td>' . esc_html($log->bot_response) . '</td>';
    echo '<td>' . esc_html($log->created_at) . '</td>';
    echo '</tr>';
  }

  echo '</tbody></table>';
  echo '</div>';
}
