#!/usr/bin/env bash

# Variables to indicate key settings files or directories for Drupal.
DRUPAL_ROOT=/app/web

# If we don't have a Drupal install, download it.
if [ ! -d "/app/web/core" ]; then
  echo "Installing Drupal"
  export COMPOSER_PROCESS_TIMEOUT=600
  composer install
fi

# Create Drupal public files directory and set IO permissions.
if [ ! -d "/app/web/files" ]; then
  echo "Creating public Drupal files directory"
  mkdir -p /app/web/files
  chmod -R 0775 /app/web/files
fi

# Create Drupal private file directory above web root.
if [ ! -d "/app/private" ]; then
  echo "Creating private Drupal files directory"
  mkdir -p /app/private
fi

if [ ! -d $DRUPAL_ROOT/sites/default/settings.local.php ]; then
  echo "Creating local Drupal settings and developent services files"
  cp -v /app/.ddev/homeadditions/config/drupal.settings.php $DRUPAL_ROOT/sites/default/settings.local.php
  cp -v /app/.ddev/homeadditions/config/drupal.services.yml $DRUPAL_ROOT/sites/local.development.services.yml
fi
