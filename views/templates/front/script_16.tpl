{if isset($script)}
  <!-- START OF DOOFINDER SCRIPT -->
  {$script|html_entity_decode:2:"UTF-8" nofilter}
  <!-- END OF DOOFINDER SCRIPT -->
{/if}

{if isset($extra_css)}
  <!-- START OF DOOFINDER CSS -->
  {$extra_css}
  <!-- END OF DOOFINDER CSS -->
{/if}
  <!-- TO REGISTER CLICKS -->
{if isset($productLinks)}
<script>
  var dfProductLinks = {$productLinks|json_encode nofilter};
  var dfLinks = Object.keys(dfProductLinks);
  var doofinderAppendAfterBanner = "{$doofinder_banner_append}";
</script>  
{/if}
  <!-- END OF TO REGISTER CLICKS -->
