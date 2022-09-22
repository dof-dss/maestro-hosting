<?php

namespace MaestroHosting/Provider;

use Maestro\Utils;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides hosting setup and configuration for PlatformSH.
 */
class PlatformSH extends Hosting implements HostingInterface {

  /**
   * {@inheritdoc}
   */
  public function build($io) {
    parent::build($io);
    $routes = [];
    // Flag to determine if we need to include Solr configuration
    // in the Platform services file.
    $solr_required = FALSE;

    $platform = $this->fs()->readFile($this->path() . '/templates/.platform.app.template.yaml');
    $services = $this->fs()->readFile($this->path() . '/templates/.services.template.yaml');

    $platform['name'] = Utils::createApplicationId($this->project()->name());

    foreach ($this->project()->sites() as $site_id => $site) {

      // Create database relationship.
      if (!empty($site['database'])) {
        $platform['relationships'][$site_id] = 'db:' . $site['database'];
      }

      // Create solr relationship.
      if (!empty($site['solr'])) {
        $platform['relationships'][$site_id . '_solr'] = 'solr:' . $site['solr'];
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

      // Create development instance route.
      if ($site['status'] !== 'production') {
        // Create Platform SH route.
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
    $this->fs()->dumpFile('/.platform.app.yaml', $platform);
    $this->fs()->dumpFile('/.platform/services.yaml', $services);
    $this->fs()->dumpFile('/.platform/routes.yaml', $routes);

    // Copy Solr configuration to platform directory.
    $io->writeln('Copying Solr configuration.');
    $this->fs()->mirror($this->path() . '/files/solr_config', '/.platform/solr_config');

    // Copy environment file.
    $io->writeln('Copying environment file.');
    $this->fs()->copy($this->path() . '/files/.environment', '/.environment');

    // Copy Redis installer file.
    $io->writeln('Copying Redis install script.');
    $this->fs()->copy($this->path() . '/files/install-redis.sh', '/install-redis.sh');

    $this->addInstructions('Download PlatformSH databases: platform db:dump -p ' . $this->project()->id());
    $this->addInstructions('Download PlatformSH files: platform mount:download -p ' . $this->project()->id());
  }

}
