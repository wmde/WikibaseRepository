These are the release notes for the Wikibase extension.

Extension page on mediawiki.org: https://www.mediawiki.org/wiki/Extension:Wikibase
Latest version of the release notes: https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/extensions/Wikibase.git;a=blob;f=repo/RELEASE-NOTES


### Version 0.5 (dev)

* WikibaseEntityModificationUpdate hook now only gets fired on modification and not on insertion and
takes different arguments then it did before.
* WikibaseEntityInsertionUpdate hook was added
* WikibaseEntityDeletionUpdate hook now gets an EntityContent rather than an EntityDeletionUpdate.
* The definition of the database fields wb_terms.term_row_id and wb_items_per_site.ips_row_id have been changed to BIGINT for MySQL, to avoid integer overflow on large sites. '''NOTE:''' the column definition is not automatically updated when running update.php. If you expect a large number (hundreds of million) of edits on your wiki, please apply repo/sql/MakeRowIDsBig.sql to your database manually. This is only needed for MySQL (and for PostGres, which however isn't fully supported at the moment).

### Version 0.4 (???)

#### API

* Dropped support for numeric item ids. They now always need to be prefixed.

#### Backend

* Referenced entities are now added to the pagelinks table and thus show up on places such as Special:WhatLinksHere

### Version 0.3 (???)

#### Interface

* Added Special:ListDatatypes
* Added Special:NewProperty

#### API

* Added wbcreateclaim API module
* Added wbgetclaims API module
* Added wbremoveclaims API module
* Added wbsetclaimvalue API module
* Added wbsetreference API module
* Added wbremovereferences API module
* Added wbsetstatementrank API module
* Added wbsetqualifier API module
* Added wbremovequalifiers API module

#### Backend

* Claims are now included in the output of EntitySerializer, thus are now present in the wbgetentities API module
* API serializers have been moved to lib and made non-API specific
* Internal serialization logic for Snak, Claim, Statement and Reference has been implemented in toArray and newFromArray.

### Version 0.2 (???)

* Added Special:SetLabel
* Added Special:EntitiesWithoutLabel
* Added wbsearchentities API module
* Renamed wbsetitem API module to wbsetentity
* Added wb_entity_per_page table
* Most serialization in the API is done via new API serializers

### Version 0.1 (2012-11-01)

Initial release with these features:

* Items can be created, modified and deleted, and have most functionality associated with normal articles
* Items have internationalized labels, descriptions and aliases
* Items have associated sitelinks which can be propagated automatically to clients
* Editing of items can be done via the rich user interface
* An API to access and modify Items

