# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# page object for recent changes special page

class RecentChangesPage < NewItemPage
  include PageObject
  page_url WIKI_REPO_URL + "index.php/Special:RecentChanges"
  unordered_list(:recentChanges, :class => "special")
  span(:firstResultLabelSpan, :class => "wb-itemlink-label")
  span(:firstResultIdSpan, :class => "wb-itemlink-id")
  link(:firstResultLink, :xpath => "//ul[@class='special']/li/span/a")

  def countSearchResults
    count = 0
    searchResults_element.each do |resultElem|
      count = count+1
    end
    return count
  end
end
