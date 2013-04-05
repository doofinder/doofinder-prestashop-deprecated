<?php

if (!ini_get('safe_mode'))
  @set_time_limit(0);

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/doofinder.php');

$context = Context::getContext();


//
// Configure script
//

define('TXT_SEPARATOR', '|');
define('CATEGORY_SEPARATOR', '/');
define('CATEGORY_TREE_SEPARATOR', '>');

$shop = new Shop((int) $context->shop->id);
if (!$shop->id)
  die('NOT PROPERLY CONFIGURED');

$id_lang = Tools::getValue('lang');
$id_lang = intval($id_lang ? Language::getIdByIso($id_lang) : (int) $context->language->id);
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
    $id_currency = $context->currency->id;
}
$currency = new Currency($id_currency);

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

$header = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title');

$res = Db::getInstance(_PS_USE_SQL_SLAVE_)->query($sql);

echo implode(TXT_SEPARATOR, $header).PHP_EOL;
flush(); ob_flush();

while ($row = Db::getInstance()->nextRow($res))
{
  // ID
  echo $row['id_product'].TXT_SEPARATOR;

  // TITLE
  $product_title = dfTools::cleanString($row['name']);
  echo $product_title.TXT_SEPARATOR;

  // LINK
  $cat_link_rew = Category::getLinkRewrite($row['id_category_default'], $lang->id);
  echo $context->link->getProductLink(intval($row['id_product']),
                                      $row['link_rewrite'],
                                      $cat_link_rew,
                                      $row['ean13'],
                                      intval($row['id_lang']),
                                      $shop->id,
                                      0, true).TXT_SEPARATOR;

  // DESCRIPTION
  echo dfTools::cleanString($row['description'.($cfg_short_desc ? '_short' : '')]).TXT_SEPARATOR;

  // PRODUCT PRICE & ON SALE PRICE
  $product_price = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
  $onsale_price = Product::getPriceStatic($row['id_product'], true, null, 2);

  echo Tools::convertPrice($product_price, $currency).TXT_SEPARATOR;
  echo (($product_price != $onsale_price) ? Tools::convertPrice($onsale_price, $currency) : "").TXT_SEPARATOR;

  // IMAGE LINK
  $image = Image::getCover($row['id_product']);
  echo $context->link->getImageLink($row['link_rewrite'],
                                    $row['id_product'] .'-'. $image['id_image'],
                                    $cfg_image_size).TXT_SEPARATOR;

  // PRODUCT CATEGORIES

  $categories = "";
  $categoryIds = Product::getProductCategories($row['id_product']);
  $nbcategories = count($categoryIds);
  $i = 0;

  foreach ($categoryIds as $categoryId)
  {
    $category = new Category($categoryId, $lang->id, $shop->id);
    $cat_link_rew = Category::getLinkRewrite($categoryId, $lang->id);

    $tree = "";
    $parents = array_reverse($category->getParentsCategories($lang->id));
    $nbparents = count($parents);
    $j = 0;

    foreach ($parents as $cat)
    {
      if ($cat['is_root_category'])
      {
        // It's not going to be visible so it doesn't count.
        $nbparents--;
        continue;
      }

      $tree .= $cat['name'];
      if (++$j < $nbparents)
        $tree .= CATEGORY_TREE_SEPARATOR;
    }

    if ($tree = trim($tree))
    {
      $categories .= dfTools::cleanString($tree);
      if (++$i < $nbcategories)
        $categories .= CATEGORY_SEPARATOR;
    }
    else
    {
      // It's not going to be visible so it doesn't count.
      $nbcategories--;
    }
  }

  echo $categories.TXT_SEPARATOR;

  // AVAILABILITY
  echo intval($row['quantity']) ? 'in stock' : 'out of stock'.TXT_SEPARATOR;

  // BRAND
  echo dfTools::cleanString(Manufacturer::getNameById(intval($row['id_manufacturer']))).TXT_SEPARATOR;

  // GTIN
  echo dfTools::cleanString($row['ean13']).TXT_SEPARATOR;

  // MPN
  echo dfTools::cleanString($row['supplier_reference']).TXT_SEPARATOR;

  // EXTRA_TITLE
  echo dfTools::purgeString($product_title);

  echo PHP_EOL;
  flush(); ob_flush();
}

