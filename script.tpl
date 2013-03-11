{if isset($hashid) || isset($script)}
  {if $script}
    <!-- START OF DOOFINDER SCRIPT -->
    {$script}
    <!-- END OF DOOFINDER SCRIPT -->
  {else}
    {if $hashid}
      <!-- START OF DOOFINDER SCRIPT -->
      <script type="text/javascript">
          {literal}
          var doofinder_script = '//d3chj0zb5zcn0g.cloudfront.net/media/js/doofinder-3.latest.min.js';

          (function(d,t){var f=d.createElement(t),s=d.getElementsByTagName(t)[0];f.async=1;
            f.src=('https:'==location.protocol?'https:':'http:')+doofinder_script;
            s.parentNode.insertBefore(f,s)}(document,'script')
          );

          if(!doofinder){var doofinder={};}
          {/literal}

          doofinder.options = {
            queryInput: '#df-searchbox',
            lang: '{$lang}',
            hashid: '{$hashid}',
            dtop: {$dtop},
            dleft: {$dleft},
            width: {$width}
          }
      </script>
      <!-- END OF DOOFINDER SCRIPT -->
    {/if}
  {/if}
{/if}