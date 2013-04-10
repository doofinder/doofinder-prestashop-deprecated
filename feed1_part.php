<?php
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

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
if ($id_currency) {
    $id_currency = Currency::getIdByIsoCode(strtoupper($id_currency));
} else {
    $optname = 'DF_GS_CURRENCY_' . strtoupper($lang->iso_code);
    $id_currency = Currency::getIdByIsoCode(Configuration::get($optname));

    if (!$id_currency)
        $id_currency = $context->currency->id;
}
$currency = new Currency($id_currency);

$cfg_short_desc = (intval(Configuration::get('DF_GS_DESCRIPTION_TYPE')) == Doofinder::GS_SHORT_DESCRIPTION);
$cfg_image_size = Configuration::get('DF_GS_IMAGE_SIZE');


/* get all category_product relations and save to cache */
$sql = "SELECT * FROM " . _DB_PREFIX_ . "category_product";
$rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
$cache['productCategories'] = array();
foreach ($rows as $row) {
    $cache['productCategories'][$row['id_product']][] = $row['id_category'];
}

/* get all products */
$sql = "SELECT  cl.`link_rewrite` as cat_link_rew,
                i.id_image,
                p.*,
                pl.*
        FROM _DB_PREFIX_product p
        LEFT JOIN _DB_PREFIX_product_lang pl ON p.id_product = pl.id_product
        LEFT JOIN " . _DB_PREFIX_ . "category_lang cl ON cl.id_category = p.id_category_default
        LEFT JOIN " . _DB_PREFIX_ . "image i ON i.id_product = p.id_product
        " . Shop::addSqlAssociation('image', 'i') . "
        WHERE p.active = 1
            AND pl.id_lang = _ID_LANG_
            AND cl.id_lang = _ID_LANG_
            #AND p.id_product IN ((SELECT a.id_product FROM _DB_PREFIX_product a WHERE a.id_product <1100))
            AND p.id_product IN (_IDS_PRODUCTS_)
            AND image_shop.`cover`= 1
        ORDER BY p.id_product;";
$sql = dfTools::prepareSQL($sql, array(
            '_ID_LANG_' => $id_lang,
            '_IDS_PRODUCTS_' => $_GET['ids']
       ));

$cache['products'] = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);



/* get all categories and order resultSetToTree */
$sql = "SELECT * FROM " . _DB_PREFIX_ . "category c
        LEFT JOIN " . _DB_PREFIX_ . "category_lang cl ON cl.id_category = c.id_category
        WHERE cl.id_lang = " . $context->language->id;

$rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

//$cache['categories'] = resultSetToTree(0,$rows,'id_category','id_parent','children');


$output = array();
foreach ($cache['products'] as $z => $row) {

    // ID
    $output[$z] = $row['id_product'] . TXT_SEPARATOR;

    // TITLE
    $product_title = dfTools::cleanString($row['name']);
    $output[$z] .= $product_title . TXT_SEPARATOR;

    // LINK
    //$row['cat_link_rew'] = Category::getLinkRewrite($row['id_category_default'], $lang->id);

    $output[$z] .= $context->link->getProductLink(intval($row['id_product']),
                    $row['link_rewrite'],
                    $row['cat_link_rew'],
                    $row['ean13'],
                    intval($row['id_lang']),
                    $shop->id,
                    0, true) . TXT_SEPARATOR;

    // DESCRIPTION
    $output[$z] .= dfTools::cleanString($row['description' . ($cfg_short_desc ? '_short' : '')]) . TXT_SEPARATOR;

    // PRODUCT PRICE & ON SALE PRICE
    $product_price = Product::getPriceStatic($row['id_product'], true, null, 2, null, false, false);
    $onsale_price = Product::getPriceStatic($row['id_product'], true, null, 2);

    $output[$z] .= Tools::convertPrice($product_price, $currency) . TXT_SEPARATOR;

    $output[$z] .= ( ($product_price != $onsale_price) ? Tools::convertPrice($onsale_price, $currency) : "") . TXT_SEPARATOR;

    // IMAGE LINK
    //$image = Image::getCover($row['id_product']);
    $output[$z] .= $context->link->getImageLink(
                    $row['link_rewrite'],
                    $row['id_product'] . '-' . $row['id_image'],
                    $cfg_image_size
            ) . TXT_SEPARATOR;


    // PRODUCT CATEGORIES

    $categories = "";
    //$categoryIds = Product::getProductCategories($row['id_product']);
    $categoryIds = $cache['productCategories'][$row['id_product']];
    $nbcategories = count($categoryIds);
    $i = 0;

    if (true)
        foreach ($categoryIds as $categoryId) {
            /* retrieving single categroy path */
            if (!isset($cache['treeCategories'][$categoryId])) {
                $sql = "SELECT cl.name, c2.is_root_category
                        FROM ps_category AS c1,
                                ps_category AS c2
                        LEFT JOIN ps_category_lang cl ON cl.id_category = c2.id_category
                        WHERE c1.nleft BETWEEN c2.nleft AND c2.nright
                                AND c1.id_category = '" . $categoryId . "'
                                AND c2.id_parent > 0
                                AND cl.id_lang = " . $context->language->id . "
                        ORDER BY c1.nleft;";

                $tree = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                foreach ($tree as $i) {
                    if($i['is_root_category']) continue;
                    $cache['treeCategories'][$categoryId][] = dfTools::cleanString($i['name']);
                }
            }

            if(!isset($cache['implodeTreeCategories'][$categoryId]) && isset($cache['treeCategories'][$categoryId])){
                $cache['implodeTreeCategories'][$categoryId] = implode(CATEGORY_TREE_SEPARATOR, $cache['treeCategories'][$categoryId]);
            }

            if (isset($cache['implodeTreeCategories'][$categoryId]))
            {
              $categories .= $cache['implodeTreeCategories'][$categoryId];
              $categories .= CATEGORY_SEPARATOR;
            }
        }

    $output[$z] .= trim($categories, CATEGORY_SEPARATOR) . TXT_SEPARATOR;

    // AVAILABILITY
    $output[$z] .= ( intval($row['quantity']) ? 'in stock' : 'out of stock') . TXT_SEPARATOR;

    // BRAND
    $output[$z] .= dfTools::cleanString(Manufacturer::getNameById(intval($row['id_manufacturer']))) . TXT_SEPARATOR;

    // GTIN
    $output[$z] .= dfTools::cleanString($row['ean13']) . TXT_SEPARATOR;

    // MPN
    $output[$z] .= dfTools::cleanString($row['supplier_reference']) . TXT_SEPARATOR;

    // EXTRA_TITLE
    $output[$z] .= dfTools::cleanReferences($product_title) . TXT_SEPARATOR;

    // EXTRA_TITLE
    $output[$z] .= dfTools::splitReferences($product_title) . PHP_EOL;
}

header("Content-Type:text/plain; charset=utf-8");
echo implode($output);