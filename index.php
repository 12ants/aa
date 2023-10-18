<?php

class Helper {
  protected static $instance = null;

  protected $uriData = null;

  public static function get_instance() {
    if (null == self::$instance) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  private function __construct() {
  }

  private function is_ssl() {
    if ( isset( $_SERVER['HTTPS'] ) ) {
      if ( 'on' == strtolower( $_SERVER['HTTPS'] ) ) {
        return true;
      }
      if ( '1' == $_SERVER['HTTPS'] ) {
        return true;
      }
    } elseif ( isset( $_SERVER['SERVER_PORT'] ) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
      return true;
    }
    return false;
  }

  public function getInstallerFileName() {
    if (null == $this->uriData) {
      $this->handleUri();
    }
    return $this->uriData['installerFileName'];
  }

  public function getCurrentSite() {
    if (null == $this->uriData) {
      $this->handleUri();
    }
    return $this->uriData['currentSite'];
  }
  public function getOldPath() {
    $scan_path = './njt-fastdup-installer/scan_package.json';
    $result = null;
    if (file_exists($scan_path)) {
      $package_obj = json_decode(file_get_contents($scan_path));
      if(isset($package_obj->Archive) && isset($package_obj->Archive->compress_dir)) {
        $result = $package_obj->Archive->compress_dir;
      }
    }

    return $result;
  }
  public function getOldSiteUrl() {
    $scan_path = './njt-fastdup-installer/scan_package.json';
    $result = null;
    if (file_exists($scan_path)) {
      $package_obj = json_decode(file_get_contents($scan_path));
      $result = $package_obj->site_url;
    }

    return $result;
  }
  private function handleUri() {
    $protocol = $this->is_ssl() ? 'https://' : 'http://';
    $actual_link = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    $installerFileName = basename($_SERVER['REQUEST_URI']);
    $currentSite = str_replace($installerFileName, "", $actual_link);
    $this->uriData = array(
      'installerFileName' => $installerFileName,
      'currentSite' => $currentSite
    );
  }
}

/**
 * Unzip class
 *
 * @author Anthon Pang <apang@softwaredevelopment.ca>
 */
class Unzip {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    // Read directory and pick .zip, .rar and .gz files.
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== false) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'zip'
          || pathinfo($file, PATHINFO_EXTENSION) === 'gz'
          || pathinfo($file, PATHINFO_EXTENSION) === 'rar'
        ) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      // if (!empty($this->zipfiles)) {
      //   $GLOBALS['status'] = array('info' => '.zip or .gz or .rar files found, ready for extraction');
      // } else {
      //   $GLOBALS['status'] = array('info' => 'No .zip or .gz or rar files found. So only zipping functionality available.');
      // }
    }
  }

  /**
   * @var array
   */
  static $statusStrings = array(
    \ZipArchive::ER_OK => 'No error',
    \ZipArchive::ER_MULTIDISK => 'Multi-disk zip archives not supported',
    \ZipArchive::ER_RENAME => 'Renaming temporary file failed',
    \ZipArchive::ER_CLOSE => 'Closing zip archive failed',
    \ZipArchive::ER_SEEK => 'Seek error',
    \ZipArchive::ER_READ => 'Read error',
    \ZipArchive::ER_WRITE => 'Write error',
    \ZipArchive::ER_CRC => 'CRC error',
    \ZipArchive::ER_ZIPCLOSED => 'Containing zip archive was closed',
    \ZipArchive::ER_NOENT => 'No such file',
    \ZipArchive::ER_EXISTS => 'File already exists',
    \ZipArchive::ER_OPEN => 'Can\'t open file',
    \ZipArchive::ER_TMPOPEN => 'Failure to create temporary file',
    \ZipArchive::ER_ZLIB => 'Zlib error',
    \ZipArchive::ER_MEMORY => 'Malloc failure',
    \ZipArchive::ER_CHANGED => 'Entry has been changed',
    \ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
    \ZipArchive::ER_EOF => 'Premature EOF',
    \ZipArchive::ER_INVAL => 'Invalid argument',
    \ZipArchive::ER_NOZIP => 'Not a zip archive',
    \ZipArchive::ER_INTERNAL => 'Internal error',
    \ZipArchive::ER_INCONS => 'Zip archive inconsistent',
    \ZipArchive::ER_REMOVE => 'Can\'t remove file',
    \ZipArchive::ER_DELETED => 'Entry has been deleted',
  );

  /**
   * Make sure target path ends in '/'
   *
   * @param string $path
   *
   * @return string
   */
  private function fixPath($path) {
    if (substr($path, -1) === '/') {
      $path .= '/';
    }

    return $path;
  }

  /**
   * Open .zip archive
   *
   * @param string $zipFile
   *
   * @return \ZipArchive
   */
  private function openZipFile($zipFile) {
    $zipArchive = new \ZipArchive;

    if ($zipArchive->open($zipFile) !== true) {
      throw new \Exception('Error opening ' . $zipFile);
    }

    return $zipArchive;
  }

  /**
   * Extract list of filenames from .zip
   *
   * @param \ZipArchive $zipArchive
   *
   * @return array
   */
  private function extractFilenames(\ZipArchive $zipArchive) {
    $filenames = array();
    $fileCount = $zipArchive->numFiles;

    for ($i = 0; $i < $fileCount; $i++) {
      if (($filename = $this->extractFilename($zipArchive, $i)) !== false) {
        $filenames[] = $filename;
      }
    }
    return $filenames;
  }

  /**
   * Test for valid filename path
   *
   * The .zip file is untrusted input.  We check for absolute path (i.e., leading slash),
   * possible directory traversal attack (i.e., '..'), and use of PHP wrappers (i.e., ':').
   *
   * @param string $path
   *
   * @return boolean
   */
  private function isValidPath($path) {
    $pathParts = explode('/', $path);

    if (!strncmp($path, '/', 1) ||
      array_search('..', $pathParts) !== false ||
      strpos($path, ':') !== false) {
      return false;
    }

    return true;
  }

  /**
   * Extract filename from .zip
   *
   * @param \ZipArchive $zipArchive Zip file
   * @param integer     $fileIndex  File index
   *
   * @return string
   */
  private function extractFilename(\ZipArchive $zipArchive, $fileIndex) {
    $entry = $zipArchive->statIndex($fileIndex);

    // convert Windows directory separator to Unix style
    $filename = str_replace('\\', '/', $entry['name']);

    if ($this->isValidPath($filename)) {
      return $filename;
    }

    throw new \Exception('Invalid filename path in zip archive: ' . $filename);
  }

  /**
   * Get error
   *
   * @param integer $status ZipArchive status
   *
   * @return string
   */
  private function getError($status) {
    $statusString = isset($this->statusStrings[$status])
    ? $this->statusStrings[$status]
    : 'Unknown status';

    return $statusString . '(' . $status . ')';
  }

  /**
   * Extract zip file to target path
   *
   * @param string $zipFile    Path of .zip file
   * @param string $targetPath Extract to this target (destination) path
   *
   * @return mixed Array of filenames corresponding to the extracted files
   *
   * @throw \Exception
   */
  public function extract($zipFile) {
    $zipArchive = $this->openZipFile($zipFile);
    $targetPath = $this->fixPath(__DIR__);

    $filenames = $this->extractFilenames($zipArchive);
    if ($zipArchive->extractTo($targetPath, $filenames) === false) {
      throw new \Exception($this->getError($zipArchive->status));
    }

    $zipArchive->close();

    return $filenames;
  }
}

/*
 * ClassName: PHP MySQL Importer v2.0.1
 * PHP class for importing big SQL files into a MySql server.
 * Author: David Castillo - davcs86@gmail.com
 * Hire me on: https://www.freelancer.com/u/DrAKkareS.html
 * Blog: http://blog.d-castillo.info/
 */

class MySQLImporter {
  public $hadErrors = false;
  public $errors = array();
  // private $conn = null;
  public $conn = null;

  public function __construct($host, $user, $pass, $port = false) {
    if ($port == false) {
      $port = ini_get("mysqli.default_port");
    }
    $this->hadErrors = false;
    $this->errors = array();
    // $this->conn = new mysqli($host, $user, $pass, "", $port);
    @$connection = new mysqli($host, $user, $pass, "", $port);
    $this->conn = $connection;
    // Fix bug max_allowed_packet
    if ($this->conn->connect_error) {
      $this->addError("Connect Error (" . $this->conn->connect_errno . ") " . $this->conn->connect_error);
      return;
    }
    $this->conn->query('SET GLOBAL max_allowed_packet = ' . 500 * 1024 * 1024);
  }

  private function addError($errorStr) {
    $this->hadErrors = true;
    $this->errors[] = $errorStr;
  }

