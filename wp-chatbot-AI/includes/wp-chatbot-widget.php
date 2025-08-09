<?php
if (!defined('ABSPATH')) {
  exit;
}

class AI_Chatbot_Widget extends WP_Widget
{

  public function __construct()
  {
    parent::__construct(
      'ai_chatbot_widget',
      __('AI Chatbot', 'ai-chatbot'),
      ['description' => __('A simple AI chatbot widget.', 'ai-chatbot')]
    );
  }

  public function widget($args, $instance)
  {
    echo $args['before_widget'];
    echo $args['before_title'] . __('AI Chatbot', 'ai-chatbot') . $args['after_title'];
    echo do_shortcode('[ai_chatbot]');
    echo $args['after_widget'];
  }

  public function form($instance)
  {
    echo '<p>' . __('This widget displays the AI chatbot.', 'ai-chatbot') . '</p>';
  }

  public function update($new_instance, $old_instance)
  {
    return $new_instance;
  }
}

// Register Widget
function ai_chatbot_register_widget()
{
  register_widget('AI_Chatbot_Widget');
}
add_action('widgets_init', 'ai_chatbot_register_widget');
