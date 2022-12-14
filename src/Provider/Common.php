<?php

namespace Maestro\Hosting\Provider;

use Maestro\Core\FilesystemInterface;
use Maestro\Core\ProjectInterface;
use Maestro\Hosting\Hosting;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Provides common hosting setup and configuration.
 */
class Common extends Hosting {

  /**
   * {@inheritdoc}
   */
  public function build(StyleInterface $io, FilesystemInterface $fs, ProjectInterface $project) {
    parent::build($io, $fs, $project);

    $io->text('Verifying setup for ' . count($this->project()->sites()) . ' site(s).');
    foreach ($this->project()->sites() as $site_id => $site) {
      // If a site folder doesn't exist under project/sites, create it and
      // provide a settings file.
      if (!$fs->exists('/project/sites/' . $site_id)) {
        $io->text('Creating a site directory for ' . $site_id . ' under project/sites/');
        $fs->createDirectory('/project/sites/' . $site_id);
        $fs->copy($this->resourcesPath() . '/files/multisite.settings.php', '/project/sites/' . $site_id . '/settings.php');
      }

      // Enable our multisite entry by linking from the sites directory to
      // the project directory.
      $fs->link('//app/project/sites/' . $site_id, '/web/sites/' . $site_id);

      // If a site config doesn't exist under project/config, create it.
      if (!$fs->exists('/project/config/' . $site_id)) {
        $io->text('Creating config directory for ' . $site_id . ' under project/config/');
        $fs->createDirectory('/project/config/' . $site_id);
        $fs->write('/project/config/' . $site_id . '/.gitkeep', "");

        // Create the default config directories if they don't already exist.
        foreach (['config', 'hosted', 'local', 'production'] as $directory) {
          $io->text('Creating default config directories');
          if (!$fs->exists('/project/config/' . $site_id . '/' . $directory)) {
            $fs->createDirectory('/project/config/' . $site_id . '/' . $directory);
          }
        }
      }
    }

    // Copy base Drupal services file is one doesn't already exist.
    if (!$fs->exists('/web/sites/services.yml')) {
      $io->text('Creating Drupal services file from defaults.');
      $fs->copy($this->resourcesPath() . '/files/default.services.yml', '/web/sites/services.yml');
    }

  }

}
