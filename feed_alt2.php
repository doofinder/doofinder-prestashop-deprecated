<?php
/**
 * Doofinder On-site Search Prestashop Module
 *
 * Author:  Carlos Escribano <carlos@markhaus.com>
 * Website: http://www.doofinder.com / http://www.markhaus.com
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish and distribute copies of the
 * Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 *     - The above copyright notice and this permission notice shall be
 *       included in all copies or substantial portions of the Software.
 *     - The Software shall be used for Good, not Evil.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * This Software is licensed with a Creative Commons Attribution NonCommercial
 * ShareAlike 3.0 Unported license:
 *
 *       http://creativecommons.org/licenses/by-nc-sa/3.0/
 */

@set_time_limit(0);

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/doofinder.php');

global $cookie;
$link = new Link();

//
// Configure script
//

define('TXT_SEPARATOR', '|');

$lang = dfTools::getLanguageFromRequest();
$currency = dfTools::getCurrencyForLanguageFromRequest($lang);

// CONFIG
$cfg_short_desc = (intval(Configuration::get('DF_GS_DESCRIPTION_TYPE')) == Doofinder::GS_SHORT_DESCRIPTION);
$cfg_image_size = Configuration::get('DF_GS_IMAGE_SIZE');
$cfg_display_prices = (bool) Doofinder::cfg('DF_GS_DISPLAY_PRICES', Doofinder::YES);
$cfg_prices_w_taxes = (bool) Doofinder::cfg('DF_GS_PRICES_USE_TAX', Doofinder::YES);

// OUTPUT
if (isset($_SERVER['HTTPS']))
  header('Strict-Transport-Security: max-age=500');

header("Content-Type:text/plain; charset=utf-8");

// HEADER
$header = array('id', 'title', 'link', 'description', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title_1', 'extra_title_2');

if ($cfg_display_prices)
{
  $header[] = 'price';
  $header[] = 'sale_price';
}

echo implode(TXT_SEPARATOR, $header).PHP_EOL;
dfTools::flush();

// PRODUCTS
$sql = "SELECT *
        FROM _DB_PREFIX_product p
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON p.id_product = pl.id_product
        WHERE p.active = 1
          AND pl.id_lang = _ID_LANG_
        ORDER BY p.id_product;";
$sql = dfTools::prepareSQL($sql, array('_ID_LANG_' => $lang->id));

foreach(Db::s($sql) as $row)
{
  $product = array();

  // ID
  echo $row['id_product'].TXT_SEPARATOR;

  // TITLE
  $product_title = dfTools::cleanString($row['name']);
  echo $product_title.TXT_SEPARATOR;

  // LINK
  $cat_link_rew = Category::getLinkRewrite($row['id_category_default'], $lang->id);
  echo $link->getProductLink(intval($row['id_product']),
                             $row['link_rewrite'],
                             $cat_link_rew,
                             $row['ean13'],
                             intval($row['id_lang'])).TXT_SEPARATOR;

  // DESCRIPTION
  echo dfTools::cleanString($row['description'.($cfg_short_desc ? '_short' : '')]).TXT_SEPARATOR;

  // IMAGE LINK
  $image = Image::getCover($row['id_product']);
  echo $link->getImageLink($row['link_rewrite'],
                           $row['id_product'] .'-'. $image['id_image'],
                           $cfg_image_size).TXT_SEPARATOR;

  // PRODUCT CATEGORIES
  echo dfTools::getCategoriesForProductIdAndLanguage($row['id_product'], $lang->id).TXT_SEPARATOR;

  // AVAILABILITY
  echo (intval($row['quantity']) ? 'in stock' : 'out of stock').TXT_SEPARATOR;

  // BRAND
  echo dfTools::cleanString(Manufacturer::getNameById(intval($row['id_manufacturer']))).TXT_SEPARATOR;

  // GTIN
  echo dfTools::cleanString($row['ean13']).TXT_SEPARATOR;

  // MPN
  echo dfTools::cleanString($row['supplier_reference']).TXT_SEPARATOR;

  // EXTRA_TITLE_1
  echo dfTools::cleanReferences($product_title).TXT_SEPARATOR;

  // EXTRA_TITLE_2
  echo dfTools::splitReferences($product_title);

  // PRODUCT PRICE & ON SALE PRICE
  if ($cfg_display_prices)
  {
    echo TXT_SEPARATOR;

    $product_price = Product::getPriceStatic($row['id_product'], $cfg_prices_w_taxes, null, 2, null, false, false);
    $onsale_price = Product::getPriceStatic($row['id_product'], $cfg_prices_w_taxes, null, 2);

    echo Tools::convertPrice($product_price, $currency).TXT_SEPARATOR;
    echo (($product_price != $onsale_price) ? Tools::convertPrice($onsale_price, $currency) : "");
  }

  echo PHP_EOL;
  dfTools::flush();
}

