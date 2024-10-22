<?php

namespace Maestro\Hosting\Provider;

use Maestro\Core\FilesystemInterface;
use Maestro\Core\ProjectInterface;
use Maestro\Core\Utils;
use Maestro\Hosting\Hosting;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Provides hosting setup and configuration for DDev.
 */
class DDev extends Hosting {

  /**
   * {@inheritdoc}
   */
  public function build(StyleInterface $io, FilesystemInterface $fs, ProjectInterface $project) {
    parent::build($io, $fs, $project);
    $data = [];

    $data['name'] = Utils::createApplicationId($project->name());

    foreach ($project->sites() as $site_id => $site) {

      // Multisite hosts.
      $data['additional_hostnames'][] = $site['url'];

      // Create solr relationship.
//      if (!empty($site['solr'])) {
//        // Make solr service name unique.
//        $solr_server_name = $site_id . '_solr';
//        if (preg_match('/intranet/i', $project->name())) {
//          $solr_server_name = $site_id . '_intranet_solr';
//        }
//        $data['services'][$solr_server_name] = [
//          'type' => 'solr:8.11',
//          'portforward' => TRUE,
//          'core' => 'default',
//          'config' => [
//            'dir' => '.lando/config/solr/',
//          ],
//        ];
//      }
    }

    $fs->createDirectory('/.ddev');

    // Copy the base configuration for DDev.
    $io->writeln("Creating DDev base configuration file.");
    $fs->copy($this->resourcesPath() . '/templates/config.yaml', '/.ddev/config.yaml');

    // Create project specific DDev file.
    $io->writeln("Creating DDev project configuration file.");
    $fs->write('/.ddev/config.maestro.yaml', $data, TRUE);

    // Copy DDev resources to the project.
    $io->writeln("Copying DDev resources to project.");
    $fs->copyDirectory($this->resourcesPath() . '/files', '/.ddev');

    // Copy Lando Drupal services file if one doesn't already exist.
    if (!$fs->exists('/web/sites/default/services.yml')) {
      $io->writeln("Copying DDev Drupal services file.");
      $fs->copy($this->resourcesPath() . '/templates/drupal.services.yml', '/web/sites/default/services.yml');
    }

    // Create public files directory if one doesn't already exist.
    if (!$fs->exists('/web/files')) {
      $io->writeln("Creating Drupal public files directory.");
      $fs->createDirectory('/web/files');
    }

    // Create private files directory if one doesn't already exist.
    if (!$fs->exists('/private')) {
      $io->writeln("Creating Drupal private files directory.");
      $fs->createDirectory('/private');
    }

    // Check for an .env file and copy example if missing.
    if (!$fs->exists('/.ddev/.env')) {
      // Copy from the sample env file as it may have project specific entries.
      // If sample.env doesn't exist, copy the basic version.
      if (!$fs->exists('/.ddev/.env.sample')) {
        $fs->copy($this->resourcesPath() . '/templates/.env.sample', '/.ddev/.env.sample');
      }

      $fs->copy('/.ddev/.env.sample', '/.ddev/.env');
      $io->success('Created local .env file');
    }

    // Read .env file to check for some default Drupal environment settings.
    $env_data = $fs->read('/.ddev/.env');

    if (empty($env_data['HASH_SALT'])) {
      if ($io->confirm('Hash Salt was not found in the .env file. Would you like to add one?')) {
        $env_data['HASH_SALT'] = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(random_bytes(55)));
        $fs->write('/.ddev/.env', $env_data);
        $io->success('Creating local site hash within .env file');
      }
    }

    // Inform the user if composer install is needed.
    if (!$fs->exists('/vendor')) {
      $this->addInstructions("Run 'ddev composer install'");
    }

    // TODO: Write a per project provider and display usage instructions.
//    $this->addInstructions("Import platform databases using 'lando db-import <database name> <dump file>'");
  }

}
