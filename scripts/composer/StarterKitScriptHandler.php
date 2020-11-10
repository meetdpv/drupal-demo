<?php

namespace DrupalProject\composer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

const BUILDVERSION = "v2.0.0";

/**
 * Class StarterKitScriptHandler.
 *
 * @package DrupalProject\composer
 */
class StarterKitScriptHandler {

  /**
   * @param \Composer\Script\Event $event
   *   The event.
   */
  public static function createLinks(Event $event) {
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
      $event->getIO()
        ->write("Some of the operations don't work best in windows. Follow Readme Document.");
    }
    else {
      $fs = new Filesystem();
      $project_root = getcwd();
      $drupal_root = static::getDrupalRoot($project_root);

      // Custom Directory Mapping.
      $links = [
        // TODO: Convert for Multi-Site using 2 dimensional Array.
        '/custom/feature' => '/modules/feature',
        '/custom/themes' => '/themes/custom',
        '/custom/profiles' => '/profiles/custom',
      ];

      // Create symlink within the docroot for Custom Codes.
      foreach ($links as $src => $dest) {

        $fs->symlink('../..' . $src, $drupal_root . $dest, TRUE);

        $event->getIO()
          ->write($src . " to " . $dest . " Symlink have been created");
      }
    }
  }

  /**
   * @param \Composer\Script\Event $event
   *   The event.
   */
  public static function htaccessUpdate(Event $event) {

    $fs = new Filesystem();
    $project_root = getcwd();
    $drupal_root = static::getDrupalRoot($project_root);

    $custom_htaccess = $project_root . '/custom/htaccess/htaccess-rules.txt';
    $original_htaccess = $drupal_root . '/.htaccess';

    // Prepare the .htaccess file with Custom Rules.
    if ($fs->exists($custom_htaccess) && $fs->exists($original_htaccess)) {

      // Inject Custom .htaccess Rules.
      $htaccess_file_content = "#### Project Customization Starts ####\n";
      $htaccess_file_content .= file_get_contents($custom_htaccess);
      $htaccess_file_content .= "\n#### Project Customization Ends ####\n\n";
      $htaccess_file_content .= file_get_contents($original_htaccess);
      file_put_contents($original_htaccess, $htaccess_file_content);

      $event->getIO()
        ->write(".htaccess updated with custom project Rules.");
    }
  }

  /**
   * @param \Composer\Script\Event $event
   *   The event.
   */
  public static function updateSettings(Event $event) {

    $fs = new Filesystem();
    $project_root = getcwd();
    $drupal_root = static::getDrupalRoot($project_root);

    $settingFilePath = $drupal_root . '/sites/default/settings.php';
    $settingCorePath = $project_root . '/custom/settings/settings.core.php';
    $settingsAcquiaPath = $project_root . '/custom/settings/settings.acquia.php';
    $hashSaltPath = $project_root . '/custom/settings/drupalhash.cfg';

    // TODO: Settings handler to be updated.
    // Update Settings file it exists.
    if ($fs->exists($settingFilePath)) {
      // Bootstrap for settings write.
      require_once $drupal_root . '/core/includes/bootstrap.inc';
      require_once $drupal_root . '/core/includes/install.inc';

      $settingsContent = file_get_contents($settingFilePath);

      // deployment_identifier updated.
      $settingsContent = str_replace("# \$settings['deployment_identifier'] = \Drupal::VERSION;", "\$settings['deployment_identifier'] = '" . trim(BUILDVERSION) . "';", $settingsContent);

      // Add drupal hash salt.
      if ($fs->exists($hashSaltPath)) {

        $hashSalt = file_get_contents($hashSaltPath);

        // Replace hash_salt.
        $settingsContent = str_replace("\$settings['hash_salt'] = '';", "\$settings['hash_salt'] = '" . trim($hashSalt) . "';", $settingsContent);

        // Update Drupal Settings File.
        file_put_contents($settingFilePath, $settingsContent);
        $event->getIO()->write("Salt Hash has been updated in settings file");
      }

      // Prepare the core settings file for installation.
      if ($fs->exists($settingCorePath)) {

        $settingsIncludeContent = "
// Core Config Overrides
if (file_exists(\$app_root . '/' . \$site_path . '/settings.core.php')) {
  include \$app_root . '/' . \$site_path . '/settings.core.php';
}";

        $settings_file = file_put_contents($settingFilePath, $settingsIncludeContent . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($settings_file) {
          $event->getIO()->write("settings.core.php has been included in settings file");
        }
      }

      // Prepare the Acquia settings file for installation.
      if ($fs->exists($settingsAcquiaPath)) {

        $settingsIncludeContent = "
// On Acquia Cloud, this include file configures Drupal to use the correct
// database in each site environment (Dev, Stage, or Prod). To use this
// settings.php for development on your local workstation, set \$db_url
// (Drupal 5 or 6) or \$databases (Drupal 7 or 8) as described in comments above.
if (file_exists('/var/www/site-php')) {
  require('/var/www/site-php/ACQUIA-SUBSCRIPTIONID/ACQUIA-SUBSCRIPTIONID-settings.inc');

  // Configure Memcache (Only in Acquia)
  if (file_exists(\$app_root . '/' . \$site_path . '/cloud-memcache-d8.php')) {
    include \$app_root . '/' . \$site_path . '/cloud-memcache-d8.php';
  }

  // Acquia Environment Specific configs
  if (file_exists(\$app_root . '/' . \$site_path . '/settings.acquia.php')) {
    include \$app_root . '/' . \$site_path . '/settings.acquia.php';
  }
}
";

        $settings_file = file_put_contents($settingFilePath, $settingsIncludeContent . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($settings_file) {
          $event->getIO()->write("settings.acquia.php has been included in settings file");
        }
      }

      // Prepare the local settings file for installation.
      if ($fs->exists($settingsLocalPath)) {

        $settingsIncludeContent = "
// Local configuration (DB & Other config for developers)
if (file_exists(\$app_root . '/' . \$site_path . '/settings.local.php')) {
  include \$app_root . '/' . \$site_path . '/settings.local.php';
}";

        $settings_file = file_put_contents($settingFilePath, $settingsIncludeContent . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($settings_file) {
          $event->getIO()->write("settings.local.php has been included in settings file");
        }
      }

      // Update Settings File Permission as per Drupal Suggestion.
      $fs->chmod($settingFilePath, 0666);
      $event->getIO()->write("Create a sites/default/settings.php file with chmod 0666");
    }
  }

  /**
   * @param string $project_root
   *   The project root directory path.
   * @return string
   *   The drupal root directory path.
   */
  protected static function getDrupalRoot($project_root) {
    return $project_root . '/docroot';
  }

}
