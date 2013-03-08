<form id="df-searchbox-form" method="get" action="{$link->getPageLink('search', true)}" autocomplete="off">

  {if !isset($hook_mobile)}
    {if isset($hook_top)}
    <label for="df-searchbox"><!-- image on background --></label>
    {else}
    <label for="doofinder_searchbox">{l s='Enter a product name' mod='doofinder'}</label>
    {/if}
  {/if}

  <input type="hidden" name="controller" value="search" />
  <input type="hidden" name="orderby" value="position" />
  <input type="hidden" name="orderway" value="desc" />
  <input id="df-searchbox" type="text" name="search_query" placeholder="{$placeholder}"
    value="{if isset($smarty.get.search_query)}{$smarty.get.search_query|htmlentities:$ENT_QUOTES:'utf-8'|stripslashes}{/if}" />
  <!-- TODO: ENABLE/DISABLE WITH ADMIN OPTION
  {if !isset($hook_mobile)}
    {if isset($hook_top)}
    <input type="submit" name="submit_search" value="{l s='Search' mod='doofinder'}" class="button" />
    {else}
    <input type="submit" id="search_button" class="button_mini" value="{l s='go' mod='doofinder'}" />
    {/if}
  {/if}
  -->
</form>