# Solr 9 ownership

Maestro Hosting standardises the deployment mechanics for Corp Lite and Unity
without owning their search schemas.

## Maestro-owned behaviour

- Solr 9.9 image, modules, credential-free local health check, and persistent
  DDEV data volume.
- One DDEV startup script that creates or refreshes `<site>_index` cores from
  repository configsets.
- One read-only `.platform/solr_configsets` mount rather than a generated mount
  and command for every core.
- Platform service, relationship, endpoint, and per-core `conf_dir` generation.
- Shared configset generation and static-verification scripts copied to
  `scripts/solr` in consumer projects.
- A local connector synchronisation command, run after DDEV database pulls,
  that clears stale plugin caches and verifies every enabled Solr-backed site
  against its project-specific container and `<site>_index` core.

## Consumer-owned behaviour

Each consumer repository owns:

- the Solr site declarations in `project/project.yml`;
- Drupal Search API server configuration and local connector patches;
- `.platform/solr_configsets/<site>/conf` for every declared site; and
- its architecture note and any site-specific rollout/QA coverage.

This boundary lets Maestro remove infrastructure duplication while keeping
schema, fields, languages, processors, and module-version effects reviewable in
the codebase where they apply. A generic configset in this package must not be
used as the runtime source of truth.

## Release sequence

Merge and release Maestro Hosting first. Consumer PRs then update to that
release, rebuild generated files, regenerate their site configsets from current
local databases for Solr 9.9, and run `scripts/solr/verify-configsets`. Consumer
PRs and edge QA remain independent so a failed site or codebase does not force
an all-at-once rollout.
