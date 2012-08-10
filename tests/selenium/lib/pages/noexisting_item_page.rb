# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# page object for a non existing item

require 'ruby_selenium'

class NonExistingItemPage < RubySelenium
  include PageObject
  page_url WIKI_REPO_URL + "index.php/Data:Qxy"

  span(:firstHeading, :xpath => "//h1[@id='firstHeading']/span")
  link(:specialLogLink, :css => "div#mw-content-text > div > p > span > a:nth-child(1)")
  link(:specialCreateNewItemLink, :css => "div#mw-content-text > div > p > span > a:nth-child(2)")
  text_field(:labelInputField, :xpath => "//h1[@id='firstHeading']/span/span/input")
  text_field(:descriptionInputField, :xpath => "//div[@id='mw-content-text']/div/span/span/input")
end
