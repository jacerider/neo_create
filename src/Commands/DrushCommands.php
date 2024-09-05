<?php

declare(strict_types=1);

namespace Drupal\neo_create\Commands;

use Drupal\Component\Serialization\Yaml;
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
    private readonly string $appRoot
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
      'jacerider/valet',
      'drupal/pathauto',
      'drupal/focal_point',
      'drupal/allowed_formats',
      'drupal/hide_revision_field',
      'drupal/disable_user_1_edit',
      'drupal/reroute_email',
      'drupal/config_split',
      'drupal/config_readonly',
      'drupal/config_ignore',
      'jacerider/neo',
      'jacerider/neo_theme',
      'jacerider/neo_loader',
      'jacerider/neo_image',
    ];
    $moduleInstall = [
      'devel',
      'valet',
      'pathauto',
      'focal_point',
      'allowed_formats',
      'hide_revision_field',
      'disable_user_1_edit',
      'reroute_email',
      'config_split',
      'config_readonly',
      'config_ignore',
      'neo',
      'neo_icon',
      'neo_icon_admin',
      'neo_icon_local_task',
      'neo_modal',
      'neo_loader',
      'neo_image',
      'neo_twig',
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

    // Phase 1 Commands.
    $color = $this->io()->ask('What is your primary HEX color? (Default: #2780e3)', '#2780e3');
    $commands = [];
    $commands['Setting minimum-stability to dev.'] = 'composer config minimum-stability dev';
    $commands['Installing modules and themes.'] = 'composer require ' . implode(' ', $composerRequire);
    $commands['Configuring VScode for Drupal. '] = 'composer config --json --merge extra.installer-paths \'{".vscode/extensions/{$name}": ["type:vscode-extension"]}\' && composer config --json --merge extra.installer-types \'["vscode-extension"]\' && composer config scripts.vscode-setup "VscodeDrupal\\Install::postPackageInstall" && composer require augustash/vscode-drupal && composer vscode-setup -- --color=' . $color;
    foreach ($commands as $message => $command) {
      $this->io->info($message);
      $shell = Drush::shell($command, $this->getRoot());
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
    $commands['Installing Neo development environment.'] = 'drush neo-install';
    foreach ($commands as $message => $command) {
      $this->io->info($message);
      $shell = Drush::shell($command, $this->getRoot());
      $shell->run(function ($type, $buffer) use ($debug) {
        if ($debug) {
          $this->output()->writeln('-- ' . $buffer);
        }
      });
    }

    // Update .gitignore.
    try {
      $path = $this->getRoot() . '/.gitignore';
      $file = $fileSystem->exists($path) ? file_get_contents($path) : '';
      if (strpos($file, '# Neo') === FALSE) {
        $this->io->info('Updating .gitignore.');
        $file .= "\n# Neo\n/neo.json\n/.stylelintcache";
        $fileSystem->dumpFile($path, $file);
      }
    }
    catch (\Error $e) {
      $this->io->error('<error>' . $e->getMessage() . '</error>');
    }

    // Update .ddev.
    try {
      $path = $this->getRoot() . '/.ddev/nginx/neo.conf';
      if (!$fileSystem->exists($path)) {
        $data = "location /neo-assets/ {\n    rewrite ^(.*)\.neo$ $1 break;\n    proxy_pass http://127.0.0.1:5173/neo-assets/;\n    proxy_http_version 1.1;\n    proxy_set_header Upgrade \$http_upgrade;\n    proxy_set_header Connection \"upgrade\";\n}";
        $fileSystem->dumpFile($path, $data);
      }
    }
    catch (\Error $e) {
      $this->io->error('<error>' . $e->getMessage() . '</error>');
    }

    $this->io->info('Install node modules.');
    $shell = Drush::shell('npm install', $this->getRoot());
    $shell->run();

    $this->io->info('Build neo assets.');
    $shell = Drush::shell('npm run deploy', $this->getRoot());
    $shell->run();

    $this->io->info('Uninstall "neo_create" module.');
    $shell = Drush::shell('drush pmu neo_create', $this->getRoot());
    $shell->run();
    $this->io->info('Remove "neo_create" module.');
    $shell = Drush::shell('composer remove jacerider/neo_create', $this->getRoot());
    $shell->run();

    $this->io->success('If using VScode, please visit your extentions tab and enable both the "Drupal Extension Pack" and "Drupal Neo Extention Pack".');

    $this->io->success('Success! To enter DEV mode run "npm start".');
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
