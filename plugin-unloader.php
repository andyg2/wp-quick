<?php
// Experimental - this is included in the zip file if deactivate plugins is checked - askimet and hello dolly

if (!is_plugin_active('akismet/akismet.php')) {
  uninstall_plugin('akismet/akismet.php');
}
if (!is_plugin_active('hello.php')) {
  uninstall_plugin('hello.php');
}

// Delete this file
unlink(__FILE__);

// Delete this directory
rmdir(dirname(__FILE__));