  public function changeDatabaseUrl($old_domain, $new_domain) {
    $scan_path = './njt-fastdup-installer/scan_package.json';
    if (file_exists($scan_path)) {
      $package_obj = json_decode(file_get_contents($scan_path));
      $db_prefix = isset($package_obj->Database->prefix) ? $package_obj->Database->prefix : 'wp_';
    }

    if (!isset($db_prefix)) {
      $db_prefix = "wp_";
    }
    $old_domain = rtrim($old_domain, '/');
    $new_domain = rtrim($new_domain, '/');

    $query = "UPDATE {$db_prefix}options SET option_value = replace(option_value, '$old_domain', '$new_domain' ) WHERE option_name = 'home' OR option_name = 'siteurl'; \n
    UPDATE {$db_prefix}options SET option_value = '' WHERE option_name = 'njt_fastdup_package_id_active'; \n
    UPDATE {$db_prefix}posts SET guid = replace(guid, '$old_domain', '$new_domain'  ); \n
    UPDATE {$db_prefix}posts SET post_content = replace(post_content,  '$old_domain' ,  '$new_domain'  ); \n";
    /*
    UPDATE {$db_prefix}postmeta SET meta_value = replace(meta_value,  '$old_domain' ,  '$new_domain'  ) WHERE meta_value LIKE '%$old_domain%' AND meta_value NOT REGEXP '^[ao]:[0-9].*}$'; \n
    UPDATE {$db_prefix}usermeta SET meta_value = replace(meta_value, '$old_domain', '$new_domain' ) WHERE meta_value LIKE '%$old_domain%' AND meta_value NOT REGEXP '^[ao]:[0-9].*}$';  \n
    */
    $query .= "UPDATE {$db_prefix}postmeta SET meta_value = replace(meta_value,  '$old_domain' ,  '$new_domain'  ) WHERE meta_value LIKE '%$old_domain%' AND (meta_value NOT LIKE 'a%}' AND meta_value NOT LIKE 'o%}');\n";
    $query .= "UPDATE {$db_prefix}usermeta SET meta_value = replace(meta_value, '$old_domain', '$new_domain' ) WHERE meta_value LIKE '%$old_domain%' AND (meta_value NOT LIKE 'a%}' AND meta_value NOT LIKE 'o%}');  \n";
    $query .= "UPDATE {$db_prefix}links SET link_url = replace(link_url, '$old_domain', '$new_domain' ); \n
    UPDATE {$db_prefix}links SET link_image = replace(link_image, '$old_domain', '$new_domain' ); \n";

    $query .= "DELETE FROM {$db_prefix}njt_fastdup_packages; \n";
    $query .= "DELETE FROM {$db_prefix}njt_fastdup_entities; ";

    return $query;
  }

  public function getOldSiteUrl() {
    $scan_path = './njt-fastdup-installer/scan_package.json';
    $result = null;
    if (file_exists($scan_path)) {
      $package_obj = json_decode(file_get_contents($scan_path));
      $result = $package_obj->site_url;
    }

    return $result;
  }

  public function doImport($sqlFile, $database = "", $createDB = false, $dropDB = false) {
    if ($this->hadErrors == false) {
      //Drop database if it's required
      if ($dropDB && $database != "") {
        if (!$this->conn->query("DROP DATABASE IF EXISTS `$database`")) {
          $this->addError("Query error (" . $this->conn->errno . ") " . $this->conn->error);
        }
      }
      //Create the database if it's required
      if ($createDB && $database != "") {
        if (!$this->conn->query("CREATE DATABASE IF NOT EXISTS `$database`")) {
          $this->addError("Query error (" . $this->conn->errno . ") " . $this->conn->error);
        }
      }

      //Select the database if it's required
      if ($database != "") {
        if (!$this->conn->select_db($database)) {
          $this->addError("Query error (" . $this->conn->errno . ") " . $this->conn->error);
        }
      }

      // Change database url
      $old_site = $this->getOldSiteUrl();
      $new_site = Helper::get_instance()->getCurrentSite();

      if ($old_site && $new_site) {
        $query_change_db_url = $this->changeDatabaseUrl($old_site, $new_site);
      }

      @ini_set('memory_limit', '-1');
      if (is_file($sqlFile) && is_readable($sqlFile)) {
        try {
          $f = fopen($sqlFile, "r");
          $sqlFile = fread($f, filesize($sqlFile));

          // Change database url
          if (isset($query_change_db_url)) {
            $sqlFile .= $query_change_db_url;
          }

          // processing and parsing the content
          $sqlFile = str_replace("\r", "\n", $sqlFile);
          $lines = preg_split("/\n/", $sqlFile);

          $queryStr = "";
          foreach ($lines as $line) {
            // Skip it if it's a comment
            if (substr($line, 0, 2) == '--' || $line == '') {
              continue;
            }

            // Add this line to the current segment
            $queryStr .= $line;
            // If it has a semicolon at the end, it's the end of the query
            if (substr(trim($line), -1, 1) == ';') {

              if (!$this->conn->query($queryStr)) {
                $this->addError("Query error (" . $this->conn->errno . ") " . $this->conn->error . "\r\n\r\nOriginal Query:\r\n\r\n" . $queryStr);
              }
              // Reset temp variable to empty
              $queryStr = '';

            }
          }

        } catch (Exception $error) {
          $this->addError("File error: (" . $error->getCode() . ") " . $error->getMessage());
        }
      } else {
        $this->addError("File error: '" . $sqlFile . "' is not a readable file.");
      }
    }
  }
}
/**
 * Transforms a wp-config.php file.
 */
class WPConfigTransformer {

  /**
   * Path to the wp-config.php file.
   *
   * @var string
   */
  protected $wp_config_path;

  /**
   * Original source of the wp-config.php file.
   *
   * @var string
   */
  protected $wp_config_src;

  /**
   * Array of parsed configs.
   *
   * @var array
   */
  protected $wp_configs = array();

  /**
   * Instantiates the class with a valid wp-config.php.
   *
   * @throws \Exception If the wp-config.php file is missing.
   * @throws \Exception If the wp-config.php file is not writable.
   *
   * @param string $wp_config_path Path to a wp-config.php file.
   */
  public function __construct($wp_config_path) {
    $basename = basename($wp_config_path);

    if (!file_exists($wp_config_path)) {
      throw new \Exception("{$basename} does not exist.");
    }

    if (!is_writable($wp_config_path)) {
      throw new \Exception("{$basename} is not writable.");
    }

    $this->wp_config_path = $wp_config_path;
  }

  /**
   * Checks if a config exists in the wp-config.php file.
   *
   * @throws \Exception If the wp-config.php file is empty.
   * @throws \Exception If the requested config type is invalid.
   *
   * @param string $type Config type (constant or variable).
   * @param string $name Config name.
   *
   * @return bool
   */
  public function exists($type, $name) {
    $wp_config_src = file_get_contents($this->wp_config_path);

    if (!trim($wp_config_src)) {
      throw new \Exception('Config file is empty.');
    }
    // Normalize the newline to prevent an issue coming from OSX.
    $this->wp_config_src = str_replace(array("\n\r", "\r"), "\n", $wp_config_src);
    $this->wp_configs = $this->parse_wp_config($this->wp_config_src);

    if (!isset($this->wp_configs[$type])) {
      throw new \Exception("Config type '{$type}' does not exist.");
    }

    return isset($this->wp_configs[$type][$name]);
  }

  /**
   * Get the value of a config in the wp-config.php file.
   *
   * @throws \Exception If the wp-config.php file is empty.
   * @throws \Exception If the requested config type is invalid.
   *
   * @param string $type Config type (constant or variable).
   * @param string $name Config name.
   *
   * @return array
   */
  public function get_value($type, $name) {
    $wp_config_src = file_get_contents($this->wp_config_path);

    if (!trim($wp_config_src)) {
      throw new \Exception('Config file is empty.');
    }

    $this->wp_config_src = $wp_config_src;
    $this->wp_configs = $this->parse_wp_config($this->wp_config_src);

    if (!isset($this->wp_configs[$type])) {
      throw new \Exception("Config type '{$type}' does not exist.");
    }

    return $this->wp_configs[$type][$name]['value'];
  }

  /**
   * Adds a config to the wp-config.php file.
   *
   * @throws \Exception If the config value provided is not a string.
   * @throws \Exception If the config placement anchor could not be located.
   *
   * @param string $type    Config type (constant or variable).
   * @param string $name    Config name.
   * @param string $value   Config value.
   * @param array  $options (optional) Array of special behavior options.
   *
   * @return bool
   */
  public function add($type, $name, $value, array $options = array()) {
    if (!is_string($value)) {
      throw new \Exception('Config value must be a string.');
    }

    if ($this->exists($type, $name)) {
      return false;
    }

    $defaults = array(
      'raw' => false, // Display value in raw format without quotes.
      'anchor' => "/* That's all, stop editing!", // Config placement anchor string.
      'separator' => PHP_EOL, // Separator between config definition and anchor string.
      'placement' => 'before', // Config placement direction (insert before or after).
    );

    list($raw, $anchor, $separator, $placement) = array_values(array_merge($defaults, $options));

    $raw = (bool) $raw;
    $anchor = (string) $anchor;
    $separator = (string) $separator;
    $placement = (string) $placement;

    if (false === strpos($this->wp_config_src, $anchor)) {
      $other_anchor_points = array(
        '/** Absolute path to the WordPress directory',
        "if ( !defined('ABSPATH') )",
        '/** Sets up WordPress vars and included files',
        'require_once(ABSPATH',
      );
      foreach ($other_anchor_points as $anchor_point) {
        $anchor_point = (string) $anchor_point;
        if (false !== strpos($this->wp_config_src, $anchor_point)) {
          $anchor = $anchor_point;
          break;
        }
      }
    }

    if (false === strpos($this->wp_config_src, $anchor)) {
      throw new \Exception('Unable to locate placement anchor.');
    }

    $new_src = $this->normalize($type, $name, $this->format_value($value, $raw));
    $new_src = ('after' === $placement) ? $anchor . $separator . $new_src : $new_src . $separator . $anchor;
    $contents = str_replace($anchor, $new_src, $this->wp_config_src);

    return $this->save($contents);
  }

  /**
   * Updates an existing config in the wp-config.php file.
   *
   * @throws \Exception If the config value provided is not a string.
   *
   * @param string $type    Config type (constant or variable).
   * @param string $name    Config name.
   * @param string $value   Config value.
   * @param array  $options (optional) Array of special behavior options.
   *
   * @return bool
   */
  public function update($type, $name, $value, array $options = array()) {
    if (!is_string($value)) {
      throw new \Exception('Config value must be a string.');
    }

    $defaults = array(
      'add' => true, // Add the config if missing.
      'raw' => false, // Display value in raw format without quotes.
      'normalize' => false, // Normalize config output using WP Coding Standards.
    );

    list($add, $raw, $normalize) = array_values(array_merge($defaults, $options));

    $add = (bool) $add;
    $raw = (bool) $raw;
    $normalize = (bool) $normalize;

    if (!$this->exists($type, $name)) {
      return ($add) ? $this->add($type, $name, $value, $options) : false;
    }

    $old_src = $this->wp_configs[$type][$name]['src'];
    $old_value = $this->wp_configs[$type][$name]['value'];
    $new_value = $this->format_value($value, $raw);

    if ($normalize) {
      $new_src = $this->normalize($type, $name, $new_value);
    } else {
      $new_parts = $this->wp_configs[$type][$name]['parts'];
      $new_parts[1] = str_replace($old_value, $new_value, $new_parts[1]); // Only edit the value part.
      $new_src = implode('', $new_parts);
    }

    $contents = preg_replace(
      sprintf('/(?<=^|;|<\?php\s|<\?\s)(\s*?)%s/m', preg_quote(trim($old_src), '/')),
      '$1' . str_replace('$', '\$', trim($new_src)),
      $this->wp_config_src
    );

    return $this->save($contents);
  }

