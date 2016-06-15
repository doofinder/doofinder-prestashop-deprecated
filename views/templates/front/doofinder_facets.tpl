{*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registred Trademark & Property of PrestaShop SA
*}

<!-- Doofinder Block layered navigation module -->
{if $nbr_filterBlocks != 0}
<script type="text/javascript">
var current_friendly_url = '#';
var param_product_url = '';
</script>
<div id="layered_block_left" class="block">
	<div class="block_content">
		<form action="#" id="layered_form">
			<div>
				
                                {foreach from=$facets item=facet}

                                    {if isset($facet._type)}
                                        {if isset($facet.ranges)}
                                        <div class="layered_price">
                                        {else}
                                        <div class="layered_filter {if !isset($facet.terms) OR $facet.terms|@count lt 1}hidden{/if}">
                                        {/if}
                                            <span class="layered_subtitle">{$options[$facet@key]|escape:html:'UTF-8'}</span>
                                            <span class="layered_close"><a href="#" data-rel="ul_layered_{$facet._type}_{$facet@key}">v</a></span>
                                            <div class="clear"></div>
                                            <ul id="ul_layered_{$facet._type}_{$facet@key}">
                                            {if !isset($facet.ranges) && $facet._type == 'terms'}
                                                {foreach from=$facet.terms key=id_value item=value name=fe}

                                                    {if $value.count}
                                                    <li class="nomargin {*if $smarty.foreach.fe.index >= $filter.filter_show_limit}hiddable{/if*}">

                                                            <input type="checkbox" class="checkbox" name="layered_{$facet._type}_{$facet@key}[]" id="layered_{$facet._type}_{$facet@key}_{$id_value}" value="{$value.term}"{if $value.selected} checked="checked"{/if}{if !$value.count} disabled="disabled"{/if} /> 

                                                            <label for="layered_{$facet._type}_{$facet@key}_{$id_value}"{if !$value.count} class="disabled"{/if}>
                                                                    {if !$value.count}
                                                                    {$value.term|escape:html:'UTF-8'} <span> ({$value.count})</span>
                                                                    {else}
                                                                    <a href="{$value.term}" data-rel="{$value.term}">{$value.term|escape:html:'UTF-8'} <span> ({$value.count})</span></a>
                                                                    {/if}
                                                            </label>
                                                    </li>
                                                    {/if}
                                                {/foreach}
                                            {else}
                                                <span id="layered_{$facet@key}_range"></span>
								<div class="layered_slider_container">
									<div class="layered_slider" id="layered_{$facet@key}_slider"></div>
								</div>
								<script type="text/javascript">
								{literal}
									var filterRange = {/literal}{$facet.ranges[0].max|string_format:"%.2f"}-{$facet.ranges[0].min|string_format:"%.2f"}{literal};
									var step = filterRange / 100;
									if (step > 1)
										step = parseInt(step);
									addSlider('{/literal}{$facet@key}{literal}',{
										range: true,
										step: step,
										min: {/literal}{$facet.ranges[0].min|string_format:"%.2f"}{literal},
										max: {/literal}{$facet.ranges[0].max|string_format:"%.2f"}{literal},
										values: [ {/literal}{$facet.ranges[0].min|string_format:"%.2f"}{literal}, {/literal}{$facet.ranges[0].max|string_format:"%.2f"}{literal}],
										slide: function( event, ui ) {
											stopAjaxQuery();
											{/literal}
	
											{literal}
												from = ui.values[0].toFixed(2)+' {/literal}{*$filter.unit*}€{literal}';
												to = ui.values[1].toFixed(2)+' {/literal}{*$filter.unit*}€{literal}';
											{/literal}
											
                                                                                            
											{literal}
											$('#layered_{/literal}{$facet@key}{literal}_range').html(from+' - '+to);
										},
										stop: function () {
											reloadContent();
										}
									}, '{/literal}{*$filter.unit*} €{literal}', {/literal}{*$filter.format*}5{literal});
								{/literal}
								</script>
                                            {/if}
                                            </ul>
                                        </div>
                                    {/if}
                                {/foreach}
				
			</div>
			<input type="hidden" name="id_category_layered" value="0" />
			<input type="hidden" name="search_query" id="doofinder_facets_search_query" value="" />
		</form>
	</div>
	<div id="layered_ajax_loader" style="display: none;">
		<p><img src="{$img_ps_dir}loader.gif" alt="" /><br />{l s='Loading...' mod='doofinder'}</p>
	</div>
</div>
{/if}
<!-- /Doofinder Block layered navigation module -->
