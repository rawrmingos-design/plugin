jQuery(document).ready(function ($) {
  $('#ai-chatbot-send').click(function () {
    var message = $('#ai-chatbot-input').val();
    if (message.trim() === '') return;

    $('#ai-chatbot-messages').append('<div class="user-message">User: ' + message + '</div>');
    $('#ai-chatbot-input').val('');

    $.post(chatbot_ajax.ajaxurl, { // Gunakan chatbot_ajax.ajaxurl
      action: 'ai_chatbot',
      message: message
    }, function (response) {
      console.log(response);
      var data = JSON.parse(response);
      $('#ai-chatbot-messages').append('<div class="bot-message">Bot: ' + data.response + '</div>');
    });
  });
});