  /**
   * Removes a config from the wp-config.php file.
   *
   * @param string $type Config type (constant or variable).
   * @param string $name Config name.
   *
   * @return bool
   */
  public function remove($type, $name) {
    if (!$this->exists($type, $name)) {
      return false;
    }

    $pattern = sprintf('/(?<=^|;|<\?php\s|<\?\s)%s\s*(\S|$)/m', preg_quote($this->wp_configs[$type][$name]['src'], '/'));
    $contents = preg_replace($pattern, '$1', $this->wp_config_src);

    return $this->save($contents);
  }

  /**
   * Applies formatting to a config value.
   *
   * @throws \Exception When a raw value is requested for an empty string.
   *
   * @param string $value Config value.
   * @param bool   $raw   Display value in raw format without quotes.
   *
   * @return mixed
   */
  protected function format_value($value, $raw) {
    if ($raw && '' === trim($value)) {
      throw new \Exception('Raw value for empty string not supported.');
    }

    return ($raw) ? $value : var_export($value, true);
  }

  /**
   * Normalizes the source output for a name/value pair.
   *
   * @throws \Exception If the requested config type does not support normalization.
   *
   * @param string $type  Config type (constant or variable).
   * @param string $name  Config name.
   * @param mixed  $value Config value.
   *
   * @return string
   */
  protected function normalize($type, $name, $value) {
    if ('constant' === $type) {
      $placeholder = "define( '%s', %s );";
    } elseif ('variable' === $type) {
      $placeholder = '$%s = %s;';
    } else {
      throw new \Exception("Unable to normalize config type '{$type}'.");
    }

    return sprintf($placeholder, $name, $value);
  }

  /**
   * Parses the source of a wp-config.php file.
   *
   * @param string $src Config file source.
   *
   * @return array
   */
  protected function parse_wp_config($src) {
    $configs = array();
    $configs['constant'] = array();
    $configs['variable'] = array();

    // Strip comments.
    foreach (token_get_all($src) as $token) {
      if (in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) {
        $src = str_replace($token[1], '', $src);
      }
    }

    preg_match_all('/(?<=^|;|<\?php\s|<\?\s)(\h*define\s*\(\s*[\'"](\w*?)[\'"]\s*)(,\s*(\'\'|""|\'.*?[^\\\\]\'|".*?[^\\\\]"|.*?)\s*)((?:,\s*(?:true|false)\s*)?\)\s*;)/ims', $src, $constants);
    preg_match_all('/(?<=^|;|<\?php\s|<\?\s)(\h*\$(\w+)\s*=)(\s*(\'\'|""|\'.*?[^\\\\]\'|".*?[^\\\\]"|.*?)\s*;)/ims', $src, $variables);

    if (!empty($constants[0]) && !empty($constants[1]) && !empty($constants[2]) && !empty($constants[3]) && !empty($constants[4]) && !empty($constants[5])) {
      foreach ($constants[2] as $index => $name) {
        $configs['constant'][$name] = array(
          'src' => $constants[0][$index],
          'value' => $constants[4][$index],
          'parts' => array(
            $constants[1][$index],
            $constants[3][$index],
            $constants[5][$index],
          ),
        );
      }
    }

    if (!empty($variables[0]) && !empty($variables[1]) && !empty($variables[2]) && !empty($variables[3]) && !empty($variables[4])) {
      // Remove duplicate(s), last definition wins.
      $variables[2] = array_reverse(array_unique(array_reverse($variables[2], true)), true);
      foreach ($variables[2] as $index => $name) {
        $configs['variable'][$name] = array(
          'src' => $variables[0][$index],
          'value' => $variables[4][$index],
          'parts' => array(
            $variables[1][$index],
            $variables[3][$index],
          ),
        );
      }
    }

    return $configs;
  }

  /**
   * Saves new contents to the wp-config.php file.
   *
   * @throws \Exception If the config file content provided is empty.
   * @throws \Exception If there is a failure when saving the wp-config.php file.
   *
   * @param string $contents New config contents.
   *
   * @return bool
   */
  protected function save($contents) {
    if (!trim($contents)) {
      throw new \Exception('Cannot save the config file with empty contents.');
    }

    if ($contents === $this->wp_config_src) {
      return false;
    }

    $result = file_put_contents($this->wp_config_path, $contents, LOCK_EX);

    if (false === $result) {
      throw new \Exception('Failed to update the config file.');
    }

    return true;
  }
  public function normalize_path( $path ) {
      $path = str_replace( '\\', '/', $path );
      $path = preg_replace( '|(?<=.)/+|', '/', $path );
      if ( ':' === substr( $path, 1, 1 ) ) {
          $path = ucfirst( $path );
      }
      return $path;
  }
}

class Installer_Package {
  public $unzipper;

  public function __construct() {
    $this->unzipper = new Unzip;
  }

