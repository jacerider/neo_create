<?php

declare(strict_types=1);

namespace Drupal\neo_create\Commands;

use Drush\Commands\DrushCommands as CoreCommands;
use Drush\Drush;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Drush commands for Neo init.
 */
class DrushCommands extends CoreCommands {

  /**
   * The doc root.
   *
   * @var string
   */
  protected $docRoot;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    private readonly string $appRoot,
  ) {
    parent::__construct();
  }

  /**
   * Perform neo initial configuration and module setup.
   *
   * @command neo:create
   * @usage drush neo:create
   *   Run the Neo creation.
   * @aliases neo-create
   *
   * @throws \Exception
   *   If no index or no server were passed or passed values are invalid.
   */
  public function neoCreate() {
    $fileSystem = new Filesystem();
    $debug = FALSE;
    $composerRequire = [
      'drupal/devel',
      'kint-php/kint',
      'drupal/pathauto',
      'drupal/focal_point',
      'drupal/allowed_formats',
      'drupal/hide_revision_field',
      'drupal/disable_user_1_edit',
      'drupal/pantheon_advanced_page_cache',
      'drupal/reroute_email',
      'drupal/role_delegation',
      'drupal/menu_admin_per_menu',
      'drupal/linkit',
      'drupal/mailsystem',
      'drupal/google_tag',
      'drupal/metatag',
      'drupal/schema_metatag',
      'jacerider/valet',
      'jacerider/neo',
      'jacerider/neo_theme',
      'jacerider/neo_loader',
      'jacerider/neo_image',
      'jacerider/neo_font',
      'jacerider/neo_favicon',
      'jacerider/neo_config_flow',
      'jacerider/neo_site_settings',
      'jacerider/neo_toolbar',
      'jacerider/neo_form',
      'jacerider/neo_alchemist',
      'jacerider/neo_animate',
    ];
    $moduleInstall = [
      'devel',
      'pathauto',
      'focal_point',
      'allowed_formats',
      'hide_revision_field',
      'disable_user_1_edit',
      'pantheon_advanced_page_cache',
      'reroute_email',
      'role_delegation',
      'menu_admin_per_menu',
      'linkit',
      'mailsystem',
      'google_tag',
      'valet',
      'neo',
      'neo_icon',
      'neo_icon_admin',
      'neo_icon_local_task',
      'neo_menu_link',
      'neo_metatag',
      'neo_modal',
      'neo_loader',
      'neo_image',
      'neo_font',
      'neo_twig',
      'neo_favicon',
      'neo_config_flow',
      'neo_site_settings',
      'neo_toolbar',
      'neo_alchemist',
    ];
    $themeInstall = [
      'neo_base',
      'neo_front',
      'neo_back',
      'front',
      'back',
    ];
    $themeClone = [
      'front',
      'back',
    ];

    $commandPrefix = 'ddev exec ';
    if (getenv('DDEV_PROJECT')) {
      $commandPrefix = '';
    }

    // Phase 1 Commands.
    $color = $this->io()->ask('What is your primary HEX color? (Default: #2780e3)', '#2780e3');
    $installGrumphp = $this->io()->confirm(
      'Install PHP code standards tooling (GrumPHP)?',
      TRUE
    );
    $installClaudeHook = $this->io()->confirm(
      'Set up the personal Claude Code phpcs hook?',
      TRUE
    );
    $commands = [];
    $commands['Setting minimum-stability to dev.'] = 'composer config minimum-stability dev';
    $commands['Installing modules and themes.'] = 'composer require ' . implode(' ', $composerRequire);
    $commands['Configuring VScode for Drupal. '] = 'composer config --json --merge extra.installer-paths \'{".vscode/extensions/{$name}": ["type:vscode-extension"]}\' && composer config --json --merge extra.installer-types \'["vscode-extension"]\' && composer config scripts.vscode-setup "VscodeDrupal\\Install::postPackageInstall" && composer require --dev jacerider/vscode-neo && composer vscode-setup -- --color=' . $color;
    if ($installGrumphp) {
      $commands['Installing GrumpPHP.'] = 'composer require --dev jacerider/grumphp-drupal';
    }
    foreach ($commands as $message => $command) {
      $this->io->info($message);
      $shell = Drush::shell($commandPrefix . $command, $this->getRoot());
      $shell->run(function ($type, $buffer) use ($debug) {
        if ($debug) {
          $this->output()->writeln('-- ' . $buffer);
        }
      });
    }

    foreach ($themeClone as $theme) {
      try {
        $to = $this->appRoot . '/themes/' . $theme;
        $from = $this->appRoot . '/themes/contrib/neo_theme/neo_base/install/neo/' . $theme;
        if (!$fileSystem->exists($to) && $fileSystem->exists($from)) {
          $this->io->info('Creating "' . $theme . '" theme.');
          $fileSystem->mirror($from, $to);
          $fileSystem->rename($to . '/' . $theme . '.info.neo.yml', $to . '/' . $theme . '.info.yml');
        }
      }
      catch (\Error $e) {
        $this->io->error('<error>' . $e->getMessage() . '</error>');
      }
    }

    // Phase 2 Commands.
    $commands = [];
    $commands['Enabling modules.'] = 'drush en ' . implode(' ', $moduleInstall) . ' -y';
    $commands['Enabling themes.'] = 'drush theme:enable ' . implode(' ', $themeInstall) . ' -y';
    $commands['Setting default frontend theme.'] = 'drush config:set system.theme default front -y';
    $commands['Setting default backend theme.'] = 'drush config:set system.theme admin back -y';
    $neoInstall = 'drush neo-install';
    if ($installClaudeHook) {
      $neoInstall .= ' --claude';
    }
    $commands['Installing Neo development environment.'] = $neoInstall;
    foreach ($commands as $message => $command) {
      $this->io->info($message);
      $shell = Drush::shell($commandPrefix . $command, $this->getRoot());
      $shell->run(function ($type, $buffer) use ($debug) {
        if ($debug) {
          $this->output()->writeln('-- ' . $buffer);
        }
      });
    }

    // Phase 3 Configurations.
    $config = [];
    $config['file.settings'] = [
      'filename_sanitization.transliterate' => '1',
      'filename_sanitization.replace_whitespace' => '1',
      'filename_sanitization.replace_non_alphanumeric' => '1',
      'filename_sanitization.deduplicate_separators' => '1',
      'filename_sanitization.lowercase' => '1',
    ];
    foreach ($config as $name => $values) {
      foreach ($values as $key => $value) {
        $this->io->info('Setting config ' . $name . '.' . $key . ' to ' . $value . '.');
        $shell = Drush::shell($commandPrefix . 'drush config:set ' . $name . ' ' . $key . ' ' . $value . ' -y', $this->getRoot());
        $shell->run(function ($type, $buffer) use ($debug) {
          if ($debug) {
            $this->output()->writeln('-- ' . $buffer);
          }
        });
      }
    }

    // Update .gitignore.
    try {
      $path = $this->getRoot() . '/.gitignore';
      $file = $fileSystem->exists($path) ? file_get_contents($path) : '';
      if (strpos($file, '# Neo') === FALSE) {
        $this->io->info('Updating .gitignore.');
        $file .= "\n# Neo\n/neo.json\n/tsconfig.neo.json\n/.stylelintcache\n!/config/files/*\n";
        $fileSystem->dumpFile($path, $file);
      }
    }
    catch (\Error $e) {
      $this->io->error('<error>' . $e->getMessage() . '</error>');
    }

    $this->io->info('Install node modules.');
    $shell = Drush::shell($commandPrefix . 'npm install', $this->getRoot());
    $shell->run();

    $this->io->info('Build Neo assets.');
    $shell = Drush::shell($commandPrefix . 'npm run deploy', $this->getRoot());
    $shell->run();

    if (!$debug) {
      $this->io->info('Uninstall "neo_create" module.');
      $shell = Drush::shell($commandPrefix . 'drush pmu neo_create', $this->getRoot());
      $shell->run();
      $this->io->info('Remove "neo_create" module.');
      $shell = Drush::shell($commandPrefix . 'ddev exec composer remove jacerider/neo_create', $this->getRoot());
      $shell->run();
    }

    $this->io->success('If using VScode, please visit your extentions tab and enable both the "Drupal Extension Pack" and "Drupal Neo Extention Pack".');

    if (getenv('DDEV_PROJECT')) {
      $this->io->success('Success! To enter DEV mode run "ddev ssh && npm start".');
    }
    else {
      $this->io->success('Success! To enter DEV mode run "npm start".');
    }
  }

  /**
   * Get the docroot.
   *
   * @return string
   *   The docroot.
   */
  protected function getRoot() {
    if (!isset($this->docRoot)) {
      $this->docRoot = $this->appRoot . '/';
      if (!file_exists($this->docRoot . 'composer.json')) {
        $this->docRoot = $this->appRoot . '/../';
        if (!file_exists($this->docRoot . 'composer.json')) {
          return FALSE;
        }
      }
    }
    return realpath($this->docRoot);
  }

}
