<?php
/*
LICENCIA
*/
require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/doofinder.php');

$context = Context::getContext();


//
// Configure script
//

define('TXT_SEPARATOR', '|');

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

// if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'))
//   ob_start("ob_gzhandler");
// else
//   ob_start();

header("Content-Type:text/plain; charset=utf-8");

$header = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn');
$categoriesCache = array();

$res = Db::getInstance(_PS_USE_SQL_SLAVE_)->query($sql);

echo implode(TXT_SEPARATOR, $header).PHP_EOL;

while ($row = Db::getInstance()->nextRow($res))
{
  $product = array();

  // ID
  $product[] = $row['id_product'];

  // TITLE
  $product[] = mb_convert_case(dfTools::cleanString($row['name']), MB_CASE_TITLE, 'UTF-8');

  // LINK
  $cat_link_rew = Category::getLinkRewrite($row['id_category_default'], $lang->id);
  $product[] = $context->link->getProductLink(intval($row['id_product']),
                                              $row['link_rewrite'],
                                              $cat_link_rew,
                                              $row['ean13'],
                                              intval($row['id_lang']),
                                              $shop->id,
                                              0, true);

  // DESCRIPTION
  $product[] = dfTools::cleanString($row['description'.($cfg_short_desc ? '_short' : '')]);

  // PRODUCT PRICE & ON SALE PRICE
  $product_price = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
  $onsale_price = Product::getPriceStatic($row['id_product'], true, null, 2);

  $product[] = Tools::convertPrice($product_price, $currency);
  $product[] = ($product_price != $onsale_price) ? Tools::convertPrice($onsale_price, $currency) : "";

  // IMAGE LINK
  $image = Image::getCover($row['id_product']);
  $product[] = $context->link->getImageLink($row['link_rewrite'],
                                            $row['id_product'] .'-'. $image['id_image'],
                                            $cfg_image_size);

  // PRODUCT CATEGORIES

  $productCategories = array();

  foreach (Product::getProductCategories($row['id_product']) as $categoryId)
  {
    $category = new Category($categoryId, $lang->id, $shop->id);
    $cat_link_rew = Category::getLinkRewrite($categoryId, $lang->id);

    if (!array_key_exists($cat_link_rew, $categoriesCache))
    {
      $tmp = array();
      foreach ($category->getParentsCategories($lang->id) as $cat)
        if (!$cat['is_root_category'])
          $tmp[] = $cat['name'];

      $tmp = trim(dfTools::cleanString(implode(' > ', array_reverse($tmp))));

      if (!empty($tmp))
        $categoriesCache[$cat_link_rew] = $tmp;
    }

    if (isset($categoriesCache[$cat_link_rew]))
      $productCategories[] = $categoriesCache[$cat_link_rew];
  }

  $product[] = implode(' / ', $productCategories);

  // AVAILABILITY
  $product[] = intval($row['quantity']) ? 'in stock' : 'out of stock';

  // BRAND
  $product[] = dfTools::cleanString(Manufacturer::getNameById(intval($row['id_manufacturer'])));

  // GTIN
  $product[] = dfTools::cleanString($row['ean13']);

  // MPN
  $product[] = dfTools::cleanString($row['supplier_reference']);

  echo implode(TXT_SEPARATOR, $product).PHP_EOL;
}