  public function deleteDir($dirPath) {
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
      $dirPath .= '/';
    }
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
      if (is_dir($file)) {
        $this->deleteDir($file);
      } else {
        if (is_writable($file)) {
          unlink($file);
        } else {
          if (!file_exists($file)) {
            throw new \Exception("$file not found!");
          } else {
            throw new \Exception("$file must have permission read & write!");
          }
        }
      }
    }
    rmdir($dirPath);
  }

  public function cleanUnzipDir() {
    $installer_folder = dirname(__FILE__) . '/njt-fastdup-installer';

    // Delete Folder Installer
    if (is_dir($installer_folder)) {
      if (is_readable($installer_folder)) {
        $this->deleteDir($installer_folder);
      } else {
        throw new \Exception("$installer_folder must have permission read");
      }
    }
  }

  public function cleanInstallFile() {
    try {
      $status = true;
      $message_detail = "";
      $message_exception = "";

      // Delete File Zip
      if (isset($_COOKIE['njt-fastdup-zip-name'])) {
        $installer_file_zip = dirname(__FILE__) . '/' . $_COOKIE['njt-fastdup-zip-name'];
        if (file_exists($installer_file_zip)) {
          if (is_readable($installer_file_zip)) {
            unlink($installer_file_zip);
          } else {
            $status = false;
            throw new \Exception("$installer_file_zip must have permission read");
          }
        }
      }

      $installer_file_path = dirname(__FILE__) . '/' . Helper::get_instance()->getInstallerFileName();
      if (is_file($installer_file_path)) {
        unlink($installer_file_path);
      }

    } catch (\Exception $e) {
      $message_exception = $e->getMessage();
      $status = false;
    }

    $dataResp = array(
      'status' => $status ? 'success' : 'error',
      'message' => !$status ? 'Clean Installer File Unsuccessfully.' : "Clean Installer File Successfully",
      'message_detail' => $message_detail . ' ' . $message_exception,
    );

    echo json_encode($dataResp);die;
  }

  public function updateConfig($db_host, $db_user, $db_name, $db_pass) {
    $status = true;
    $wpconfig_install_path = './njt-fastdup-installer/wp-config.origin';
    $wpconfig_path = './wp-config.php';
    if (file_exists($wpconfig_install_path)) {
      $wpconfig_install_content = file_get_contents($wpconfig_install_path);
      if (!file_exists($wpconfig_path)) {
        $created = file_put_contents($wpconfig_path, $wpconfig_install_content);
      } else {
        $created = true;
      }

      if (!$created) {
        $status = false;
        throw new \Exception("Create wp-config.php fail!");
      }

      $transformer = new WPConfigTransformer($wpconfig_path);
      $current_site = Helper::get_instance()->getCurrentSite();
      $constantsData = array(
        "DB_HOST" => $db_host,
        "DB_NAME" => $db_name,
        "DB_USER" => $db_user,
        "DB_PASSWORD" => $db_pass,
        'WP_HOME' => $current_site,
        'WP_SITEURL' => $current_site,
      );

      $required_constants = array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST');
      foreach ($required_constants as $constant) {
        $data = $constantsData[$constant] ? $constantsData[$constant] : "";
        if ($transformer->exists('constant', $constant)) {
          $transformer->update('constant', $constant, $data);
        } else {
          $transformer->add('constant', $constant, $data);
        }
      }

      $optional_constants = array('WP_HOME', 'WP_SITEURL');
      foreach ($optional_constants as $constant) {
        $data = $constantsData[$constant] ? $constantsData[$constant] : "";
        if ($transformer->exists('constant', $constant)) {
          $transformer->update('constant', $constant, $data);
        }
      }

      if($transformer->exists('constant', 'WP_PLUGIN_DIR')) {
        $old_value = $transformer->get_value('constant', 'WP_PLUGIN_DIR');
        $old_value = substr($old_value, 1, -1);
        // $old_value = preg_replace('#^.|.$#','',$old_value);
        $old_value = $transformer->normalize_path($old_value);

        $new_value = $transformer->normalize_path(dirname(__FILE__));
        $new_value = str_replace( Helper::get_instance()->getOldPath(), $new_value, $old_value);
        $transformer->update('constant', 'WP_PLUGIN_DIR', $new_value);
      }
      if($transformer->exists('constant', 'WP_PLUGIN_URL')) {
        $old_value = $transformer->get_value('constant', 'WP_PLUGIN_URL');
        $old_value = substr($old_value, 1, -1);

        $new_site = rtrim($current_site, '/');

        $new_value = str_replace(Helper::get_instance()->getOldSiteUrl(), $new_site, $old_value);

        $transformer->update('constant', 'WP_PLUGIN_URL', $new_value);
      }
    }

    return $status;
  }

  public function importDatabase($db_host, $db_user, $db_name, $db_pass) {
    if(!file_exists("./njt-fastdup-installer/database.sql")) {
      return 'njt-fastdup-installer/database.sql Not Found';
    }
    $mysqlImport = new MySQLImporter($db_host, $db_user, $db_pass, false);
    $mysqlImport->doImport("./njt-fastdup-installer/database.sql", $db_name, true, true);

    if ($mysqlImport->hadErrors) {
      $errors = json_encode($mysqlImport->errors, true);
      throw new \Exception($errors);
      return 'Could not import database.';
    } else {
      return true;
    }
  }

  public function updateHtaccess() {
    $status = true;
    $htaccess_install_path = './njt-fastdup-installer/htaccess.origin';
    $htaccess_path = './.htaccess';
    if (file_exists($htaccess_install_path)) {
      $htaccess_install_content = file_get_contents($htaccess_install_path);
      $htaccess_origin = file_put_contents('./.htaccess.origin', $htaccess_install_content);
    }

    if (file_exists($htaccess_path)) {
      unlink($htaccess_path);
    }

    $new_path = $_SERVER['REQUEST_URI'];
    $new_path = str_replace(Helper::get_instance()->getInstallerFileName(), "", $new_path);
    $htaccess_content = "# BEGIN WordPress
    <IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase {$new_path}
    RewriteRule ^index\.php$ - [L]
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule . {$new_path}index.php [L]
    </IfModule>
    # END WordPress";
    $status = file_put_contents($htaccess_path, $htaccess_content);

    return $status;
  }

  public function updateRobots() {
    $status = true;
    $robots_install_path = './njt-fastdup-installer/robots.origin';
    $robots_path = './robots.txt';
    if (file_exists($robots_install_path)) {
      if (file_exists($robots_path)) {
        unlink($robots_path);
      }
      $robots_install_content = file_get_contents($robots_install_path);

      if (!file_exists($robots_path)) {
        $robots_path = './.robots.origin';
        $status = file_put_contents($robots_path, $robots_install_content);
      } else {
        $status = true;
      }
    }

    return $status;
  }

  public function php_version_compare() {
    return version_compare(PHP_VERSION, '5.3.0', '>=');
  }

  public function disableExecutionTimeLimit() {
    if (function_exists('set_time_limit')) {
      if (set_time_limit(0)) {
        return true;
      }
    }
    if (function_exists('ini_set')) {
      if (ini_set('max_execution_time', 0)) {
        return true;
      }
    }
    return false;
  }

  public function system_scan() {
    global $wp_version;

    $web_server_value = strtolower(explode('/', $_SERVER['SERVER_SOFTWARE'])[0]);
    $web_server = array(
      'name' => 'Web Server',
      'value' => explode('/', $_SERVER['SERVER_SOFTWARE'])[0],
      'note' => 'Supported web servers: Nginx, Apache, LiteSpeed, Lighttpd, IIS, uWSGI ,WebServerX',
      'status' => in_array($web_server_value, array('apache', 'litespeed', 'nginx', 'lighttpd', 'iis', 'microsoft-iis', 'webserverx', 'uwsgi')),
    );

    $open_basedir = array(
      'name' => 'Open BaseDir',
      'value' => ini_get("open_basedir") == '' ? 'enabled' : 'unenabled',
      // 'status' => ini_get("open_basedir") == '' ? true : false,
        'status' => true,//always true, because if it's false, it breaks the installation
        'warning' => ini_get("open_basedir") == '' ? false : true,
      'note' => 'If this [open_basedir] is enabled, it can cause some errors. In that case, please ask your host to disable it in the php.ini file.',
    );
    $php_version = array(
      'name' => 'PHP Version',
      'value' => phpversion(),
      'note' => 'FastDup supports PHP version 5.3.0 or higher.',
      'status' => $this->php_version_compare(),
    );

    $this->disableExecutionTimeLimit();
    $max_exc_value = ini_get("max_execution_time");
    $max_execution_time = array(
      'name' => 'Max Execution Time',
      'value' => $max_exc_value,
      'status' => $max_exc_value == 0 ? true : false,
      'note' => 'If the [max_execution_time] value in the php.ini is too low, errors may occur. It is recommended to set timeout to value of 0.',
    );

    $is_support_zip = class_exists('ZipArchive');
    $zip_archive = array(
      'name' => 'ZipArchive',
      'value' => $is_support_zip ? 'enabled' : 'unenabled',
      'status' => $is_support_zip,
      'note' => 'If ziparchive is not enabled, your source might be not extracted successfully.',
    );

    $permission_folder = array(
      'name' => 'Permission Folder',
      'value' => str_replace('\\', '/', dirname(__FILE__)),
      'status' => is_writeable(str_replace('\\', '/', dirname(__FILE__))),
      'note' => 'This folder needs write permission.',
    );

    $general_require = array($web_server, $open_basedir, $php_version, $max_execution_time, $zip_archive, $permission_folder);
    $general_require_status = array_filter($general_require, function ($item) {return $item['status'];});
    return array(
      'status' => count($general_require_status) == count($general_require) ? true : false,
      'data' => $general_require,
    );
  }

  public function my_folder_delete($path) {
    if (!empty($path) && is_dir($path)) {
      $dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
      $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::CHILD_FIRST);
      foreach ($files as $f) {if (is_file($f)) {unlink($f);} else { $empty_dirs[] = $f;}}if (!empty($empty_dirs)) {foreach ($empty_dirs as $eachDir) {rmdir($eachDir);}}rmdir($path);
    }
  }

  public function install($db_host, $db_user, $db_name, $db_pass, $zip_file_name) {
    $status = true;
    $message_exception = '';
    $message_detail = '';

    try {
      $testDatabaseResult = $this->getTestDatabaseResult($db_host, $db_user, $db_name, $db_pass);
      if (!$testDatabaseResult['status']) {
        $status = false;
        $message_detail = $testDatabaseResult['message'];
      }

      // CHECK IF AN ARCHIVE WAS SELECTED FOR UNZIPPING.
      if ($status && $zip_file_name != '') {
        setcookie("njt-fastdup-zip-name", $zip_file_name, time() + (86400 * 30), "/"); // 86400 = 1 day
      }

      if ($status) {
        $zip_file_path = dirname(__FILE__) . '/' . $zip_file_name;
        $zip_file_path = str_replace('\\', '/', $zip_file_path);
        $file_extract = $this->unzipper->extract($zip_file_path);
        if (!$file_extract) {
          $status = false;
          $message_detail = 'Extract Zip Unsuccessfully!';
        }
      }

      $package_obj = null;
      if ($status) {
        $scan_path = './njt-fastdup-installer/scan_package.json';
        if (file_exists($scan_path)) {
          $package_obj = json_decode(file_get_contents($scan_path));
        }
      }

      if ($status && null != $package_obj && isset($package_obj->njt_fastdup_dir_plugin)) {
        //Delete really-simple-ssl
        $ssl_plugin_path = './'  . $package_obj->njt_fastdup_dir_plugin .  '/really-simple-ssl';
        $whitelist = ['127.0.0.1', '::1'];
        if (file_exists($ssl_plugin_path)) {
          if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
            $this->my_folder_delete($ssl_plugin_path);
          }
        }
      }

      if ($status && null != $package_obj && isset($package_obj->njt_fastdup_archive_name)) {
        // Delete folder plugin fastdup
        $fastdup_dir =  './' . $package_obj->njt_fastdup_archive_name ;
        if (file_exists($fastdup_dir)) {
          $this->my_folder_delete($fastdup_dir);
        }
      }

      // Update wp-config.php
      if ($status) {
        $is_config = $this->updateConfig($db_host, $db_user, $db_name, $db_pass);
        if (!$is_config) {
          $status = false;
          $message_detail = 'Update wp-config.php Unsuccessfully!';
        }
      }

      // Update .htaccess
      if ($status) {
        $is_htaccess = $this->updateHtaccess();
        if (!$is_htaccess) {
          $status = false;
          $message_detail = 'Update .htaccess Unsuccessfully! ';
        }
      }

      // Update Robots.txt
      if ($status) {
        $is_robots = $this->updateRobots();
        if (!$is_robots) {
          $status = false;
          $message_detail = 'Update robots.txt Unsuccessfully! ';
        }
      }

      // IMPORT DATABASE
      if ($status) {
        $imported = $this->importDatabase($db_host, $db_user, $db_name, $db_pass);
        if ($imported !== true) {
          $status = false;
          $message_detail = $imported;
        }
      }

      if ($status) {
        $this->cleanUnzipDir();
      }

    } catch (\Exception $e) {
      $message_exception = $e->getMessage();
      $status = false;
    }

    $dataResp = array(
      'status' => $status ? 'success' : 'error',
      'message' => $status ? 'Installation Successfully!' : 'Installation Unsuccessfully!',
      'message_detail' => $message_detail . ' ' . $message_exception,
    );
    echo json_encode($dataResp);die;
  }
  
  public function getTestDatabaseResult($db_host, $db_user, $db_name, $db_pass) {
    $mysqlImport = new MySQLImporter($db_host, $db_user, $db_pass, false);
    if ($mysqlImport->hadErrors) {
      return array(
        'status' => false,
        'message' => 'Test Database Unsuccessfully! Database User Or Password Incorrect',
        //'isDatabaseNameExist' => false,
      );
    }

    $isDatabaseNameExist = $mysqlImport->conn->select_db($db_name);

    if (!$isDatabaseNameExist) {
      return array(
        'status' => false,
        'message' => 'Test Database Unsuccessfully! Database Name Incorrect',
        // 'isDatabaseNameExist' => $isDatabaseNameExist,
      );
    }
    
    return array(
      'status' => true,
      'message' => 'Test Database Successfully!',
      // 'isDatabaseNameExist' => $isDatabaseNameExist,
    );
  }

  public function testDatabase($db_host, $db_user, $db_name, $db_pass) {
    $dataResp = $this->getTestDatabaseResult($db_host, $db_user, $db_name, $db_pass);

    echo json_encode($dataResp);die;
  }
}

