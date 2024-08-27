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
    $composerRequire = [
      'drupal/devel',
      'jacerider/valet',
      'jacerider/neo',
      'jacerider/neo_theme',
      'jacerider/neo_image',
    ];
    $moduleInstall = [
      'devel',
      'valet',
      'neo',
      'neo_icon',
      'neo_icon_admin',
      'neo_icon_local_task',
      'neo_modal',
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
    $commands[] = 'composer require ' . implode(' ', $composerRequire);
    $commands[] = 'composer config --json --merge extra.installer-paths \'{".vscode/extensions/{$name}": ["type:vscode-extension"]}\' && composer config --json --merge extra.installer-types \'["vscode-extension"]\' && composer config scripts.vscode-setup "VscodeDrupal\\Install::postPackageInstall" && composer require augustash/vscode-drupal && composer vscode-setup -- --color=' . $color;
    foreach ($commands as $command) {
      $shell = Drush::shell($command, $this->getRoot());
      $shell->run(function ($type, $buffer) {
        $this->output()->writeln('-- ' . $buffer);
      });
    }

    foreach ($themeClone as $theme) {
      try {
        $to = $this->appRoot . '/themes/' . $theme;
        $from = $this->appRoot . '/themes/contrib/neo_theme/neo_base/install/neo/' . $theme;
        if (!$fileSystem->exists($to) && $fileSystem->exists($from)) {
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
    $commands[] = 'drush en ' . implode(' ', $moduleInstall) . ' -y';
    $commands[] = 'drush theme:enable ' . implode(' ', $themeInstall) . ' -y';
    $commands[] = 'drush config:set system.theme default front -y';
    $commands[] = 'drush config:set system.theme admin back -y';
    $commands[] = 'drush neo-install';
    foreach ($commands as $command) {
      $shell = Drush::shell($command, $this->getRoot());
      $shell->run(function ($type, $buffer) {
        $this->output()->writeln('-- ' . $buffer);
      });
    }

    // Update .file.
    try {
      $path = $this->getRoot() . '/.file';
      $file = $fileSystem->exists($path) ? file_get_contents($path) : '';
      if (strpos($file, '# Neo') === FALSE) {
        $file .= "\n# Neo\n/neo.json\n/.stylelintcache";
        $fileSystem->dumpFile($path, $file);
      }
    }
    catch (\Error $e) {
      $this->io->error('<error>' . $e->getMessage() . '</error>');
    }

    // Update .ddev.
    try {
      $path = $this->getRoot() . '/.ddev/config.yaml';
      if ($fileSystem->exists($path)) {
        $file = Yaml::decode(file_get_contents($path));
        if (!isset($file['web_extra_exposed_ports']) || empty(array_filter($file['web_extra_exposed_ports'], function ($port) {
          return $port['name'] === 'Vite';
        }))) {
          $file['web_extra_exposed_ports'][] = [
            'name' => 'Vite',
            'container_port' => 5173,
            'http_port' => 5172,
            'https_port' => 5173,
          ];
        }
        $fileSystem->dumpFile($path, Yaml::encode($file));
      }
    }
    catch (\Error $e) {
      $this->io->error('<error>' . $e->getMessage() . '</error>');
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
