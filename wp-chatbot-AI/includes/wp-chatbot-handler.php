<?php
if (!defined('ABSPATH')) {
  exit;
}

// Handle AJAX Request
function ai_chatbot_response()
{
  $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
  $api_key = get_option('ai_chatbot_api_key');

  if (!$api_key) {
    echo json_encode(['response' => 'API key is missing.']);
    wp_die();
  }

  $response = wp_remote_post('https://api.aimlapi.com/chat/completions', [
    'headers' => [
      'Authorization' => 'Bearer ' . $api_key,
      'Content-Type'  => 'application/json'
    ],
    'body' => json_encode([
      'model'    => 'gpt-4o',  // Pakai model Deepseek R1
      'messages' => [['role' => 'user', 'content' => $message]],
      'max-token' => 512,
      'stream'   => false
    ])
  ]);

  if (is_wp_error($response)) {
    echo json_encode(['response' => 'Error fetching response.']);
  } else {
    $body = json_decode(wp_remote_retrieve_body($response), true);
    echo json_encode(['response' => $body['choices'][0]['message']['content'] ?? 'No response from AI.']);
    global $wpdb;
    $table_name = $wpdb->prefix . 'ai_chatbot_logs';

    $wpdb->insert($table_name, [
      'user_message' => $message,
      'bot_response' => $body['choices'][0]['message']['content'] ?? 'No response from AI.',
    ]);
  }

  wp_die();
}

add_action('wp_ajax_ai_chatbot', 'ai_chatbot_response');
