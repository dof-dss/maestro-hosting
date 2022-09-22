<?php

namespace MaestroHosting;

use League\Flysystem\Filesystem;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Interface for hosting services.
 */
interface HostingInterface {

  /**
   * Generates the hosting setup and configuration.
   *
   * @param \Symfony\Component\Console\Style\SymfonyStyle $io
   *   Symfony style instance.
   * @param \League\Flysystem\Filesystem $fs
   *   Filesystem instance.
   */
  public function build(SymfonyStyle $io, Filesystem $fs);
}
