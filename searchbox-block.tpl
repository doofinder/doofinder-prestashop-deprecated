{if isset($script)}
  <div id="df-searchbox-wrap" class="block {$searchbox_type}">
    <h4 class="title_block">{l s='Search' mod='doofinder'}</h4>

    {include file="$self/form.tpl"}
  </div>
{/if}

{if isset($hook_top)}
<p class="df-warning">{l s='Remove the top search box first!' mod='doofinder'}</p>
{/if}