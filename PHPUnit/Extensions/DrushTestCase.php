<?php

/*
 * @file
 *   Initialize a sandboxed environment. Starts with call unish_init() at bottom.
 */

abstract class PHPUnit_Extensions_DrushTestCase extends PHPUnit_Framework_TestCase {

  function __construct() {
    $this->_output = false;
  }

  /**
   * Assure that each class starts with an empty sandbox directory and
   * a clean environment - http://drupal.org/node/1103568.
   */
  public static function setUpBeforeClass() {
    $sandbox = UNISH_SANDBOX;
    if (file_exists($sandbox)) {
      unish_file_delete_recursive($sandbox);
    }
    $ret = mkdir($sandbox, 0777, TRUE);
    chdir(UNISH_SANDBOX);

    mkdir(getenv('HOME') . '/.drush', 0777, TRUE);
    mkdir($sandbox . '/etc/drush', 0777, TRUE);
    mkdir($sandbox . '/share/drush/commands', 0777, TRUE);
  }

  /**
   * Runs after each test case. Remove sandbox directory.
   */
  public static function tearDownAfterClass() {
    if (file_exists(UNISH_SANDBOX)) {
      unish_file_delete_recursive(UNISH_SANDBOX);
    }
  }

  /*
   *Print a log message to the console.
   *
   * @param string $message
   * @param string $type
   *   Supported types are:
   *     - notice
   *     - verbose
   *     - debug
   */
  function log($message, $type = 'notice') {
    $line = "\nLog: $message\n";
    switch ($this->log_level()) {
      case 'verbose':
        if (in_array($type, array('notice', 'verbose'))) print $line;
        break;
      case 'debug':
        print $line;
        break;
      default:
        if ($type == 'notice') print $line;
        break;
    }
  }

  function log_level() {
    if (in_array('--debug', $_SERVER['argv'])) {
      return 'debug';
    }
    elseif (in_array('--verbose', $_SERVER['argv'])) {
      return 'verbose';
    }
  }

  public static function is_windows() {
    return (strtoupper(substr(PHP_OS, 0, 3)) == "WIN");
  }

  public static function escapeshellarg($arg) {
    // Short-circuit escaping for simple params (keep stuff readable)
    if (preg_match('|^[a-zA-Z0-9.:/_-]*$|', $arg)) {
      return $arg;
    }
    elseif (self::is_windows()) {
      return self::_escapeshellarg_windows($arg);
    }
    else {
      return escapeshellarg($arg);
    }
  }

  public static function _escapeshellarg_windows($arg) {
    // Double up existing backslashes
    $arg = preg_replace('/\\\/', '\\\\\\\\', $arg);

    // Escape double quotes.
    $arg = preg_replace('/"/', '\\"', $arg);

    // Escape single quotes.
    $arg = preg_replace('/\'/', '\\\'', $arg);

    // Add surrounding quotes.
    $arg = '"' . $arg . '"';

    return $arg;
  }

  /**
   *    Accessor for the last output.
   *    @return string        Output as text.
   *    @access public
   */
  function getOutput() {
    return implode("\n", $this->_output);
  }

  /**
   *    Accessor for the last output.
   *    @return array         Output as array of lines.
   *    @access public
   */
  function getOutputAsList() {
    return $this->_output;
  }

  function webroot() {
    return UNISH_SANDBOX . '/web';
  }

  function directory_cache($subdir = '') {
    return getenv('CACHE_PREFIX') . '/' . $subdir;
  }

  function setUpDrupal($num_sites = 1, $install = FALSE, $version_string = '7', $profile = NULL) {
    $sites_subdirs_all = array('dev', 'stage', 'prod', 'retired', 'elderly', 'dead', 'dust');
    $sites_subdirs = array_slice($sites_subdirs_all, 0, $num_sites);
    $root = $this->webroot();

    if (is_null($profile)) {
      $profile = substr($version_string, 0, 1) >= 7 ? 'testing' : 'default';
    }
    $cache_keys = array($num_sites, $install ? 'install' : 'noinstall', $version_string, $profile);
    $source = $this->directory_cache('environments') . '/' . implode('-', $cache_keys) . '.tar.gz';
    if (file_exists($source)) {
      $this->log('Cache HIT. Environment: ' . $source, 'verbose');
      $this->drush('archive-restore', array($source), array('destination' => $root, 'overwrite' => NULL));
    }
    else {
      $this->log('Cache MISS. Environment: ' . $source, 'verbose');
      // Build the site(s), install (if needed), then cache.
      foreach ($sites_subdirs as $subdir) {
        $this->fetchInstallDrupal($subdir, $install, $version_string, $profile);
      }
      $options = array(
        'destination' => $source,
        'root' => $root,
        'uri' => reset($sites_subdirs),
        'overwrite' => NULL,
      );
      $this->drush('archive-dump', array('@sites'), $options);
    }

    // Stash details about each site.
    foreach ($sites_subdirs as $subdir) {
      $this->sites[$subdir] = array(
        'db_url' => UNISH_DB_URL . '/unish_' . $subdir,
      );
      // Make an alias for the site
      $alias_definition = array($subdir => array('root' => $root,  'uri' => $subdir));
      file_put_contents(UNISH_SANDBOX . '/etc/drush/' . $subdir . '.alias.drushrc.php', $this->file_aliases($alias_definition));
    }
    return $this->sites;
  }

  function fetchInstallDrupal($env = 'dev', $install = FALSE, $version_string = '7', $profile = NULL) {
    $root = $this->webroot();
    $site = "$root/sites/$env";

    // Download Drupal if not already present.
    if (!file_exists($root)) {
      $options = array(
        'destination' => UNISH_SANDBOX,
        'drupal-project-rename' => 'web',
        'yes' => NULL,
        'quiet' => NULL,
        'cache' => NULL,
      );
      $this->drush('pm-download', array("drupal-$version_string"), $options);
    }

    // If specified, install Drupal as a multi-site.
    if ($install) {
      $options = array(
        'root' => $root,
        'db-url' => UNISH_DB_URL . '/unish_' . $env,
        'sites-subdir' => $env,
        'yes' => NULL,
        'quiet' => NULL,
      );
      $this->drush('site-install', array($profile), $options);
      // Give us our write perms back.
      chmod($site, 0777);
    }
    else {
      mkdir($site);
      touch("$site/settings.php");
    }
  }
}

