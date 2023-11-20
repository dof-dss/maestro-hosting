<?php

namespace Maestro\Hosting\Provider;

use Maestro\Core\FilesystemInterface;
use Maestro\Core\ProjectInterface;
use Maestro\Core\Utils;
use Maestro\Hosting\Hosting;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides hosting setup and configuration for PlatformSH.
 */
class PlatformSH extends Hosting {

  /**
   * {@inheritdoc}
   */
  public function build(StyleInterface $io, FilesystemInterface $fs, ProjectInterface $project) {
    parent::build($io, $fs, $project);
    $routes = [];
    // Flag to determine if we need to include Solr configuration
    // in the Platform services file.
    $solr_required = FALSE;
    $default_site_entries = 0;

    $platform = $fs->read($this->resourcesPath() . '/templates/.platform.app.template.yaml');
    $services = $fs->read($this->resourcesPath() . '/templates/.services.template.yaml');

    $platform['name'] = Utils::createApplicationId($project->name());

    /* Set disk sizes from project settings */
    $platform['disk'] = $project->project_disk_size() ?: 8000;
    $services['db']['disk'] = $project->db_disk_size() ?: 4000;

    foreach ($project->sites() as $site_id => $site) {

      // Create database relationship.
      if (!empty($site['database'])) {
        $platform['relationships'][$site_id] = 'db:' . $site['database'];
      }

      // Create solr relationship.
      if (!empty($site['solr'])) {
        $platform['relationships'][$site_id . '_solr'] = 'solr_8_11:' . $site['solr'];
        $solr_required = TRUE;
      }

      // Create Platform SH services.
      $services['db']['configuration']['schemas'][] = $site_id . 'db';
      $services['db']['configuration']['endpoints'][$site_id] = [
        'default_schema' => $site_id . 'db',
        'privileges' => [
          $site_id . 'db' => 'admin',
        ],
      ];

      if (!empty($site['solr'])) {
        $solr_conf_dir = new TaggedValue('archive', 'solr_config/');
        $services['solr']['configuration']['cores'][$site_id . '_index'] = [
          'conf_dir' => $solr_conf_dir,
        ];

        $services['solr']['configuration']['endpoints'][$site_id] = [
          'core' => $site_id . '_index',
        ];
        $solr_required = TRUE;
      }

      // Create cron entries.
      if (!empty($site['cron_spec']) && !empty($site['cron_cmd'])) {
        $platform['crons'][$site_id]['spec'] = $site['cron_spec'];
        $platform['crons'][$site_id]['cmd'] = $site['cron_cmd'];
      }

      // Create cron entry for Logz.io.
      $platform['crons']['logging']['spec'] = '*/5 * * * *';
      $platform['crons']['logging']['commands']['start'] = '/bin/bash /app/cronjob.sh';
      $platform['crons']['logging']['shutdown_timeout'] = 290;

      // Create platform url routing.
      // Process sites that do not use a 'www' subdomain.
      if (array_key_exists('www', $site) && $site['www'] == false) {
        if ($site['default'] === true) {
          $default_site_entries++;

          $routes['https://' . $site['url'] . '.{default}/'] = [
              'type' => 'upstream',
              'upstream' => $platform['name'] . ':http',
              'cache' => [
                  'enabled' => 'false',
              ],
          ];
        } else {
          $routes['https://' . $site['url'] . '/'] = [
              'type' => 'upstream',
              'upstream' => $platform['name'] . ':http',
              'cache' => [
                  'enabled' => 'false',
              ],
          ];
        }
      } else {
        if ($site['default'] === true) {
          $default_site_entries++;

          $routes['https://www.' . $site['url'] . '.{default}/'] = [
              'type' => 'upstream',
              'upstream' => $platform['name'] . ':http',
              'cache' => [
                  'enabled' => 'false',
              ],
          ];

          $routes['https://' . $site['url'] . '.{default}/'] = [
              'type' => 'redirect',
              'to' => 'https://www.' . $site['url'] . '.{default}/',
          ];
        } elseif ($site['status'] !== 'production' && $site['default'] !== true) {
          // Create routes for dev sites.
          $routes['https://www.' . $site['url'] . '/'] = [
              'type' => 'upstream',
              'upstream' => $platform['name'] . ':http',
              'cache' => [
                  'enabled' => 'false',
              ],
          ];

          $routes['https://' . $site['url'] . '/'] = [
              'type' => 'redirect',
              'to' => 'https://www.' . $site['url'] . '/',
          ];
        }
      }
    }

    // Update platform post deploy hook with list of sites.
    $platform['hooks']['post_deploy'] = str_replace('<sites_placeholder>', implode(' ', array_keys($this->project()->sites())), $platform['hooks']['post_deploy']);

    // Add 'Catch all' to PlatformSH routing.
    $routes['https://www.{all}/'] = [
      'type' => 'upstream',
      'upstream' => $platform['name'] . ':http',
      'cache' => [
        'enabled' => 'false',
      ],
    ];

    $routes['https://{all}/'] = [
      'type' => 'redirect',
      'to' => 'https://www.{all}/',
    ];

    // Remove Solr config if none of the sites use Solr.
    if (!$solr_required) {
      unset($services['solr']);
    }

    // Write Platform configuration files.
    $io->writeln('Writing platform configuration, services and routes.');
    $fs->write('/.platform.app.yaml', $platform, TRUE);
    $fs->write('/.platform/services.yaml', $services, TRUE);
    $fs->write('/.platform/routes.yaml', $routes, TRUE);

    // Copy Solr configuration to platform directory.
    $io->writeln('Copying Solr configuration.');
    $fs->copyDirectory($this->resourcesPath() . '/files/solr_config', '/.platform/solr_config');

    // Copy environment file.
    $io->writeln('Copying environment file.');
    $fs->copy($this->resourcesPath() . '/files/.environment', '/.environment');

    // Copy Redis installer file.
    $io->writeln('Copying Redis install script.');
    $fs->copy($this->resourcesPath() . '/files/install-redis.sh', '/install-redis.sh');

    // Warn if we have no default site or more than one defined.
    if ($default_site_entries === 0) {
      $io->warning("This project does not have a default site enabled.");
    }
    elseif ($default_site_entries > 1) {
      $io->warning("This project has multiple sites designated as the default site.");
    }

    $this->addInstructions('Download PlatformSH databases: platform db:dump -p ' . $project->id());
    $this->addInstructions('Download PlatformSH files: platform mount:download -p ' . $project->id());
  }

}