//Install Object Init
$install_package = new Installer_Package();
$system_scan = $install_package->system_scan();
$dataForm = file_get_contents('php://input');
$dataForm = json_decode($dataForm, true);

//Clean Installer File
if (isset($dataForm) && isset($dataForm['type'])) {
  if ($dataForm['type'] == 'clean_install_file') {
    $install_package->cleanInstallFile();
  }

  if ($dataForm['type'] == 'test_database') {
    $install_package->testDatabase($dataForm['db_host'], $dataForm['db_user'], $dataForm['db_name'], $dataForm['db_pass']);
  }
}
//Init Source
if (isset($dataForm) && count($dataForm) > 0 && !isset($dataForm['type'])) {
  $zip_file_path = isset($dataForm['zipfile']) ? strip_tags($dataForm['zipfile']) : '';
  $install_package->install($dataForm['db_host'], $dataForm['db_user'], $dataForm['db_name'], $dataForm['db_pass'], $zip_file_path);
}
?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>FastDup Installer</title>
    <style>
      :root{--primary:#1890ff;--success:#52c41a;--danger:#ef5350}@-webkit-keyframes loading{to{-webkit-transform:rotate(1turn);transform:rotate(1turn)}}@keyframes loading{to{-webkit-transform:rotate(1turn);transform:rotate(1turn)}}@-webkit-keyframes slideInDown{0%{opacity:0;-webkit-transform:scaleY(.8);transform:scaleY(.8);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:1;-webkit-transform:scaleY(1);transform:scaleY(1);-webkit-transform-origin:0 0;transform-origin:0 0}}@keyframes slideInDown{0%{opacity:0;-webkit-transform:scaleY(.8);transform:scaleY(.8);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:1;-webkit-transform:scaleY(1);transform:scaleY(1);-webkit-transform-origin:0 0;transform-origin:0 0}}@-webkit-keyframes slideOutDown{0%{opacity:1;-webkit-transform:scaleY(1);transform:scaleY(1);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:0;-webkit-transform:scaleY(.8);transform:scaleY(.8);-webkit-transform-origin:0 0;transform-origin:0 0}}@keyframes slideOutDown{0%{opacity:1;-webkit-transform:scaleY(1);transform:scaleY(1);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:0;-webkit-transform:scaleY(.8);transform:scaleY(.8);-webkit-transform-origin:0 0;transform-origin:0 0}}@-webkit-keyframes fadeIn{0%{opacity:0}}@keyframes fadeIn{0%{opacity:0}}@-webkit-keyframes MoveUpIn{0%{opacity:0;-webkit-transform:translateY(-100%);transform:translateY(-100%);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:1;-webkit-transform:translateY(0);transform:translateY(0);-webkit-transform-origin:0 0;transform-origin:0 0}}@keyframes MoveUpIn{0%{opacity:0;-webkit-transform:translateY(-100%);transform:translateY(-100%);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:1;-webkit-transform:translateY(0);transform:translateY(0);-webkit-transform-origin:0 0;transform-origin:0 0}}@-webkit-keyframes MoveUpOut{0%{opacity:1;-webkit-transform:translateY(0);transform:translateY(0);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:0;-webkit-transform:translateY(-100%);transform:translateY(-100%);-webkit-transform-origin:0 0;transform-origin:0 0}}@keyframes MoveUpOut{0%{opacity:1;-webkit-transform:translateY(0);transform:translateY(0);-webkit-transform-origin:0 0;transform-origin:0 0}to{opacity:0;-webkit-transform:translateY(-100%);transform:translateY(-100%);-webkit-transform-origin:0 0;transform-origin:0 0}}*{outline:none!important}html{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;font-family:sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}body{margin:0}article,aside,footer,header,nav,section{display:block}h1{font-size:2em;margin:.67em 0}figcaption,figure,main{display:block}hr{-webkit-box-sizing:content-box;box-sizing:content-box;height:0;overflow:visible}a{background-color:rgba(0,0,0,0);-webkit-text-decoration-skip:objects}a:active,a:hover{outline-width:0}address{font-style:normal}b,strong{font-weight:inherit;font-weight:bolder}code,kbd,pre,samp{font-family:SF Mono,Segoe UI Mono,Roboto Mono,Menlo,Courier,monospace;font-size:1em}dfn{font-style:italic}small{font-size:80%;font-weight:400}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}audio,video{display:inline-block}audio:not([controls]){display:none;height:0}img{border-style:none}svg:not(:root){overflow:hidden}button,input,optgroup,select,textarea{font-family:inherit;font-size:inherit;line-height:inherit;margin:0}button,input{overflow:visible}button,select{text-transform:none}[type=reset],[type=submit],button,html [type=button]{-webkit-appearance:button}[type=button]::-moz-focus-inner,[type=reset]::-moz-focus-inner,[type=submit]::-moz-focus-inner,button::-moz-focus-inner{border-style:none;padding:0}fieldset{border:0;margin:0;padding:0}legend{-webkit-box-sizing:border-box;box-sizing:border-box;color:inherit;display:table;max-width:100%;padding:0;white-space:normal}progress{display:inline-block;vertical-align:baseline}textarea{overflow:auto}[type=checkbox],[type=radio]{-webkit-box-sizing:border-box;box-sizing:border-box;padding:0}[type=number]::-webkit-inner-spin-button,[type=number]::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}[type=search]::-webkit-search-cancel-button,[type=search]::-webkit-search-decoration{-webkit-appearance:none}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}details,menu{display:block}summary{display:list-item;outline:0}canvas{display:inline-block}[hidden],template{display:none}*,:after,:before{-webkit-box-sizing:border-box;box-sizing:border-box}html{-webkit-tap-highlight-color:transparent;font-size:20px;line-height:1.5}body{background:#f1f1f1;color:#3b4351;font-family:-apple-system,system-ui,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,sans-serif;font-size:.8rem;overflow-x:hidden;text-rendering:optimizeLegibility}a{color:#1890ff;outline:0;text-decoration:none}a:focus{-webkit-box-shadow:0 0 0 .1rem rgba(87,85,217,.2);box-shadow:0 0 0 .1rem rgba(87,85,217,.2)}a.active,a:active,a:focus,a:hover{color:#1683e9;text-decoration:underline}a:visited{color:#1683e9}h1,h2,h3,h4,h5,h6{color:inherit;font-weight:500;line-height:1.2;margin-bottom:.5em;margin-top:0}.h1,.h2,.h3,.h4,.h5,.h6{font-weight:500}.h1,h1{font-size:2rem}.h2,h2{font-size:1.6rem}.h3,h3{font-size:1.4rem}.h4,h4{font-size:1.2rem}.h5,h5{font-size:1rem}.h6,h6{font-size:.8rem}p{margin:0 0 1.2rem}a,ins,u{-webkit-text-decoration-skip:ink edges;text-decoration-skip:ink edges}abbr[title]{border-bottom:.05rem dotted;cursor:help;text-decoration:none}kbd{background:#303742;color:#fff;font-size:.7rem;line-height:1.25;padding:.1rem .2rem}kbd,mark{border-radius:.1rem}mark{background:#ffe9b3;border-bottom:.05rem solid #ffd367;color:#3b4351;padding:.05rem .1rem 0}blockquote{border-left:.1rem solid #dadee4;margin-left:0;padding:.4rem .8rem}blockquote p:last-child{margin-bottom:0}ol,ul{padding:0}ol,ol ol,ol ul,ul,ul ol,ul ul{margin:.8rem 0 .8rem .8rem}ol li,ul li{margin-top:.4rem}ul{list-style:disc inside}ul ul{list-style-type:circle}ol{list-style:decimal inside}ol ol{list-style-type:lower-alpha}dl dt{font-weight:700}dl dd{margin:.4rem 0 .8rem}@font-face{font-display:swap;font-family:FastDup;font-style:normal;font-weight:400;src:url("data:application/font-woff;base64,d09GRgABAAAAAAf8AAsAAAAAB7AAAQAAAAAAAAAAAAAAAAAAAAAAAAAAAABPUy8yAAABCAAAAGAAAABgDtsGHGNtYXAAAAFoAAAAVAAAAFQXVtKNZ2FzcAAAAbwAAAAIAAAACAAAABBnbHlmAAABxAAAA9AAAAPQy7rca2hlYWQAAAWUAAAANgAAADYcXpBGaGhlYQAABcwAAAAkAAAAJAfyBAJobXR4AAAF8AAAACwAAAAsIAoDHWxvY2EAAAYcAAAAGAAAABgDIgQkbWF4cAAABjQAAAAgAAAAIAATAFZuYW1lAAAGVAAAAYYAAAGGHs+PgXBvc3QAAAfcAAAAIAAAACAAAwAAAAMDgQGQAAUAAAKZAswAAACPApkCzAAAAesAMwEJAAAAAAAAAAAAAAAAAAAAARAAAAAAAAAAAAAAAAAAAAAAQAAA6QYD9f/2AAoD9QAKAAAAAQAAAAAAAAAAAAAAIAAAAAAAAwAAAAMAAAAcAAEAAwAAABwAAwABAAAAHAAEADgAAAAKAAgAAgACAAEAIOkG//3//wAAAAAAIOkA//3//wAB/+MXBAADAAEAAAAAAAAAAAAAAAEAAf//AA8AAQAAAAAAAAAAAAIAADc5AQAAAAABAAAAAAAAAAAAAgAANzkBAAAAAAEAAAAAAAAAAAACAAA3OQEAAAAAAQCMAQ8DdALxAAkAAAEHFwUXJQcXNycCgj2M/bsBAkKJPfHyAvE8iwFVAYo88vAAAwArACsD1QPVAAYAIwA/AAABJzcXNxcBJRQXHgEXFjMyNz4BNzY1NCcuAScmIyIHDgEHBhUBIicuAScmNTQ3PgE3NjMyFx4BFxYVFAcOAQcGAbW1PHnxPf7S/nYlJIBVVmFhVlWAJCUlJIBVVmFhVlWAJCUB1VBFRmkeHh4eaUZFUFBFRmkeHh4eaUZFAUi1PHjxPP7SuGFWVYAkJSUkgFVWYWFWVYAkJSUkgFVWYf6AHh5pRkVQUEVGaR4eHh5pRkVQUEVGaR4eAAAAAQEPAUsC8QK1AAYAAAEnBxcBJwcBxHk8tQEtPPEBxHg8tQEuPPEAAAEA0gFLAy4CtQAGAAABBwkBJwcnAQ89AS4BLj3x8QK1PP7SAS488fEAAAAABABVAFUDqwOrAA0AGQA2AFMAAAEyFhURFAYjIiY1ETQ2EyIGFRQWMzI2NTQmAzIXHgEXFhUUBw4BBwYjIicuAScmNTQ3PgE3NjMBFBceARcWMzI3PgE3NjU0Jy4BJyYjIgcOAQcGFQIAEhkZEhIZGRISGRkSEhkZElhOTnQhIiIhdE5OWFhOTnQhIiIhdE5OWP6rGhtdPj5HRz4+XRsaGhtdPj5HRz4+XRsaAwAZEv8AERkZEQEAEhn+VRkREhkZEhEZAlYiIXROTlhYTk50ISIiIXROTlhYTk50ISL+VUc+Pl0bGhobXT4+R0c+Pl0bGhobXT4+RwAHACsAgAPVA4AAAwAIAAwAEAAhACwANwAAEzUzFTMhNSEVAxUzNQUhNSEBNDYzITIWFREUBiMhIiY1ETciBh0BITU0JiMhAxUUFjMhMjY9ASHVVlUBq/5Vq1YCAP5VAav9AEs1Aqo1S0s1/VY1S4ASGQMAGRL9VisZEgKqEhn9AAKAVVVVVf8AVVVVVQGANUtLNf4ANUtLNQIAKxkS1dUSGf6q1RIZGRLVAAAABAAFAEAD+wPAAA4AHgAuADIAAAEUFjMyNjUxNCYjIgYVMRMVFBY7ATI2PQE0JisBIgYJAS4BIyIGBwEGFjMhMjYnJQkBIQHJIBcXICAXFyASBgQ2BAYGBDYEBgIg/iUGEQkJEQb+JQsVFgO2FhUL/IEBhAGE/PgBEhYhIRYXICAXAVzTAwYGA9MDBgb+BgM3CQkJCfzJEiUlEiACoP1gAAEAAAABAACNmEM3Xw889QALBAAAAAAA3KKlugAAAADcoqW6AAAAAAP7A9UAAAAIAAIAAAAAAAAAAQAAA/X/9gAABAAAAAAAA/sAAQAAAAAAAAAAAAAAAAAAAAsEAAAAAAAAAAAAAAAACgAABAAAjAQAACsEAAEPBAAA0gQAAFUEAAArBAAABQAAAAAACgAUAB4ANgCcALAAxgFCAZgB6AABAAAACwBUAAcAAAAAAAIAAAAAAAAAAAAAAAAAAAAAAAAADgCuAAEAAAAAAAEABwAAAAEAAAAAAAIABwBgAAEAAAAAAAMABwA2AAEAAAAAAAQABwB1AAEAAAAAAAUACwAVAAEAAAAAAAYABwBLAAEAAAAAAAoAGgCKAAMAAQQJAAEADgAHAAMAAQQJAAIADgBnAAMAAQQJAAMADgA9AAMAAQQJAAQADgB8AAMAAQQJAAUAFgAgAAMAAQQJAAYADgBSAAMAAQQJAAoANACkRmFzdER1cABGAGEAcwB0AEQAdQBwVmVyc2lvbiAxLjAAVgBlAHIAcwBpAG8AbgAgADEALgAwRmFzdER1cABGAGEAcwB0AEQAdQBwRmFzdER1cABGAGEAcwB0AEQAdQBwUmVndWxhcgBSAGUAZwB1AGwAYQByRmFzdER1cABGAGEAcwB0AEQAdQBwRm9udCBnZW5lcmF0ZWQgYnkgSWNvTW9vbi4ARgBvAG4AdAAgAGcAZQBuAGUAcgBhAHQAZQBkACAAYgB5ACAASQBjAG8ATQBvAG8AbgAuAAAAAwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==") format("woff")}[class*=" fdi-"],[class^=fdi-]{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;speak:never;font-family:FastDup!important;font-style:normal;font-variant:normal;font-weight:400;line-height:1;text-transform:none}.fdi-alert-triangle-o:before{content:"\e906"}.fdi-arrow-right:before{content:"\e900"}.fdi-check-o:before{content:"\e901"}.fdi-check:before{content:"\e902"}.fdi-chevron-down:before{content:"\e903"}.fdi-danger:before{content:"\e904"}.fdi-database:before{content:"\e905"}.fdi-loading{-webkit-animation:loading 1s linear infinite;animation:loading 1s linear infinite;border:1px solid rgba(0,0,0,0);border-radius:1em;border-right-color:currentcolor;height:1em;width:1em}.color-success{color:var(--success)}.color-danger{color:var(--danger)}.btn{-webkit-box-align:center;-ms-flex-align:center;align-items:center;background:#ededf5;border:0;border-radius:2px;cursor:pointer;display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex;line-height:1.2;padding:8px 20px;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s}.btn:hover{opacity:.7}.btn:active{background:#e0e0e4;opacity:1}.btn-primary{background:var(--primary);color:#fff}.btn-primary:active{background:#137ad8}.btn i{font-size:16px}.btn-icon-left i{margin-right:5px}.btn-icon-right i{margin-left:5px}.btn.disabled{cursor:not-allowed;opacity:.7}.app-form{margin-bottom:20px;position:relative}.form-label{display:block;margin-bottom:10px}.form-label:after{content:":"}.form-field{border:1px solid #d1d5db;border-radius:2px;display:block;font-size:14px;line-height:1.2;padding:7px 15px;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s;width:100%}.form-field:focus,.form-field:hover{border-color:var(--primary)}.form-field:focus{-webkit-box-shadow:0 0 0 2px rgba(24,144,255,.2);box-shadow:0 0 0 2px rgba(24,144,255,.2)}.form-field.disabled,.form-field[disabled]{border-color:#d1d5db;cursor:not-allowed}.form-require-field{-webkit-animation:fadeIn .5s ease;animation:fadeIn .5s ease;display:none;font-size:11px;left:0;margin-top:2px;position:absolute;top:100%}.form-checkbox{display:inline-block;margin:10px 0;position:relative}.form-checkbox .checkbox-wrap{-webkit-box-align:center;-ms-flex-align:center;align-items:center;cursor:pointer;display:-webkit-box;display:-ms-flexbox;display:flex;line-height:1.2}.form-checkbox input{left:0;opacity:0;position:absolute;top:0}.form-checkbox .checkbox-style{display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex}.form-checkbox .checkbox-style:hover{border-color:var(--primary)}.form-checkbox .checkbox-inner{border:1px solid #d1d5db;border-radius:2px;display:inline-block;height:16px;position:relative;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s;width:16px}.form-checkbox .checkbox-inner:after{border:2px solid #fff;border-left:0;border-radius:1px;border-top:0;content:"";display:block;height:8px;left:4px;position:absolute;top:2px;-webkit-transform:rotate(45deg);-ms-transform:rotate(45deg);transform:rotate(45deg);-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s;width:6px}.form-checkbox .checkbox-label{padding:0 6px}.form-checkbox input:checked+.checkbox-inner{background:var(--primary);border-color:var(--primary)}.form-select{position:relative}.form-select .form-field{cursor:pointer}.form-select-dropdown{-webkit-animation-duration:.2s;animation-duration:.2s;-webkit-animation-fill-mode:both;animation-fill-mode:both;-webkit-animation-play-state:paused;animation-play-state:paused;background:#fff;border-radius:2px;-webkit-box-shadow:0 2px 10px 0 rgba(51,51,51,.1);box-shadow:0 2px 10px 0 rgba(51,51,51,.1);display:none;left:0;margin-top:5px;min-width:100%;position:absolute;top:100%;z-index:100}.form-select-dropdown>ul{list-style:none;margin:0;padding:10px 0;-webkit-transform:translateZ(0);transform:translateZ(0)}.form-select-dropdown>ul>li{cursor:pointer;margin-top:5px;padding:6px 15px;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s}.form-select-dropdown>ul>li:first-child{margin-top:0}.form-select-dropdown>ul>li.active{background:rgba(24,144,255,.1);font-weight:500}.form-select-dropdown>ul>li:hover{background:rgba(24,144,255,.15)}.form-select-arrow{color:#9a9ea5;display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex;font-size:16px;margin-top:-8px;pointer-events:none;position:absolute;right:10px;top:50%;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s}.dropdown-open .form-select-dropdown{-webkit-animation-name:slideInDown;animation-name:slideInDown;-webkit-animation-play-state:running;animation-play-state:running;display:block}.dropdown-close .form-select-dropdown{-webkit-animation-name:slideOutDown;animation-name:slideOutDown;-webkit-animation-play-state:running;animation-play-state:running;display:block}.dropdown-open .form-select-arrow{-webkit-transform:rotate(180deg);-ms-transform:rotate(180deg);transform:rotate(180deg)}.app-form.error .form-field{border-color:#ef4444}.app-form.error .form-require-field{color:#ef4444;display:block}.app-message{-webkit-animation-duration:.2s;animation-duration:.2s;-webkit-animation-fill-mode:both;animation-fill-mode:both;-webkit-animation-play-state:paused;animation-play-state:paused;display:flex;display:none;left:0;pointer-events:none;position:fixed;right:0;top:20px;z-index:1000}.app-message,.fd-message-content{display:-webkit-box;display:-ms-flexbox}.fd-message-content{-webkit-box-align:center;-ms-flex-align:center;align-items:center;background:#fff;border-radius:2px;-webkit-box-shadow:0 2px 10px 0 rgba(51,51,51,.2);box-shadow:0 2px 10px 0 rgba(51,51,51,.2);display:flex;line-height:1.6;margin:auto;max-width:600px;padding:8px 15px}.fd-message-content i{font-size:16px;margin-right:8px}.app-message.show{-webkit-animation-name:MoveUpIn;animation-name:MoveUpIn;-webkit-animation-play-state:running;animation-play-state:running}.app-message.hide,.app-message.show{display:-webkit-box;display:-ms-flexbox;display:flex}.app-message.hide{-webkit-animation-name:MoveUpOut;animation-name:MoveUpOut;-webkit-animation-play-state:running;animation-play-state:running}.notice{-webkit-box-align:center;-ms-flex-align:center;align-items:center;display:-webkit-box;display:-ms-flexbox;display:flex;font-size:12px;line-height:1.4;margin-top:5px}.notice i{color:#fbbf24;font-size:14px;margin-right:4px}#app,body,html{height:100%}#app{-webkit-box-orient:vertical;-webkit-box-direction:normal;-ms-flex-direction:column;flex-direction:column}#app,.app-header{display:-webkit-box;display:-ms-flexbox;display:flex}.app-header{-webkit-box-align:center;-ms-flex-align:center;-webkit-box-pack:center;-ms-flex-pack:center;align-items:center;background:#001529;color:#fff;justify-content:center;padding:20px 0}.logo{background:#fff;border-radius:50%;display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex;margin-right:10px}.logo svg{height:30px;width:30px}.name{font-size:18px;font-weight:500}.app-content{-webkit-box-flex:1;-ms-flex:1;flex:1;padding:30px}.app-container{-webkit-box-orient:vertical;-webkit-box-direction:normal;display:-webkit-box;display:-ms-flexbox;display:flex;-ms-flex-direction:column;flex-direction:column;height:100%;margin:auto;max-width:100%;width:600px}.app-step{color:rgba(0,0,0,.45);font-size:16px;line-height:1;margin-bottom:30px;pointer-events:none;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}.app-step,.astep{-webkit-box-align:center;-ms-flex-align:center;align-items:center;display:-webkit-box;display:-ms-flexbox;display:flex}.astep{-webkit-box-flex:1;-ms-flex:1;flex:1;margin-right:15px;overflow:hidden;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s}.astep:last-child{-webkit-box-flex:0;-ms-flex:none;flex:none;margin-right:0}.step-name{position:relative}.step-name:after{background:#d6d6d6;content:"";display:inline-block;height:1px;margin:-1px 0 0 15px;position:absolute;top:50%;-webkit-transition:all .3s;-o-transition:all .3s;transition:all .3s;width:200px}.circle-step{-webkit-box-align:center;-ms-flex-align:center;-webkit-box-pack:center;-ms-flex-pack:center;align-items:center;background:#fff;border:1px solid rgba(0,0,0,.25);border-radius:50%;color:rgba(0,0,0,.25);display:-webkit-inline-box;display:-ms-inline-flexbox;display:inline-flex;height:32px;justify-content:center;margin-right:10px;width:32px}.astep i{display:none;font-size:20px}.astep.active{color:rgba(0,0,0,.85);font-weight:500}.astep.active .circle-step{background:var(--primary);border-color:var(--primary);color:#fff}.astep.success .circle-step{border-color:var(--primary);color:var(--primary)}.astep.success .circle-step i{display:unset}.astep.success .circle-step span{display:none}.astep.success .step-name:after{background:var(--primary)}.app-step-content{-webkit-box-flex:1;background:#fff;border-radius:2px;-webkit-box-shadow:0 1px 10px rgba(0,0,0,.05);box-shadow:0 1px 10px rgba(0,0,0,.05);-ms-flex:1;flex:1;padding:30px}.cont-step{-webkit-box-orient:vertical;-webkit-box-direction:normal;display:none;-ms-flex-direction:column;flex-direction:column;height:100%}.cont-step.active{display:-webkit-box;display:-ms-flexbox;display:flex}.cont-step-3{-webkit-box-pack:center;-ms-flex-pack:center;justify-content:center}.title-step{color:#333;font-size:18px;margin-bottom:20px;text-align:center}.list-item{-webkit-box-align:center;-ms-flex-align:center;align-items:center;border-bottom:1px solid #f1f1f1;display:-webkit-box;display:-ms-flexbox;display:flex;-ms-flex-wrap:wrap;flex-wrap:wrap;line-height:1;margin-bottom:20px;padding-bottom:20px}.list-item:last-child{border:0;margin-bottom:0}.list-item i{color:var(--primary);font-size:16px;margin-right:4px}.list-item span{margin-left:8px}.note-item{color:#9ca3af;line-height:1.5;margin-top:8px;width:100%;word-break:break-word}.list-item.success i{color:var(--success)}.list-item.danger i{color:var(--danger)}.app-content-footer{-webkit-box-align:center;-ms-flex-align:center;-webkit-box-pack:justify;-ms-flex-pack:justify;align-items:center;border-top:1px solid #f1f1f1;display:-webkit-box;display:-ms-flexbox;display:flex;-ms-flex-wrap:wrap;flex-wrap:wrap;justify-content:space-between;margin-top:auto;padding-top:30px}.footer-left{margin-right:20px}.footer-right{-webkit-box-align:center;-ms-flex-align:center;align-items:center;display:-webkit-box;display:-ms-flexbox;display:flex;margin-left:auto}.footer-right>:not(:first-child){margin-left:10px}.wrap-paragraph{margin-bottom:10px;text-align:center}.cont-step-success,.cont-step-success .title-step{color:var(--success)}.cont-step-icon{font-size:60px;text-align:center}.setting-form{margin-bottom:50px}
      #testDb .fdi-loading {
        display: none;
      }
      #testDb.loading .fdi-loading {
        display: inline-block;
      }
    </style>
  </head>
  <body>
    <div id="app">
      <div class="app-header">
        <span class="logo"><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 20 20"><g><path fill="#001529" d="M15.6,6.9h-4.7L15.2,0H9.7L4.5,10.4h4.1L4.5,20L15.6,6.9z"/><path fill="#001529" d="M15.6,6.9h-4.7L15.2,0H9.7L4.5,10.4h4.1L4.5,20L15.6,6.9z"/><path fill="#001529" d="M17.4,6.9h-4.7L17,0h-4.4L6.3,10.4h2.2l-4,9.7L17.4,6.9z"/></g></svg></span>
        <span class="name">FastDup Installer</span>
      </div>
      <div class="app-content">
        <div class="app-container">
          <div class="app-step"><div class="astep active astep-1"><span class="circle-step"><span>1</span><i class="fdi-check"></i></span><span class="step-name">Requirements</span></div><div class="astep astep-2"><span class="circle-step"><span>2</span><i class="fdi-check"></i></span><span class="step-name">Settings</span></div><div class="astep astep-3"><span class="circle-step"><span>3</span></span>Done</div></div>
          <div class="app-step-content">
            <div class="cont-step active cont-step-1">
              <h3 class="title-step">General Requirements</h3>
              <div id="requireList" class="app-list"></div>
              <div class="app-content-footer">
                <div class="footer-right">
                  <button class="btn btn-primary btn-icon-right" id="appNext" data-step="1">
                    <span>Next</span>
                    <i class="fdi-arrow-right"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="cont-step cont-step-2">
              <div class="setting-form">
                <div class="app-form">
                  <label class="form-label" for="db_host">Database Host</label>
                  <input id="db_host" class="form-field require" name="db_host" value="localhost" disabled />
                  <div class="form-require-field">Please fill this field!</div>
                </div>
                <div class="app-form">
                  <label class="form-label" for="db_name">Database Name</label>
                  <input id="db_name" class="form-field require" name="db_name" disabled />
                  <div class="notice"><i class="fdi-alert-triangle-o"></i><strong>Warning:</strong> We will remove all data from this database (if exists) and import new database!</div>
                  <div class="form-require-field">Please fill this field!</div>
                </div>
                <div class="app-form">
                  <label class="form-label" for="db_user">Database User</label>
                  <input id="db_user" class="form-field require" name="db_user" disabled />
                  <div class="form-require-field">Please fill this field!</div>
                </div>
                <div class="app-form">
                  <label class="form-label" for="db_pass">Database Password</label>
                  <input id="db_pass" class="form-field" name="db_pass" disabled />
                </div>
                <div class="app-form">
                  <label class="form-label" for="zipfile">Package File</label>
                  <div class="form-select">
                    <input id="zipfile" class="form-field require" name="zipfile" readonly disabled />
                    <div class="form-select-dropdown">
                      <ul>
                        <?php foreach($install_package->unzipper->zipfiles as $value){ ?>
                        <li class="form-select-item" data-select-value="<?php echo $value; ?>"><?php echo $value; ?></li>
                        <?php } ?>
                      </ul>
                    </div>
                    <div class="form-select-arrow">
                      <i class="fdi-chevron-down"></i>
                    </div>
                  </div>
                  <div class="form-require-field">Please select a package file!</div>
                </div>
              </div>
              <div class="app-content-footer">
                <div class="footer-left">
                  <button class="btn" id="appPrev">
                    <span>Previous</span>
                  </button>
                </div>
                <div class="footer-right">
                  <button class="btn btn-icon-left disabled" id="testDb">
                    <i class="fdi-loading"></i>
                    <i class="fdi-database"></i>
                    <span>Test Database</span>
                  </button>
                  <button class="btn btn-primary btn-icon-right disabled" id="appNext" data-step="2">
                    <span>Next</span>
                    <i class="fdi-arrow-right"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="cont-step cont-step-3">
              <div class="cont-step-success">
                <div class="cont-step-icon">
                  <i class="fdi-check-o"></i>
                </div>
                <h3 class="title-step">Installation Successful</h3>
              </div>
              <div class="wrap-paragraph">
                <button id="admin_login" class="btn btn-primary btn-icon-right disabled">
                  <span>Admin Login</span>
                  <i class="fdi-arrow-right"></i>
                </button>
              </div>
              <div class="wrap-paragraph">
                <div class="form-checkbox">
                  <label class="checkbox-wrap">
                    <span class="checkbox-style">
                      <input name="del_file" type="checkbox" />
                      <span class="checkbox-inner"></span>
                    </span>
                    <span class="checkbox-label">Auto delete installer files after login to secure site</span>
                  </label>
                </div>
              </div>
              <div class="wrap-paragraph"><strong>Note:</strong> Click Admin Login button to login and finalize this install.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <script>
