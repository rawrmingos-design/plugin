<?php
if (!defined('ABSPATH')) {
  exit;
}

function ai_chatbot_dashboard_page()
{
  global $wpdb;
  $table_name = $wpdb->prefix . 'ai_chatbot_logs';

  // Total Percakapan
  $total_chats = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

  // Total Pesan dari User & AI
  $total_user_messages = $wpdb->get_var("SELECT COUNT(user_message) FROM $table_name");
  $total_bot_responses = $wpdb->get_var("SELECT COUNT(bot_response) FROM $table_name");

  // Data Aktivitas Harian
  $daily_stats = $wpdb->get_results("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM $table_name 
        GROUP BY DATE(created_at) 
        ORDER BY date ASC
    ");

  // Format data untuk Chart.js
  $labels = [];
  $counts = [];
  foreach ($daily_stats as $stat) {
    $labels[] = $stat->date;
    $counts[] = $stat->count;
  }
  $api_key = get_option('ai_chatbot_api_key');
  $masked_api_key = $api_key ? substr($api_key, 0, 3) . str_repeat('*', strlen($api_key) - 3) : 'No API Key Set';


?>

  <div class="wrap">
    <h1>Chatbot Dashboard</h1>
    <div class="card">
      <h2>Total Percakapan: <?php echo $total_chats; ?></h2>
      <h2>API Key: <?php echo esc_html($masked_api_key); ?></h2>
      <p>User Messages: <?php echo $total_user_messages; ?> | AI Responses: <?php echo $total_bot_responses; ?></p>
    </div>

    <canvas id="chatbotChart"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      var ctx = document.getElementById('chatbotChart').getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?php echo json_encode($labels); ?>,
          datasets: [{
            label: 'Percakapan per Hari',
            data: <?php echo json_encode($counts); ?>,
            borderColor: 'rgba(75, 192, 192, 1)',
            borderWidth: 2,
            fill: false
          }]
        }
      });
    </script>
  </div>

<?php
}
