# -*- encoding : utf-8 -*-
# Wikidata UI tests
#
# Author:: Tobias Gritschacher (tobias.gritschacher@wikimedia.de)
# License:: GNU GPL v2+
#
# tests for client-repo connection

require 'spec_helper'

article_title_a = "Rome"
article_text_a = "It's the capital of Italy!"
article_title_b = "Palermo"
article_text_b = "It's a town on Sicily!"
item_description = "It's the capital of Italy!"
item_sitelink_en = [["en", "Rome"]]
item_sitelinks = [["de", "Rom"], ["it", "Roma"], ["fi", "Rooma"], ["hu", "Róma"]]
item_sitelinks_additional = [["fr", "Rome"]]
item_id = 0

describe "Check functionality of client-repo connection" do
  before :all do
    # set up: create article & item & add connecting sitelink
    visit_page(ClientPage) do |page|
      page.create_article(article_title_a, article_text_a, true)
    end
    visit_page(CreateItemPage) do |page|
      item_id = page.create_new_item(article_title_a, item_description)
      page.add_sitelinks(item_sitelink_en)
    end
  end

  context "client-repo adding/removing sitelinks" do
    it "should check article and that there are no interwikilinks yet" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.clientArticleTitle.should == article_title_a
        page.interwiki_xxx?.should be_false
      end
    end
    it "should add some sitelinks to the item" do
      on_page(ItemPage) do |page|
        page.navigate_to_item
        page.wait_for_sitelinks_to_load
        page.add_sitelinks(item_sitelinks)
        page.get_number_of_sitelinks_from_counter.should == item_sitelinks.count + 1
      end
    end
    it "should check if interwikilinks are shown correctly on client" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.count_interwiki_links.should == item_sitelinks.count
        page.interwiki_de?.should be_true
        page.interwiki_it?.should be_true
        page.interwiki_fi?.should be_true
        page.interwiki_hu?.should be_true
        page.interwiki_en?.should be_false
      end
    end
    it "should check if interwikilinks lead to correct websites" do
      on_page(ClientPage) do |page|
        page.interwiki_de
        page.clientArticleTitle.should == item_sitelinks[0][1]
        page.navigate_to_article(article_title_a)
        page.interwiki_it
        page.clientArticleTitle.should == item_sitelinks[1][1]
        page.navigate_to_article(article_title_a)
        page.interwiki_fi
        page.clientArticleTitle.should == item_sitelinks[2][1]
        page.navigate_to_article(article_title_a)
        page.interwiki_hu
        page.clientArticleTitle.should == item_sitelinks[3][1]
      end
    end
    it "should add additional sitelinks" do
      on_page(ItemPage) do |page|
        page.navigate_to_item
        page.wait_for_sitelinks_to_load
        page.add_sitelinks(item_sitelinks_additional)
        page.get_number_of_sitelinks_from_counter.should == item_sitelinks.count + 1 + item_sitelinks_additional.count
      end
    end
  end

  context "client-repo adding some more sitelinks" do
    it "should check if additional interwikilinks are shown correctly on client" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.count_interwiki_links.should == item_sitelinks.count + item_sitelinks_additional.count
        page.interwiki_de?.should be_true
        page.interwiki_it?.should be_true
        page.interwiki_fi?.should be_true
        page.interwiki_hu?.should be_true
        page.interwiki_fr?.should be_true
        page.interwiki_en?.should be_false
      end
    end
  end

  context "client-repo check behaviour on changing connecting sitelink" do
    it "should change the connecting sitelink to a nonexisting article" do
      on_page(ItemPage) do |page|
        page.navigate_to_item
        page.wait_for_sitelinks_to_load
        page.englishEditSitelinkLink
        page.pageInputField = "Philippeville"
        ajax_wait
        page.wait_until do
          page.editSitelinkAutocompleteList_element.visible?
        end
        page.saveSitelinkLink
        ajax_wait
        page.wait_for_api_callback
      end
    end
    it "should check that no sitelinks are displayed anymore on client" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.interwiki_xxx?.should be_false
      end
    end
    it "should create a second article" do
      visit_page(ClientPage) do |page|
        page.create_article(article_title_b, article_text_b, true)
        page.interwiki_xxx?.should be_false
      end
    end
    it "should change the connecting sitelink to an existing article" do
      on_page(ItemPage) do |page|
        page.navigate_to_item
        page.wait_for_sitelinks_to_load
        page.englishEditSitelinkLink
        page.pageInputField = article_title_b
        ajax_wait
        page.wait_until do
          page.editSitelinkAutocompleteList_element.visible?
        end
        page.saveSitelinkLink
        ajax_wait
        page.wait_for_api_callback
      end
    end
    it "should check that sitelinks are now displayed on article b instead of a" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.interwiki_xxx?.should be_false
        page.navigate_to_article(article_title_b)
        page.count_interwiki_links.should == item_sitelinks.count + item_sitelinks_additional.count
        page.interwiki_de?.should be_true
        page.interwiki_it?.should be_true
        page.interwiki_fi?.should be_true
        page.interwiki_hu?.should be_true
        page.interwiki_fr?.should be_true
        page.interwiki_en?.should be_false
      end
    end
  end

  context "client-repo changing back connecting sitelink" do
    it "should change the connecting sitelink back to origin" do
      on_page(ItemPage) do |page|
        page.navigate_to_item
        page.wait_for_sitelinks_to_load
        page.englishEditSitelinkLink
        page.pageInputField = item_sitelink_en[0][1]
        ajax_wait
        page.wait_until do
          page.editSitelinkAutocompleteList_element.visible?
        end
        page.saveSitelinkLink
        ajax_wait
        page.wait_for_api_callback
      end
    end
    it "should check that sitelinks are displayed again on client" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.count_interwiki_links.should == 5
        page.interwiki_de?.should be_true
        page.interwiki_it?.should be_true
        page.interwiki_fi?.should be_true
        page.interwiki_hu?.should be_true
        page.interwiki_fr?.should be_true
        page.interwiki_en?.should be_false
      end
    end
  end

  context "client-repo deleting/restoring item" do
    it "should delete item & that no sitelinks are shown on client" do
      visit_page(LoginPage) do |page|
        page.login_with(WIKI_ADMIN_USERNAME, WIKI_ADMIN_PASSWORD)
      end
      on_page(DeleteItemPage) do |page|
        page.delete_item
      end
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.interwiki_xxx?.should be_false
      end
    end
    it "should undelete item & check that sitelinks are shown again on client" do
      visit_page(LoginPage) do |page|
        page.login_with(WIKI_ADMIN_USERNAME, WIKI_ADMIN_PASSWORD)
      end
      on_page(UndeleteItemPage) do |page|
        page.undelete_item(item_id)
      end
=begin
      # not implemented yet! undeleting an item is not propagated to the client
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.count_interwiki_links.should == 5
        page.interwiki_de?.should be_true
        page.interwiki_it?.should be_true
        page.interwiki_fi?.should be_true
        page.interwiki_hu?.should be_true
        page.interwiki_fr?.should be_true
        page.interwiki_en?.should be_false
      end
=end
    end
  end

  context "client-repo removing the sitelinks from the repo and checking that they're gone on the client" do
    it "should remove all sitelinks" do
      on_page(ItemPage) do |page|
        page.navigate_to_item
        page.wait_for_sitelinks_to_load
        page.remove_all_sitelinks
      end
    end

    it "should check that no sitelinks are displayed for article a & b" do
      on_page(ClientPage) do |page|
        page.navigate_to_article(article_title_a)
        page.interwiki_xxx?.should be_false
        page.navigate_to_article(article_title_b)
        page.interwiki_xxx?.should be_false
      end
    end
  end

  after :all do
    # tear down: logout
    visit_page(LoginPage) do |page|
      page.logout_user
    end
  end
end
