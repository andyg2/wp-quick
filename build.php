<?php
ini_set('memory_limit', '1024M');
$version = '';
define('ZIPS_DIR', 'zips/');
define('CACHE_DIR', 'cache/');
define('PLUGINS_DIR', 'plugins/');
define('BUILD_DIR', 'build/');
define('LATEST_ZIP', ZIPS_DIR . 'wp_{{version}}.zip');
define('WPDLHTML', CACHE_DIR . '.wpdlhtml');
define('CHECK_WP_VERSION_SECONDS', 86400);

if (!is_dir(ZIPS_DIR)) {
  mkdir(ZIPS_DIR, 0755, true);
}
if (!is_dir(CACHE_DIR)) {
  mkdir(CACHE_DIR, 0755, true);
}
if (!is_dir(BUILD_DIR)) {
  mkdir(BUILD_DIR, 0755, true);
}
if (!is_dir(PLUGINS_DIR)) {
  mkdir(PLUGINS_DIR, 0755, true);
}


$downloadToken = "downloadToken";
$_GET[$downloadToken] = isset($_GET[$downloadToken]) ? $_GET[$downloadToken] : null;

function wp_latest_zip_name() {
  global $version;
  return str_replace('{{version}}', $version, LATEST_ZIP);
}


// download_link, description, short_description, active_installs, ratings, version, name, slug
function get_all_plugin_slugs() {
  $url = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[per_page]=250';
  $response = wp_remote_get($url);
  $body = wp_remote_retrieve_body($response);
  $json = json_decode($body);

  $plugin_slugs = array();
  foreach ($json->plugins as $plugin) {
    $plugin_slugs[] = $plugin->slug;
  }

  return $plugin_slugs;
}



function api_zip_get_latest_version() {
  global $version;
  if (!file_exists(WPDLHTML) || time() - filemtime(WPDLHTML) > CHECK_WP_VERSION_SECONDS) {
    get_wp_dl_page_html();
  }
  $html = file_get_contents(WPDLHTML);
  $version = parse_version($html);
  $filename = ZIPS_DIR . 'wp_' . $version . '.zip';
  if (!file_exists($filename)) {
    download_latest_wordpress_zip();
  }
  if (file_exists($filename) && filesize($filename) > 10000000) {
    $build_path = unzip_latest($filename);
  } else {
    $build_path = null;
  }
  return ($build_path);
}

function get_wp_dl_page_html() {
  // Retrieve the contents of the WordPress download page
  $html = file_get_contents('https://wordpress.org/download/');
  // Make sure we have something meaningful
  if (strlen($html) > 1000) {
    file_put_contents(WPDLHTML, $html);
  } else { // if not, just touch the old version to bust the cache for a day
    touch(WPDLHTML);
  }
}

function parse_version($html) {
  $pattern = '/"softwareVersion":\s*"(.*?)"/';
  preg_match($pattern, $html, $matches);
  if (count($matches) >= 2) {
    return $matches[1];
  } else {
    return null;
  }
}

function download_latest_wordpress_zip() {
  // Set the URL of the latest WordPress ZIP package
  $url = 'https://wordpress.org/latest.zip';
  $filename = wp_latest_zip_name();
  curl_save_zip($url, $filename);
}

function curl_save_zip($url, $filename) {
  // Initialize cURL and set options
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_HEADER, false);

  // Download the ZIP package
  $content = curl_exec($ch);

  file_put_contents($filename, $content);

  // Close the cURL session
  curl_close($ch);
}

function delete_directory($dir_path) {
  if (!is_dir($dir_path)) {
    return;
  }

  $dir = opendir($dir_path);
  while ($file = readdir($dir)) {
    if ($file != '.' && $file != '..') {
      $file_path = $dir_path . '/' . $file;
      if (is_dir($file_path)) {
        delete_directory($file_path);
      } else {
        unlink($file_path);
      }
    }
  }
  closedir($dir);
  rmdir($dir_path);
}


