<?php

/**
 * Plugin Name: AI Smart Chatbot for WP
 * Plugin URI: https://example.com
 * Description: Simple AI chatbot using OpenAI API.
 * Version: 1.0
 * Author: Fahmi
 * Author URI: https://example.com
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin path
define('AI_CHATBOT_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once AI_CHATBOT_PATH . 'includes/wp-admin-settings.php';
require_once AI_CHATBOT_PATH . 'includes/wp-chatbot-widget.php';
require_once AI_CHATBOT_PATH . 'includes/wp-chatbot-handler.php';


// Fungsi untuk menampilkan chatbot dengan shortcode
function ai_chatbot_shortcode()
{
  ob_start();
  include AI_CHATBOT_PATH . 'templates/wp-template-chatbot.php';
  return ob_get_clean();
}
add_shortcode('ai_chatbot', 'ai_chatbot_shortcode');

// Fungsi untuk membuat tabel log chatbot
function ai_chatbot_create_db_table()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'ai_chatbot_logs';

  $charset_collate = $wpdb->get_charset_collate();

  $sql = "CREATE TABLE $table_name (
      id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_message TEXT NOT NULL,
      bot_response TEXT NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  ) $charset_collate;";

  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta($sql);
}

// Jalankan saat plugin diaktifkan
register_activation_hook(__FILE__, 'ai_chatbot_create_db_table');
