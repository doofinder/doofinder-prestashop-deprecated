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

/**
 * Accepted parameters:
 *
 * - limit:      Max results in this request.
 * - offset:     Zero-based position to start getting results.
 * - language:   Language ISO code, like "es" or "en"
 * - currency:   Currency ISO code, like "EUR" or "GBP"
 * - taxes:      Boolean. Apply taxes to prices. Default true.
 * - prices:     Boolean. Display Prices. Default true.
 */

@set_time_limit(0);

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

global $cookie;
$link = new Link();

define('TXT_SEPARATOR', '|');

$lang = dfTools::getLanguageFromRequest();
$currency = dfTools::getCurrencyForLanguageFromRequest($lang);

// CONFIG
$cfg_short_description = (dfTools::cfg('DF_GS_DESCRIPTION_TYPE', Doofinder::GS_SHORT_DESCRIPTION) == Doofinder::GS_SHORT_DESCRIPTION);
$cfg_display_prices = dfTools::getBooleanFromRequest('prices', (bool) dfTools::cfg('DF_GS_DISPLAY_PRICES', Doofinder::YES));
$cfg_prices_w_taxes = dfTools::getBooleanFromRequest('taxes', (bool) dfTools::cfg('DF_GS_PRICES_USE_TAX', Doofinder::YES));
$cfg_image_size = dfTools::cfg('DF_GS_IMAGE_SIZE');

$limit = Tools::getValue('limit', false);
$offset = Tools::getValue('offset', false);

// OUTPUT
if (isset($_SERVER['HTTPS']))
  header('Strict-Transport-Security: max-age=500');

header("Content-Type:text/plain; charset=utf-8");

// HEADER
$header = array('id', 'title', 'link', 'description', 'alternate_description',
                'meta_keywords', 'meta_title', 'meta_description', 'image_link',
                'categories', 'availability', 'brand', 'gtin', 'mpn',
                'extra_title_1', 'extra_title_2');

if ($cfg_display_prices)
{
  $header[] = 'price';
  $header[] = 'sale_price';
}

if (!$limit || ($offset !== false && intval($offset) === 0))
{
  echo implode(TXT_SEPARATOR, $header).PHP_EOL;
  dfTools::flush();
}

// PRODUCTS
foreach (dfTools::getAvailableProductsForLanguage($lang->id, $limit, $offset) as $row)
{
  // ID
  echo $row['id_product'].TXT_SEPARATOR;

  // TITLE
  $product_title = dfTools::cleanString($row['name']);
  echo $product_title.TXT_SEPARATOR;

  // LINK
  echo $link->getProductLink($row['id_product'],
                             $row['link_rewrite'],
                             $row['cat_link_rew'],
                             $row['ean13'],
                             $lang->id).TXT_SEPARATOR;

  // DESCRIPTION
  echo dfTools::cleanString($row[($cfg_short_description ? 'description_short' : 'description')]).TXT_SEPARATOR;

  // ALTERNATE DESCRIPTION
  echo dfTools::cleanString($row[($cfg_short_description ? 'description' : 'description_short')]).TXT_SEPARATOR;

  // META KEYWORDS
  echo dfTools::cleanString($row['meta_keywords']).TXT_SEPARATOR;

  // META TITLE
  echo dfTools::cleanString($row['meta_title']).TXT_SEPARATOR;

  // META DESCRIPTION
  echo dfTools::cleanString($row['meta_description']).TXT_SEPARATOR;

  // IMAGE LINK
  echo $link->getImageLink($row['link_rewrite'],
                           $row['id_product'] .'-'. $row['id_image'],
                           $cfg_image_size).TXT_SEPARATOR;

  // PRODUCT CATEGORIES
  echo dfTools::getCategoriesForProductIdAndLanguage($row['id_product'], $lang->id).TXT_SEPARATOR;

  // AVAILABILITY
  echo (intval($row['quantity']) ? 'in stock' : 'out of stock').TXT_SEPARATOR;
  // echo (StockAvailable::outOfStock($row['id_product'], $shop->id) ? 'in stock' : 'out of stock').TXT_SEPARATOR;

  // BRAND
  echo $row['manufacturer'].TXT_SEPARATOR;

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