function unzip_latest($path_to_zip) {



  // Open the ZIP file
  $build_path = BUILD_DIR . generate_seeded_unique_id(ip2long(get_client_ip())) . '/';
  if (!is_dir($build_path)) {
    mkdir($build_path, 0755, true);
  } elseif (is_dir($build_path . 'wordpress')) {
    delete_directory($build_path . 'wordpress');
  }

  $zip = new ZipArchive;
  $res = $zip->open($path_to_zip);

  // Extract the contents of the ZIP file
  if ($res === true) {
    $zip->extractTo($build_path);
    $zip->close();

    if (isset($_GET['activate']) || isset($_GET['deactivate'])) {

      // Create a mu-plugins directory
      if (!is_dir($build_path . 'wordpress/wp-content/mu-plugins')) {
        mkdir($build_path . 'wordpress/wp-content/mu-plugins', 0755, true);
      }

      // Copy a run once activator
      if (isset($_GET['activate']) && file_exists('plugin-loader.php')) {
        copy('plugin-loader.php', $build_path . 'wordpress/wp-content/mu-plugins/plugin-loader.php');
      }
      // Copy a run once deactivator
      if (isset($_GET['deactivate']) && file_exists('plugin-unloader.php')) {
        copy('plugin-unloader.php', $build_path . 'wordpress/wp-content/mu-plugins/plugin-unloader.php');
      }
    }
    return $build_path;
  } else {
    return null;
  }
}


function json_decode_array($json) {
  // Attempt to convert supplies string into and array
  $json = json_decode($json, true);
  // if it's empty or there was an error return an empty array, otherwise the populated array
  return (json_last_error() === JSON_ERROR_NONE ? (empty($json) ? array() : $json) : array());
}

function pre($a, $h = false) {
  echo $h ? '<h3 style="margin-bottom: 0px;">' . $h . '</h3><pre style="margin-top: 0px;">' : '<pre style="margin-top: 0px;">';
  print_r($a);
  echo '</pre>';
}

function prex($a, $h = false, $dbg = false) {
  pre($a, $h);
  if ($dbg) {
    echo '<pre>';
    print_r(debug_backtrace());
    echo '</pre>';
  }
  exit;
}

function get_client_ip() {
  if (getenv('HTTP_CLIENT_IP'))
    $ipaddress = getenv('HTTP_CLIENT_IP');
  else if (getenv('HTTP_X_FORWARDED_FOR'))
    $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
  else if (getenv('HTTP_X_FORWARDED'))
    $ipaddress = getenv('HTTP_X_FORWARDED');
  else if (getenv('HTTP_FORWARDED_FOR'))
    $ipaddress = getenv('HTTP_FORWARDED_FOR');
  else if (getenv('HTTP_FORWARDED'))
    $ipaddress = getenv('HTTP_FORWARDED');
  else if (getenv('REMOTE_ADDR'))
    $ipaddress = getenv('REMOTE_ADDR');
  else
    $ipaddress = 'UNKNOWN';
  return $ipaddress;
}

function generate_seeded_unique_id($seed = null, $length = 10) {
  if ($seed) {
    srand($seed);
  }
  // Generate a random number between 0 and 9999999999
  $random_number = rand(0, intval(str_repeat(9, $length)));
  // Pad the random number with leading zeros to make it 6 digits
  $padded_number = str_pad($random_number, $length, "0", STR_PAD_LEFT);
  // Return the padded number as the user ID
  return $padded_number;
}

function get_all_plugins() {
  return false;
  $plugins = array();
  $page = 1;
  $total_pages = 1;

  while ($page <= $total_pages) {
    set_time_limit(120);
    $url = 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins&request[per_page]=250&request[page]=' . $page;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($response, true);

    foreach ($response['plugins'] as $plugin) {

      $plugin_path = PLUGINS_DIR . $plugin['slug'][0] . '/' . $plugin['slug'][1] . '/' . $plugin['slug'] . '/' . $plugin['version'];
      if (!is_dir($plugin_path)) {
        mkdir($plugin_path, 0755, true);
      }
      file_put_contents($plugin_path . '/' . $plugin['version'] . '.json', json_encode($plugin));

      $plugin_data = array(
        'short_description' => $plugin['short_description'],
        'active_installs' => $plugin['active_installs'],
        'ratings' => $plugin['ratings'],
        'version' => $plugin['version'],
        'name' => $plugin['name'],
        'slug' => $plugin['slug']
      );
      array_push($plugins, $plugin_data);
      file_put_contents(CACHE_DIR . 'all_plugins.json', json_encode($plugins));
    }

    $page++;
    $total_pages = $response['info']['pages'];
  }

  return $plugins;
}


