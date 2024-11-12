# Maestro Hosting

Hosting providers and assets for Maestro.

## Directory structure
```
└─ resources/ ## Root resources directory.
├─── unity/ ## Resources for hosting providers operating on Unity Projects. 
├─ src/ ## Root code directory.
├─── provider/ ## Hosting providers which perform the build operations. 
├─── Hosting.php ## Base class for writing a hosting provider.
├─ composer.json ## PHP packages for this project.
├─ composer.lock ## Record of package versions defined in composer.json
├─ vendor/ ## Installed packages from composer.
```

## Updating hosting configuration

Under resources/<project_type> you will find the resources for each hosting provider.
Each resource directory will typically contain a 'files' and 'templates' directory. 
- Files are copied across during the build without needing alteration.
- Templates are files that are modified during the build process before added to the project. 

Changes to content in the files directory can be added and published as a new release.  
If you require changes to a template you should make your changes but also look at the 
hosting provider class to check the alterations made to the file.

## Updating hosting build steps

If you need to alter how a hosting provider constructs the overall hosting setup you will 
need to change the steps in the build function of the provider class.

## Adding a new hosting provider

To add a new hosting provider create a new class that inherits the Hosting base class.
Add the various setup steps to the build function and add any resources to 
resources/<project_type>/</provider_name>. It's important that the provider_name directory matches 
the name of your provider class (including case).

## Injected services

Each providers build method is injected with 3 services:
- StyleInterface $io, 
- FilesystemInterface $fs, 
- ProjectInterface $project

### StyleInterface

The styleinterface provides method to communicate user messages to the Maestro shell. 

See: https://github.com/symfony/console/blob/4.2/Style/StyleInterface.php for details. 

### FilesystemInterface

The FilesystemInterface provides methods to interact with the filesystem IO and mirrors the 
functionality of the Symfony Filesystem class but provides the following additional features:
- Paths starting with a forward slash will be relative to the current directory.
- Paths starting with a double forward slash will be treated as absolute paths.
- The Read method will automatically parse the given filepath based on the file extension.
- The Write method will automatically compile the content parameter to the correct format based on file extension.

See: https://github.com/dof-dss/maestro-core/blob/main/src/FilesystemInterface.php

### ProjectInterface

The projectInterface provides methods to manage the Maestro project.   
You will usually call and iterate over the sites() method from within your build() 
function to setup the hosting environment for each site.

See https://github.com/symfony/console/blob/4.2/Style/StyleInterface.php for details.

## Enabling a hosting provider

Each forked project repository must contain a maestro.yml file with the required hosting provider
entries. When the maestro shell project:build command runs, it will iterate each of these providers
and run their build method.  
Entries must have a service name (which isn't strict), the FQN of the provider class and a tag of 'maestro.hosting'.  
See the example below.

```yaml
services:
  hosting.platformSH:
    class: 'Maestro\Hosting\Provider\PlatformSH'
    tags:
      - { name: maestro.hosting }
  hosting.lando:
    class: 'Maestro\Hosting\Provider\Lando'
    tags:
      - { name: maestro.hosting }
```

# DDev

The DDev hosting provider brings some changes to local hosting compared to Lando.

- All multisite databases are hosted in a single database service (db).
- All solr cores are hosted on a single Solr service (solr). 
- Code standards commands are path aware.

Be mindful that if you alter/delete any of the db/solr services you will affect all of your local sites.

All multisite databases can be imported to your local instance from the platform development environment using:

`ddev pull unity`

This will overwrite all multisite databases within your local environment.

`phpcs` and `drck` commands are now path aware and will run against the directory on the service
that is mapped to your local directory.
e.g. if I run `ddev phpcs` within my `/Users/devuser1/project/unity_svr1/project/sites/pbni` directory
it will perform the check against the pbni directory in the ddev web service.





