-- Patch to add the epp_redirect_target to wb_entity_per_page.
-- Licence: GNU GPL v2+

-- NOTE: VARBINARY is automatically rewritten to the appropriate SQLite type.

CREATE TABLE IF NOT EXISTS /*_*/wb_entity_per_page_tmp (
  epp_entity_id                  INT unsigned        NOT NULL, -- Id of the entity
  epp_entity_type                VARBINARY(32)       NOT NULL, -- Type of the entity
  epp_page_id                    INT unsigned        NOT NULL, -- Id of the page that stores the entity
  epp_redirect_target            VARBINARY(255)      DEFAULT NULL -- Target entity, in case the row represents a redirect
) /*$wgDBTableOptions*/;

INSERT INTO /*_*/wb_entity_per_page_tmp( epp_entity_id, epp_entity_type, epp_page_id )
  SELECT epp_entity_id, epp_entity_type, epp_page_id
  FROM wb_entity_per_page;

DROP TABLE /*_*/wb_entity_per_page;

ALTER TABLE /*_*/wb_entity_per_page_tmp RENAME TO /*_*/wb_entity_per_page;

CREATE UNIQUE INDEX IF NOT EXISTS /*i*/wb_epp_entity ON /*_*/wb_entity_per_page (epp_entity_id, epp_entity_type);
CREATE UNIQUE INDEX IF NOT EXISTS /*i*/wb_epp_page ON /*_*/wb_entity_per_page (epp_page_id);
CREATE INDEX IF NOT EXISTS /*i*/epp_redirect_target ON /*_*/wb_entity_per_page (epp_redirect_target);