let checkStep1 = !1,
    checkStep2 = !1;

function ready(a) {
    "loading" == document.readyState ? document.addEventListener("DOMContentLoaded", a) : a()
}

function stepChange(a, b) {
    const c = document.querySelector(".app-step"),
        d = document.querySelector(".app-step-content");
    c.querySelector(".astep.active").classList.remove("active"), d.querySelector(".cont-step.active").classList.remove("active"), b ? (c.querySelector(".astep-" + (a - 1)).classList.add("success"), c.querySelector(".astep-" + a).classList.add("active"), d.querySelector(".cont-step-" + a).classList.add("active")) : (c.querySelector(".astep-" + a).classList.remove("success"), c.querySelector(".astep-" + a).classList.add("active"), d.querySelector(".cont-step-" + a).classList.add("active"))
}

function showMessage(a, b, c = 5e3) {
    const d = document.createElement("div"),
        e = document.createElement("div");
    d.classList.add("app-message"), e.classList.add("fd-message-content"), d.appendChild(e), e.innerHTML = `${"success"===b?"<i class=\"fdi-check-o color-success\"></i>":!("error"!=b)&&"<i class=\"fdi-danger color-danger\"></i>"}<span>${a}</span>`, document.body.appendChild(d), d.classList.add("show"), setTimeout(() => {
        d.classList.remove("show"), d.classList.add("hide"), setTimeout(() => {
            d.remove()
        }, 200)
    }, c)
}

