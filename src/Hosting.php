<?php

namespace Maestro\Hosting;

use Maestro\Core\Filesystem\Filesystem;
use Maestro\Core\HostingInterface;
use Maestro\Core\ProjectInterface;
use Symfony\Component\Console\Style\StyleInterface;

/**
 * Base class for hosting services.
 */
abstract class Hosting implements HostingInterface {

  /**
   * The service status.
   *
   * @var bool
   */
  protected bool $isEnabled = FALSE;

  /**
   * The project definition.
   *
   * @var \Maestro\Core\ProjectInterface
   */
  protected $project;

  /**
   * Symfony Style instance.
   *
   * @var \Symfony\Component\Console\Style\StyleInterface
   */
  protected $io;

  /**
   * The FileSystem.
   *
   * @var \Maestro\Core\Filesystem\Filesystem
   */
  private Filesystem $fs;

  /**
   * The service instructions.
   *
   * @var array
   */
  protected array $instructions = [];

  /**
   * Hosting constructor.
   *
   * @throws \Exception
   */
  public function __construct() {
    $this->isEnabled = $this->fs()->directoryExists($this->path());
  }

  /**
   * {@inheritdoc}
   */
  public function build(StyleInterface $io, Filesystem $fs, ProjectInterface $project) {
    $this->io()->section($this->name());
  }

  /**
   * {@inheritdoc}
   */
  public function instructions() {
    return $this->instructions;
  }

  /**
   * Add to the service instructions.
   */
  public function addInstructions(string $instruction) {
    $this->instructions[] = $instruction;
  }

  /**
   * {@inheritdoc}
   */
  public function name() {
    return (new \ReflectionClass($this))->getShortName();
  }

  /**
   * Filepath to the current hosting service resources.
   *
   * @return string
   *   Relative filepath to the hosting resources within the vendor directory.
   */
  public function resourcesPath() {
    return 'vendor/dof-dss/maestro-hosting/resources/'
      . $this->project()->type()
      . '/' . $this->name();
  }

  /**
   * The Filesystem.
   *
   * @return \Maestro\Core\Filesystem\Filesystem
   *   The Filesystem instance.
   */
  protected function fs() {
    return $this->fs;
  }

  /**
   * The project definition.
   *
   * @return \Maestro\Core\ProjectInterface
   *   The Project instance.
   *
   */
  protected function project() {
    return $this->project;
  }

  /**
   * Symfony Style.
   *
   * @return \Symfony\Component\Console\Style\StyleInterface
   *   The Symfony Style instance.
   */
  protected function io() {
    return $this->io;
  }

}
