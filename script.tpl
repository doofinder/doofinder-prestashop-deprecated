{if isset($script)}
  <!-- START OF DOOFINDER SCRIPT -->
  {$script|html_entity_decode:2:"UTF-8"}
  <!-- END OF DOOFINDER SCRIPT -->
{/if}

{if isset($extra_css)}
  <!-- START OF DOOFINDER CSS -->
  {$extra_css}
  <!-- END OF DOOFINDER CSS -->
{/if}
