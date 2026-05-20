#!/bin/bash
# Used in .github/workflows/secondary-ci-api-testing-no-opensearch.yml
# The script used in Wikimedia CI is in build/jenkins/mw-apply-wb-settings.sh

set -x

cd ../mediawiki

function apply_repo_settings {
  echo '$wgServer = "http://127.0.0.1:8080";' >> LocalSettings.php
  echo '$wgCanonicalServer = "http://127.0.0.1:8080";' >> LocalSettings.php
  echo '$wgScriptPath = "";' >> LocalSettings.php

  echo '$wgWBClientSettings["repoSiteId"] = "default";' >> LocalSettings.php
  echo '$wgWBClientSettings["siteGlobalID"] = "default";' >> LocalSettings.php
  echo '$wgWBClientSettings["siteGroup"] = "local";' >> LocalSettings.php
  echo '$wgWBClientSettings["siteLinkGroups"] = [ "local" ];' >> LocalSettings.php
  echo '$wgWBRepoSettings["siteLinkGroups"] = [ "local", "wikipedia" ];' >> LocalSettings.php
}

apply_repo_settings