#!/usr/bin/env bash

# Variables to indicate key settings files or directories for Drupal.
DRUPAL_ROOT=/app/web
DRUPAL_CUSTOM_CODE=$DRUPAL_ROOT/modules/custom

# If we don't have a Drupal install, download it.
if [ ! -d "/app/web/core" ]; then
  echo "Installing Drupal"
  export COMPOSER_PROCESS_TIMEOUT=600
  composer install
fi

if [ ! -d "/app/private" ]; then
  echo "Creating private files folder"
  mkdir -p /app/private
fi

if [ ! -d $DRUPAL_ROOT/sites/default/settings.local.php ]; then
  echo "Creating local Drupal settings and developent services files"
  cp -v /app/.lando/config/settings.local.php $DRUPAL_ROOT/sites/default/
  cp -v /app/.lando/config/services.local.yml $DRUPAL_ROOT/sites/
fi
