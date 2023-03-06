<?php
// Experimental

$plugins = get_plugins();
foreach ($plugins as $plugin_basename => $plugin_data) {
  if (is_plugin_inactive($plugin_basename)) {
    activate_plugin($plugin_basename);
  }
}
unlink(__FILE__);
rmdir(dirname(__FILE__));
