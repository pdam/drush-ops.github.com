<?php

abstract class PHPUnit_Extensions_DrushCommandTestCase extends PHPUnit_Extensions_DrushTestCase {

  // Unix exit codes.
  const EXIT_SUCCESS  = 0;
  const EXIT_ERROR = 1;
  /*
   * An array of Drupal sites that are setup in the drush-sandbox.
   */
  var $sites;

  /**
   * Actually runs the command. Does not trap the error stream output as this
   * need PHP 4.3+.
   *
   * @param string $command
   *   The actual command line to run.
   * @return integer
   *   Exit code. Usually self::EXIT_ERROR or self::EXIT_SUCCESS.
   */
  function execute($command, $expected_return = self::EXIT_SUCCESS) {
    $this->_output = FALSE;
    $this->log("Executing: $command", 'notice');
    exec($command, $this->_output, $return);
    $this->assertEquals($expected_return, $return, 'Unexpected exit code: ' .  $command);
    return $return;
  }

  /**
   * Invoke drush in via execute().
   *
   * @param command
    *   A defined drush command such as 'cron', 'status' or any of the available ones such as 'drush pm'.
    * @param args
    *   Command arguments.
    * @param $options
    *   An associative array containing options.
    * @param $site_specification
    *   A site alias or site specification. Include the '@' at start of a site alias.
    * @param $cd
    *   A directory to change into before executing.
    * @return integer
    *   An exit code.
    */
  function drush($command, array $args = array(), array $options = array(), $site_specification = NULL, $cd = NULL) {
    $cmd[] = $cd ? sprintf('cd %s;', self::escapeshellarg($cd)) : NULL;
    $cmd[] = UNISH_DRUSH;
    $cmd[] = empty($site_specification) ? NULL : self::escapeshellarg($site_specification);
    $cmd[] = $command;
    if ($level = $this->log_level()) {
      $args[] = '--' . $level;
    }

    foreach ($args as $arg) {
      $cmd[] = self::escapeshellarg($arg);
    }
    $options['nocolor'] = NULL;
    foreach ($options as $key => $value) {
      if (is_null($value)) {
        $cmd[] = "--$key";
      }
      else {
        $cmd[] = "--$key=" . self::escapeshellarg($value);
      }
    }
    $exec = array_filter($cmd, 'strlen'); // Remove NULLs
    return $this->execute(implode(' ', $exec));
  }

  // Copied from D7 - profiles/standard/standard.install
  function create_node_types_php() {
    $php = "
      \$types = array(
        array(
          'type' => 'page',
          'name' => 'Basic page',
          'base' => 'node_content',
          'description' => 'Use <em>basic pages</em> for your static content, such as an \'About us\' page.',
          'custom' => 1,
          'modified' => 1,
          'locked' => 0,
        ),
        array(
          'type' => 'article',
          'name' => 'Article',
          'base' => 'node_content',
          'description' => 'Use <em>articles</em> for time-sensitive content like news, press releases or blog posts.',
          'custom' => 1,
          'modified' => 1,
          'locked' => 0,
        ),
      );

      foreach (\$types as \$type) {
        \$type = node_type_set_defaults(\$type);
        node_type_save(\$type);
        node_add_body_field(\$type);
      }
    ";
    return $php;
  }

  /*
   * Prepare the contents of an aliases file.
   */
  function file_aliases($aliases) {
    foreach ($aliases as $name => $alias) {
      $records[] = sprintf('$aliases[\'%s\'] = %s;', $name, var_export($alias, TRUE));
    }
    $contents = "<?php\n\n" . implode("\n\n", $records);
    return $contents;
  }
}
