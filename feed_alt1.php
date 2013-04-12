<?php
@set_time_limit(0);

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/doofinder.php');

global $cookie;
$link = new Link();

define('TXT_SEPARATOR', '|');

$lang = dfTools::getLanguageFromRequest();
$currency = dfTools::getCurrencyForLanguageFromRequest($lang);

if (isset($_SERVER['HTTPS']))
{
  header('Strict-Transport-Security: max-age=500');
}
header("Content-Type:text/plain; charset=utf-8");

// HEADER

$header = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title_1', 'extra_title_2');
echo implode(TXT_SEPARATOR, $header).PHP_EOL;
flush();ob_flush();

// PRODUCTS
$rows = dfTools::getAvailableProductsForLanguage($lang->id, $limit, $offset);
$cfg_short_desc = (intval(Configuration::get('DF_GS_DESCRIPTION_TYPE')) == Doofinder::GS_SHORT_DESCRIPTION);
$cfg_image_size = Configuration::get('DF_GS_IMAGE_SIZE');

foreach ($rows as $row)
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
  echo dfTools::cleanString($row['description'.($cfg_short_desc ? '_short' : '')]).TXT_SEPARATOR;

  // PRODUCT PRICE & ON SALE PRICE
  $product_price = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
  $onsale_price = Product::getPriceStatic($row['id_product'], true, null, 2);

  echo Tools::convertPrice($product_price, $currency).TXT_SEPARATOR;
  echo (($product_price != $onsale_price) ? Tools::convertPrice($onsale_price, $currency) : "").TXT_SEPARATOR;

  // IMAGE LINK
  echo $link->getImageLink($row['link_rewrite'],
                           $row['id_product'] .'-'. $row['id_image'],
                           $cfg_image_size).TXT_SEPARATOR;

  // PRODUCT CATEGORIES
  echo dfTools::getCategoriesForProductIdAndLanguage($row['id_product'], $lang->id).TXT_SEPARATOR;

  // AVAILABILITY
  echo (intval($row['quantity']) ? 'in stock' : 'out of stock').TXT_SEPARATOR;

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

  echo PHP_EOL;
  flush(); ob_flush();
}
