<?php
// Experimental - this is included in the zip file if activate plugins is checked

add_action('admin_init', function () {
  if (!function_exists('get_plugins')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
  }
  $plugins = get_plugins();
  foreach ($plugins as $plugin_basename => $plugin_data) {
    if (is_plugin_inactive($plugin_basename)) {
      activate_plugin($plugin_basename);
    }
  }

  // Delete this file
  unlink(__FILE__);

  // Delete this directory
  rmdir(dirname(__FILE__));
}, PHP_INT_MAX);
