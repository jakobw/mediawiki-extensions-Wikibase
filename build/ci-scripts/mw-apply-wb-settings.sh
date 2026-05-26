#!/bin/bash
# Used in .github/workflows/secondaryCI.yml and .github/workflows/secondary-ci-api-testing-no-opensearch.yml
# The script used in Wikimedia CI is in build/jenkins/mw-apply-wb-settings.sh

set -x

cd ../mediawiki

function apply_client_settings {
  echo '$wgEnableWikibaseClient = true;' >> LocalSettings.php
  echo '$wgWBClientSettings["siteGlobalID"] = "enwiki";' >> LocalSettings.php
  echo 'wfLoadExtension( "Scribunto" );' >> LocalSettings.php
}

function apply_repo_settings {
  echo '$wgEnableWikibaseRepo = true;' >> LocalSettings.php

  echo '$wgServer = "http://default.mediawiki.local.wmftest.net:8080";' >> LocalSettings.php
  echo '$wgCanonicalServer = "http://default.mediawiki.local.wmftest.net:8080";' >> LocalSettings.php
  echo '$wgScriptPath = "";' >> LocalSettings.php
}

function apply_common_before_settings {
  echo 'error_reporting(E_ALL);' >> LocalSettings.php
  echo 'ini_set("display_errors", 1);' >> LocalSettings.php
  # For re-using the Wikimedia CI settings, pretend we're running in quibble
  echo 'if ( !defined( "MW_QUIBBLE_CI" ) ) define( "MW_QUIBBLE_CI", true );' >> LocalSettings.php
  echo '$wgShowExceptionDetails = true;' >> LocalSettings.php
  echo '$wgDevelopmentWarnings = true;' >> LocalSettings.php
  echo '$wgLanguageCode = "'$LANG'";' >> LocalSettings.php
  echo '$wgDebugLogFile = "mw-debug.log";' >> LocalSettings.php
  echo 'wfLoadExtension( "cldr" );' >> LocalSettings.php
  echo '$wgEnableWikibaseClient = false;' >> LocalSettings.php
  echo '$wgEnableWikibaseRepo = false;' >> LocalSettings.php
  if [ "$IPMASKING" = "enabled" ]; then
      echo '$wgAutoCreateTempUser["enabled"] = true;' >> LocalSettings.php
  fi
}

function apply_common_after_settings {
  echo 'require_once __DIR__ . "/extensions/Wikibase/Wikibase.php";' >> LocalSettings.php
}


apply_common_before_settings

if [ "$WB" = "repo" ]
then
  apply_repo_settings
elif [ "$WB" = "client" ]
then
  apply_client_settings
else
  apply_repo_settings
  apply_client_settings
fi

# Override siteGlobalID and repoSiteId for api testing
if [ "${E2E_SITELINK_SITE_ID:-}" = "default" ]; then
  echo '$wgWBClientSettings["siteGlobalID"] = "default";' >> LocalSettings.php
  echo '$wgWBClientSettings["repoSiteId"] = "default";' >> LocalSettings.php
  echo '$wgWBClientSettings["siteGroup"] = "local";' >> LocalSettings.php
  # echo '$wgWBClientSettings["siteLinkGroups"] = [ "local" ];' >> LocalSettings.php
  echo '$wgWBRepoSettings["siteLinkGroups"] = [ "local", "wikipedia" ];' >> LocalSettings.php
fi

apply_common_after_settings
