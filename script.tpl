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
  <!-- TO REGISTER CLICKS -->
{if isset($productLinks)}
<script>
  var dfProductLinks = {$productLinks|json_encode};
  var dfLinks = Object.keys(dfProductLinks);
  if(dfLinks.length){
  	$(document).on('ready', function(){
	  	$('a').click(function(){
	  		var link = $(this);
	  		var href = $(this).attr('href');
	  		var dfLayer;
	  		if(typeof(dfClassicLayers)!='undefined'){
	  			dfLayer = dfClassicLayers[0];
	  		}
	  		else if (typeof(dfFullscreenLayers)!='undefined'){
	  			dfLayer = dfFullscreenLayers[0];
	  		}
	  		dfLinks.forEach(function(item){
	  			if(href.indexOf(item) > -1 && typeof(dfLayer) != 'undefined'){
	  				var hashid = dfLayer.layerOptions.hashid;
					var cookie = Cookies.getJSON('doofhit' + hashid);
					var query = cookie.query;
	  				dfLayer.controller.registerClick(dfProductLinks[item], {
	  					"query": query
	  				});
	  			}
	  		});
	  	});
	  });
  }
  
</script>  
{/if}
  <!-- END OF TO REGISTER CLICKS -->
