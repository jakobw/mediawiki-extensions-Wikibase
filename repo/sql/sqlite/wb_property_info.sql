-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: extensions/Wikibase/repo/sql/abstract/wb_property_info.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/wb_property_info (
  pi_property_id INTEGER UNSIGNED NOT NULL,
  pi_type BLOB NOT NULL,
  pi_info BLOB NOT NULL,
  PRIMARY KEY(pi_property_id)
);

CREATE INDEX pi_type ON /*_*/wb_property_info (pi_type);
