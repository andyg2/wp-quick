<?php
// This is included in the zip file if activate plugins is checked

add_action('admin_init', function () {
  if (!function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
  }
  delete_plugins(['hello.php', 'akismet/akismet.php']);

  // Delete this file
  unlink(__FILE__);
}, 1);
