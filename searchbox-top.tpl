<!-- Doofinder TOP -->
<div id="df-search_block_top">

  <form id="df-searchbox" class="df-searchbox" method="get" action="{$link->getPageLink('search')}" autocomplete="off">
    <p>
      <label for="df-search-top"><!-- image on background --></label>
      <input type="hidden" name="controller" value="search" />
      <input type="hidden" name="orderby" value="position" />
      <input type="hidden" name="orderway" value="desc" />
      <input id="df-search-top" class="search_query df-search_query_top" type="text" name="search_query" placeholder="{l s='Enter a product name' mod='doofinder'}"
        {if isset($hook_top) && isset($customized) && $customized} style="{if $width}width: {$width};{/if}"{/if}
        value="{if isset($smarty.get.search_query)}{$smarty.get.search_query|htmlentities:$ENT_QUOTES:'utf-8'|stripslashes}{/if}" />
      <input type="submit" name="submit_search" value="{l s='Search' mod='doofinder'}" class="button" />
    </p>
  </form>

</div>
<!-- /Doofinder TOP -->