<?php
if (!defined('ABSPATH')) {
  exit;
}

// Fungsi menampilkan chatbot
function ai_chatbot_display()
{
  ob_start();
?>
  <div id="ai-chatbot-widget">
    <div id="ai-chatbot-messages"></div>
    <input type="text" id="ai-chatbot-input" placeholder="Type your message...">
    <button id="ai-chatbot-send">Send</button>
  </div>

  <style>
    #ai-chatbot-widget {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 300px;
      background: white;
      border: 1px solid #ddd;
      box-shadow: 2px 2px 10px rgba(0, 0, 0, 0.1);
      padding: 10px;
      border-radius: 5px;
    }

    #ai-chatbot-messages {
      height: 200px;
      overflow-y: auto;
      border-bottom: 1px solid #ddd;
      margin-bottom: 10px;
    }

    #ai-chatbot-input {
      width: 80%;
      padding: 5px;
    }

    #ai-chatbot-send {
      width: 18%;
      background: #0073aa;
      color: white;
      border: none;
      padding: 5px;
    }
  </style>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sendButton = document.getElementById('ai-chatbot-send');
      const inputField = document.getElementById('ai-chatbot-input');
      const messagesDiv = document.getElementById('ai-chatbot-messages');

      sendButton.addEventListener('click', function() {
        let message = inputField.value.trim();
        if (message === '') return;

        messagesDiv.innerHTML += '<div><strong>You:</strong> ' + message + '</div>';
        inputField.value = '';

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=ai_chatbot&message=' + encodeURIComponent(message)
          })
          .then(response => response.json())
          .then(data => {
            messagesDiv.innerHTML += '<div><strong>Bot:</strong> ' + data.response + '</div>';
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
          });
      });
    });
  </script>
<?php
  return ob_get_clean();
}

// Daftarkan shortcode [ai_chatbot]
add_shortcode('ai_chatbot', 'ai_chatbot_display');
?>