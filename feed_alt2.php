<?php
@set_time_limit(0);

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/doofinder.php');

global $cookie;

//
// Configure script
//

define('TXT_SEPARATOR', '|');

$id_lang = Tools::getValue('lang');
$id_lang = intval($id_lang ? Language::getIdByIso($id_lang) : (int) $cookie->id_lang);
$lang = new Language($id_lang);

$id_currency = Tools::getValue('currency');
if ($id_currency)
{
  $id_currency = Currency::getIdByIsoCode(strtoupper($id_currency));
}
else
{
  $optname = 'DF_GS_CURRENCY_'.strtoupper($lang->iso_code);
  $id_currency = Currency::getIdByIsoCode(Configuration::get($optname));

  if (!$id_currency)
    $id_currency = $cookie->id_currency;
}
$currency = new Currency($id_currency);

$link = new Link();

$cfg_short_desc = (intval(Configuration::get('DF_GS_DESCRIPTION_TYPE')) == Doofinder::GS_SHORT_DESCRIPTION);
$cfg_image_size = Configuration::get('DF_GS_IMAGE_SIZE');

$sql = "SELECT *
        FROM _DB_PREFIX_product p
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON p.id_product = pl.id_product
        WHERE p.active = 1
          AND pl.id_lang = _ID_LANG_
        ORDER BY p.id_product;";
$sql = dfTools::prepareSQL($sql, array('_ID_LANG_' => $id_lang));

//
// Output
//

header("Content-Type:text/plain; charset=utf-8");

$header = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title_1', 'extra_title_2');

echo implode(TXT_SEPARATOR, $header).PHP_EOL;
flush(); ob_flush();

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

  // PRODUCT PRICE & ON SALE PRICE
  $product_price = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
  $onsale_price = Product::getPriceStatic($row['id_product'], true, null, 2);

  echo Tools::convertPrice($product_price, $currency).TXT_SEPARATOR;
  echo (($product_price != $onsale_price) ? Tools::convertPrice($onsale_price, $currency) : "").TXT_SEPARATOR;

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

  echo PHP_EOL;
  flush(); ob_flush();
}

