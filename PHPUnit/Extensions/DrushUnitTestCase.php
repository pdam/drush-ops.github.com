<?php


/**
 * Base class for Drush unit tests
 *
 * Those tests will run in a bootstrapped Drush environment
 *
 * This should be ran in separate processes, which the following
 * annotation should do in 3.6 and above:
 *
 * @runTestsInSeparateProcesses
 */
abstract class PHPUnit_Extensions_DrushUnitTestCase extends PHPUnit_Extensions_DrushTestCase {

  /**
   * Minimally bootstrap drush
   *
   * This is equivalent to the level DRUSH_BOOTSTRAP_NONE, as we
   * haven't run drush_bootstrap() yet. To do anything, you'll need to
   * bootstrap to some level using drush_bootstrap().
   *
   * @see drush_bootstrap()
   */
  public static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    require_once(dirname(__FILE__) . '/../includes/bootstrap.inc');
    drush_bootstrap_prepare();
  }

  public static function tearDownAfterClass() {
    parent::tearDownAfterClass();
    drush_bootstrap_finish();
  }

  /*
   * Initialize our environment at the start of each run (i.e. suite).
   */
  static function unish_init() {
    // We read from globals here because env can be empty and ini did not work in quick test.
    define('UNISH_DB_URL', getenv('UNISH_DB_URL') ? getenv('UNISH_DB_URL') : !empty($GLOBALS['UNISH_DB_URL']) ? $GLOBALS['UNISH_DB_URL'] : 'mysql://root:@127.0.0.1');

    // UNISH_DRUSH value can come from phpunit.xml or `which drush`.
    if (!defined('UNISH_DRUSH')) {
      // Let the UNISH_DRUSH environment variable override if set.
      $unish_drush = isset($_SERVER['UNISH_DRUSH']) ? $_SERVER['UNISH_DRUSH'] : NULL;
      $unish_drush = isset($GLOBALS['UNISH_DRUSH']) ? $GLOBALS['UNISH_DRUSH'] : $unish_drush;
      if (empty($unish_drush)) {
        $unish_drush = PHPUnit_Extensions_DrushTestCase::is_windows() ? exec('for %i in (drush) do @echo.   %~$PATH:i') : trim(`which drush`);
      }
      define('UNISH_DRUSH', $unish_drush);
    }

    define('UNISH_TMP', getenv('UNISH_TMP') ? getenv('UNISH_TMP') : (isset($GLOBALS['UNISH_TMP']) ? $GLOBALS['UNISH_TMP'] : sys_get_temp_dir()));
    define('UNISH_SANDBOX', UNISH_TMP . '/drush-sandbox');

    $home = UNISH_SANDBOX . '/home';
    putenv("HOME=$home");
    putenv("HOMEDRIVE=$home");

    putenv('ETC_PREFIX=' . UNISH_SANDBOX);
    putenv('SHARE_PREFIX=' . UNISH_SANDBOX);

    // Cache dir lives outside the sandbox so that we get persistence across classes.
    $cache = UNISH_TMP . '/drush-cache';
    putenv("CACHE_PREFIX=" . $cache);
    // Wipe at beginning of run.
    if (file_exists($cache)) {
      // TODO: We no longer clean up cache dir between runs. Much faster, but we
      // we should watch for subtle problems. To manually clean up, delete the
      // UNISH_TMP/drush-cache directory.
      // unish_file_delete_recursive($cache);
    }
  }

  /**
   * Same code as drush_delete_dir().
   * @see drush_delete_dir()
   *
   * @param string $dir
   * @return boolean
   */
  static function unish_file_delete_recursive($dir) {
    if (!file_exists($dir)) {
      return TRUE;
    }
    if (!is_dir($dir)) {
      @chmod($dir, 0777); // Make file writeable
      return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
      if ($item == '.' || $item == '..') {
        continue;
      }
      if (!unish_file_delete_recursive($dir.'/'.$item)) {
        return FALSE;
      }
    }
    return rmdir($dir);
  }

  // This code is in global scope.
  // TODO: I would rather this code at top of file, but I get Fatal error: Class 'Drush_TestCase' not found
  //self::unish_init();
}