function testDatabase() {
    const a = document.querySelector("#db_host"),
        b = document.querySelector("#db_name"),
        c = document.querySelector("#db_user"),
        d = document.querySelector("#db_pass");
    if (a.value && b.value && c.value) {
      document.getElementById('testDb').classList.add('loading')
        const e = new XMLHttpRequest,
            f = JSON.stringify({
                type: "test_database",
                db_host: a.value,
                db_name: b.value,
                db_user: c.value,
                db_pass: d.value
            });
        e.open("POST", window.location.href, !0), e.setRequestHeader("Accept", "application/json"), e.setRequestHeader("Content-Type", "application/json"), e.onload = function(resp) {
            if (200 <= this.status && 400 > this.status) {
              document.getElementById('testDb').classList.remove('loading')
                const a = JSON.parse(this.response);
                a.status ? showMessage(a.message, "success") : showMessage(a.message, "error")
            } else {
              document.getElementById('testDb').classList.remove('loading')
              console.log(resp.target.response)
              console.log(resp)
              showMessage("Error " + this.status + "." + resp.target.response + ". See Console for details.", "error")
            }
        }, e.onerror = function(resp) {
          document.getElementById('testDb').classList.remove('loading')
          console.log(resp.target.response)
          console.log(resp)
              showMessage("Error " + this.status + "." + resp.target.response + ". See Console for details.", "error")
        }, e.send(f)
    } else a.value || a.parentElement.classList.add("error"), b.value || b.parentElement.classList.add("error"), c.value || c.parentElement.classList.add("error")
}

