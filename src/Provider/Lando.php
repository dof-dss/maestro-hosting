<?php

namespace Maestro\Hosting\Provider;

use Maestro\Core\FilesystemInterface;
use Maestro\Core\ProjectInterface;
use Maestro\Core\Utils;
use Maestro\Hosting\Hosting;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Provides hosting setup and configuration for Lando.
 */
class Lando extends Hosting {

  /**
   * {@inheritdoc}
   */
  public function build(StyleInterface $io, FilesystemInterface $fs, ProjectInterface $project) {
    parent::build($io, $fs, $project);
    $data = [];

    $data['name'] = Utils::createApplicationId($project->name());

    foreach ($project->sites() as $site_id => $site) {

      // Create Lando proxy.
      $data['proxy']['appserver'][] = $site['url'] . '.lndo.site';

      // Create solr relationship.
      if (!empty($site['solr'])) {
        $data['services'][$site_id . '_solr'] = [
          'type' => 'solr:8.11',
          'portforward' => TRUE,
          'core' => 'default',
          'config' => [
            'dir' => '.lando/config/solr/',
          ],
        ];
      }
    }

    // Copy the base configuration for Lando.
    $io->writeln("Creating Lando base configuration file.");
    $fs->copy($this->resourcesPath() . '/templates/.lando.base.yml', '/.lando.base.yml');

    // Create project specific Lando file.
    $io->writeln("Creating Lando project configuration file.");
    $fs->write('/.lando.yml', $data, TRUE);

    // Copy Lando resources to the project.
    $io->writeln("Copying Lando resources to project.");
    $fs->createDirectory('/.lando');
    $fs->copyDirectory($this->resourcesPath() . '/files', '/.lando');

    // Copy Lando Drupal services file if one doesn't already exist.
    if (!$fs->exists('/web/sites/default/services.yml')) {
      $io->writeln("Copying Lando Drupal services file.");
      $fs->copy($this->resourcesPath() . '/templates/drupal.services.yml', '/web/sites/default/services.yml');
    }

    // Copy Lando Redis config file if one doesn't already exist.
    if (!$fs->exists('/web/sites/default/redis.services.yml')) {
      $io->writeln("Copying Lando Redis config file.");
      $fs->copy($this->resourcesPath() . '/templates/redis.services.yml', '/web/sites/default/redis.services.yml');
    }

    // Create public files directory if one doesn't already exist.
    if (!$fs->exists('/web/files')) {
      $io->writeln("Creating Drupal public files directory.");
      $fs->createDirectory('/web/files');
    }

    // Create private files directory if one doesn't already exist.
    if (!$fs->exists('/.lando/private')) {
      $io->writeln("Creating Drupal private files directory.");
      $fs->createDirectory('/.lando/private');
    }

    // Check for an .env file and copy example if missing.
    if (!$fs->exists('/.env')) {
      // Copy from the sample env file as it may have project specific entries.
      // If sample.en doesn't exist, copy the basic version.
      if (!$fs->exists('/.env.sample')) {
        $fs->copy($this->resourcesPath() . '/templates/.env.sample', '/.env.sample');
      }

      $fs->copy('/.env.sample', '/.env');
      $io->success('Created local .env file');
    }

    // Read .env file to check for some default Drupal environment settings.
    $env_data = $fs->read('/.env');

    if (empty($env_data['HASH_SALT'])) {
      if ($io->confirm('Hash Salt was not found in the .env file. Would you like to add one?')) {
        $env_data['HASH_SALT'] = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));
        $fs->write('/.env', $env_data);
        $io->success('Creating local site hash within .env file');
      }
    }

    // Inform the user if composer install is needed.
    if (!$fs->exists('/vendor')) {
      $this->addInstructions("Run 'lando composer install'");
    }

    $this->addInstructions("Import platform databases using 'lando db-import <database name> <dump file>'");
  }

}