function get_plugin($slug) {

  set_time_limit(120);
  $url = 'https://api.wordpress.org/plugins/info/1.2/?&action=query_plugins&request[per_page]=5&request[search]=' . $slug;
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);
  curl_close($ch);
  $response = json_decode($response, true);

  foreach ($response['plugins'] as $plugin) {
    if ($plugin['slug'] == $slug) {
      $plugin_path = PLUGINS_DIR . $plugin['slug'][0] . '/' . $plugin['slug'][1] . '/';
      if (!is_dir($plugin_path)) {
        mkdir($plugin_path, 0755, true);
      }

      //download_link
      $path_to_zip = $plugin_path . '/' . $slug . '-' . $plugin['version'] . '.zip';
      if (!file_exists($path_to_zip)) {
        curl_save_zip($plugin['download_link'], $path_to_zip);
      }

      if (file_exists($path_to_zip)) {
        $zip = new ZipArchive;
        $res = $zip->open($path_to_zip);
        $build_path = BUILD_DIR . generate_seeded_unique_id(ip2long(get_client_ip())) . '/wordpress/wp-content/plugins/';
        if (!is_dir($build_path)) {
          mkdir($build_path, 0755, true);
        }
        // Extract the contents of the ZIP file
        if ($res === true) {
          $zip->extractTo($build_path);
          $zip->close();

          return $plugin['version'];
        }
      } else {
        // couldn't get plugin zip
      }
    } else {
      // wrong slug
    }
  } // each
}

$build_path = api_zip_get_latest_version();
if ($build_path) {
  if (isset($_GET['p'])) {
    $plugins = array_map('trim', explode(';', $_GET['p']));
    $slugs = [];
    foreach ($plugins as $plugin) {
      $v = get_plugin($plugin);
      $plugin_slug = trim(rtrim(substr($plugin, 0, 20), '-'));
      $slugs[] = $plugin_slug . '_' . $v;
    }
    sort($slugs);
    $slugs = implode(',', $slugs);

    if (strlen($slugs) >= 250) {
      $built_filename = slugs_to_filemap($slugs); // zips/wp_6.1.1_7867687614.txt containing slugs
      $dl_filename = substr('wp_' . $version . '_' . $slugs, 0, 250) . '.zip';
    } else {
      $built_filename = 'wp_' . $version . '_' . $slugs . '.zip';
      $dl_filename = $built_filename;
    }

    if (!file_exists(ZIPS_DIR . $built_filename)) {
      create_zip($build_path, ZIPS_DIR . $built_filename);
    }

    if (file_exists(ZIPS_DIR . $built_filename)) {
      if (isset($_GET[$downloadToken])) {
        setCookieToken($downloadToken, $_GET[$downloadToken], false);
      }
      header("Content-Description: File Transfer");
      header('Content-Disposition: attachment; filename="' . $dl_filename . '"');
      header("Content-Type: application/zip");
      header("Content-Transfer-Encoding: binary");
      readfile(ZIPS_DIR . $built_filename);
      // header('Location: ' . $built_filename);
    } else {
      echo 'Doh! - Sorry, something went wrong, try again later';
    }
  }
}

function slugs_to_filemap($slugs) {
  return 'wordpress_' . generate_seeded_unique_id($slugs, 20) . '.zip';
}

function create_zip($path_to_files, $zip_file_path) {
  // Get real path for the directory
  $root_path = realpath($path_to_files);

  // Initialize archive object
  $zip = new ZipArchive();
  $zip->open($zip_file_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

  // Create recursive directory iterator
  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root_path),
    RecursiveIteratorIterator::LEAVES_ONLY
  );

  foreach ($files as $name => $file) {
    // Skip directories (they are added automatically)
    if (!$file->isDir()) {
      // Get real and relative path for current file
      $file_path = $file->getRealPath();
      $relative_path = substr($file_path, strlen($root_path) + 1);

      // Add current file to archive
      $zip->addFile($file_path, $relative_path);
    }
  }

  // Zip archive will be created only after closing object
  $zip->close();

  // Check if zip file was created and return true or false
  return file_exists($zip_file_path);
}

function setCookieToken($cookieName, $cookieValue, $httpOnly = true, $secure = false) {
  setcookie(
    $cookieName,
    $cookieValue,
    2147483647,            // expires January 1, 2038
    "/",                   // your path
    $_SERVER["HTTP_HOST"], // your domain
    $secure,               // Use true over HTTPS
    $httpOnly              // Set true for $AUTH_COOKIE_NAME
  );
}