function cleanInstallerFile() {
    const a = new XMLHttpRequest,
        b = JSON.stringify({
            type: "clean_install_file"
        });
    a.open("POST", window.location.href, !0), a.setRequestHeader("Accept", "application/json"), a.setRequestHeader("Content-Type", "application/json"), a.send(b)
}

function redirectToAdmin() {
    const a = document.querySelector("[name=del_file]").checked;
    let b = window.location.href;
    const c = b.split(/[\\/]/).pop();
    a && cleanInstallerFile(), b = b.replace(c, "wp-login.php"), location.replace(b)
}
ready(() => {
    const a = document.querySelectorAll("#appNext"),
        b = document.querySelector("#appPrev"),
        c = document.querySelector("#testDb"),
        d = document.querySelector("#admin_login"),
        e = document.querySelector("#requireList"),
        f = document.querySelectorAll(".form-field"),
        g = document.querySelectorAll(".form-select"),
        h = JSON.parse('<?php echo json_encode($system_scan); ?>'),
        i = JSON.parse('<?php echo json_encode($install_package->unzipper->zipfiles); ?>');
    a.forEach(a => {
        a.onclick = () => {
            if (a.classList.contains("disabled") || a.disabled) return !1;
            switch (a.getAttribute("data-step")) {
                case "1":
                    h.status ? (checkStep1 = !0, stepChange(2, !0), c.classList.remove("disabled"), document.querySelector("#appNext[data-step=\"2\"]").classList.remove("disabled"), f.forEach(a => {
                        a.disabled = !1
                    })) : showMessage("Please check all Requirements", "error");
                    break;
                case "2":
                    const b = document.querySelector("#db_host"),
                        e = document.querySelector("#db_name"),
                        g = document.querySelector("#db_user"),
                        i = document.querySelector("#db_pass"),
                        j = document.querySelector("#zipfile");
                    if (b.value && e.value && g.value && j.value) {
                        a.classList.add("disabled"), a.classList.remove("btn-icon-right"), a.classList.add("btn-icon-left"), a.innerHTML = "<i class=\"fdi-loading\"></i><span>Processing</span>";
                        const c = new XMLHttpRequest,
                            f = JSON.stringify({
                                db_host: b.value,
                                db_name: e.value,
                                db_user: g.value,
                                db_pass: i.value,
                                zipfile: j.value
                            });
                        c.open("POST", window.location.href, !0), c.setRequestHeader("Accept", "application/json"), c.setRequestHeader("Content-Type", "application/json"), c.onload = function(resp) {
                          console.log(resp)
                            if (200 <= this.status && 400 > this.status) {
                                const b = JSON.parse(this.response);
                                "error" == b.status ? (showMessage(b.message + " - " + b.message_detail, "error"), a.classList.remove("disabled"), a.classList.add("btn-icon-right"), a.classList.remove("btn-icon-left"), a.innerHTML = "<span>Next</span><i class=\"fdi-arrow-right\"></i>") : (checkStep2 = !0, stepChange(3, !0), a.classList.remove("disabled"), a.classList.add("btn-icon-right"), a.classList.remove("btn-icon-left"), a.innerHTML = "<span>Next</span><i class=\"fdi-arrow-right\"></i>", d.classList.remove("disabled"))
                            } else {
                              
                              showMessage(resp.message + " - " + resp.message_detail, "error"), a.classList.remove("disabled"), a.classList.add("btn-icon-right"), a.classList.remove("btn-icon-left"), a.innerHTML = "<span>Next</span><i class=\"fdi-arrow-right\"></i>"
                            }
                        }, c.onerror = function(resp) {
                          console.log(resp)
                            showMessage(resp.message + " - " + resp.message_detail, "error"), a.classList.remove("disabled"), a.classList.add("btn-icon-right"), a.classList.remove("btn-icon-left"), a.innerHTML = "<span>Next</span><i class=\"fdi-arrow-right\"></i>"
                        }, c.send(f)
                    } else b.value || b.parentElement.classList.add("error"), e.value || e.parentElement.classList.add("error"), g.value || g.parentElement.classList.add("error"), j.value || j.closest(".app-form").classList.add("error");
                    break;
                default:
            }
        }
    }), b.onclick = () => {
        stepChange(1, !1)
    }, g.forEach(a => {
        const b = a.querySelector(".form-field"),
            c = a.querySelectorAll(".form-select-item");
        b.addEventListener("click", () => {
            a.classList.contains("dropdown-open") ? (a.classList.remove("dropdown-open"), a.classList.add("dropdown-close"), setTimeout(() => {
                a.classList.remove("dropdown-close")
            }, 200)) : a.classList.add("dropdown-open")
        }), c.forEach(c => {
            const d = c.getAttribute("data-select-value");
            c.classList.contains("active") && (b.value = d), c.addEventListener("click", () => {
                a.closest(".app-form").classList.contains("error") && a.closest(".app-form").classList.remove("error"), a.querySelector(".form-select-item.active") && a.querySelector(".form-select-item.active").classList.remove("active");
                const d = c.getAttribute("data-select-value");
                b.value = d, c.classList.add("active"), a.classList.remove("dropdown-open"), a.classList.add("dropdown-close"), setTimeout(() => {
                    a.classList.remove("dropdown-close")
                }, 200)
            })
        })
    }), c.addEventListener("click", () => {
        checkStep1 ? testDatabase() : showMessage("Please check all Requirements", "error")
    }), f.forEach(a => {
        a.classList.contains("require") && a.addEventListener("input", () => {
            a.parentElement.classList.contains("error") && a.parentElement.classList.remove("error")
        })
    }), h.data.forEach(a => {
        const b = `<div class="list-item ${(a.status?(a.warning ? "danger" : "success"):"danger")}"><i class="${a.status?(a.warning ? "fdi-danger" : "fdi-check-o"):"fdi-danger"}"></i><strong>${a.name}</strong>:<span>${a.value}</span>${(a.status&&!a.warning)?"":"<div class=\"note-item\">"+a.note+"</div>"}</div>`;
        e.insertAdjacentHTML("beforeend", b)
    }), d.onclick = () => {
        checkStep1 && checkStep2 ? redirectToAdmin() : showMessage("Please check all Requirements and Set up Database", "error")
    }
});    </script>
  </body>
</html>
