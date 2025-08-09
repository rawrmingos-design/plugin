<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="ai-chatbot-container">
    <div id="ai-chatbot-messages"></div>
    <input type="text" id="ai-chatbot-input" placeholder="Type a message...">
    <button id="ai-chatbot-send">Send</button>
</div>
<?php
function ai_chatbot_display()
{
    // Register & enqueue script
    wp_register_script('ai-chatbot-script', plugins_url('js/request.js', __FILE__), array('jquery'), null, true);
    wp_enqueue_script('ai-chatbot-script');

    // Localize script agar `ajaxurl` bisa digunakan di frontend
    wp_localize_script('ai-chatbot-script', 'chatbot_ajax', array(
        'ajaxurl' => admin_url('admin-ajax.php')
    ));

    // Register & enqueue style
    wp_register_style('ai-chatbot-style', plugins_url('css/style.css', __FILE__));
    wp_enqueue_style('ai-chatbot-style');
}
add_action('wp_enqueue_scripts', 'ai_chatbot_display');

?>


<style>

</style>