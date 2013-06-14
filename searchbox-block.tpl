<!-- Doofinder BLOCK -->
<div id="search_block_left" class="block exclusive">
  <h4 class="title_block">{l s='Search' mod='doofinder'}</h4>
  <form id="df-searchbox-{$searchbox_position}" class="df-searchbox" method="get" action="{$link->getPageLink('search', true)}" autocomplete="off">
    <p class="block_content">
      <label for="df-search-{$searchbox_position}">{l s='Enter a product name' mod='doofinder'}</label>
      <input type="hidden" name="orderby" value="position" />
      <input type="hidden" name="controller" value="search" />
      <input type="hidden" name="orderway" value="desc" />
      <input class="search_query df-search_query_block" type="text" id="df-search-{$searchbox_position}" name="search_query" value="{if isset($smarty.get.search_query)}{$smarty.get.search_query|htmlentities:$ENT_QUOTES:'utf-8'|stripslashes}{/if}" />
      <input type="submit" class="button_mini search_button" value="{l s='go' mod='doofinder'}" />
    </p>
  </form>
</div>
<!-- /Doofinder BLOCK -->