{if isset($searchbox_enabled) && $searchbox_enabled}
  {if isset($hook_mobile)}
    <div class="input_search" data-role="fieldcontain">
  {else}
    <div id="df-searchbox-wrap" class="top"{if $customized} style="{if $top}top: {$top};{/if}{if $left}left: {$left};{/if}"{/if}>
  {/if}
      {include file="$self/form.tpl"}
    </div>
{/if}