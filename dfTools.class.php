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

define('CATEGORY_SEPARATOR', ' / ');
define('CATEGORY_TREE_SEPARATOR', ' > ');

class dfTools
{
  //
  // Validation
  //

  public static function isBasicValue($v)
  {
    return $v && !empty($v) && Validate::isGenericName($v);
  }

  //
  // SQL Tools
  //

  public static function prepareSQL($sql, $args=array())
  {
    $keys = array('_DB_PREFIX_');
    $values = array(_DB_PREFIX_);

    foreach ($args as $k => $v)
    {
      $keys[] = $k;
      $values[] = $v;
    }

    return str_replace($keys, $values, $sql);
  }

  public static function limitSQL($sql, $limit = false, $offset = false)
  {
    if (false !== $limit && is_numeric($limit))
    {
      $sql .= " LIMIT ".intval($limit);

      if (false !== $offset && is_numeric($offset))
      {
        $sql .= " OFFSET ".intval($offset);
      }
    }

    return $sql;
  }

  //
  // SQL Queries
  //

  /**
   * Returns an array of image size names to be used in a <select> box.
   *
   * @return array (assoc) with the value of each key as value for it.
   */
  public static function getAvailableImageSizes()
  {
    $sizes = array();
    $sql = self::prepareSQL("SELECT `name` DF_GS_IMAGE_SIZE, `name` FROM `_DB_PREFIX_image_type` WHERE `products` = 1 ORDER BY `name`;");

    foreach (Db::getInstance()->ExecuteS($sql) as $size)
    {
      $sizes[$size['DF_GS_IMAGE_SIZE']] = $size;
    }

    return $sizes;
  }

  /**
   * Returns an assoc. array. Keys are currency ISO codes. Values are currency
   * names.
   * @return array.
   */
  public static function getAvailableCurrencies()
  {
    $currencies = array();

    $sql = self::prepareSQL("SELECT `iso_code`, `name` FROM `_DB_PREFIX_currency` WHERE `active` = 1 ORDER BY `name`;");

    foreach (Db::getInstance()->ExecuteS($sql) as $currency)
    {
      $currencies[$currency['iso_code']] = $currency;
    }

    return $currencies;
  }

  /**
   * Returns the # of products available for a language.
   */
  public static function countAvailableProductsForLanguage($id_lang)
  {
    $sql = "SELECT COUNT(*) AS total
            FROM _DB_PREFIX_product p
            LEFT JOIN _DB_PREFIX_product_lang pl
              ON p.id_product = pl.id_product
            WHERE p.active = 1
              AND pl.id_lang = _ID_LANG_;";
    $sql = self::prepareSQL($sql, array('_ID_LANG_' => $id_lang));
    $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    return intval($res[0]['total']);
  }

  /**
   * Returns the products available for a language
   * @param int Language ID.
   * @param int Optional. Default false. Number of products to get.
   * @param int Optional. Default false. Offset to start the select from.
   * @param string Optional. Fields to select.
   * @return array of rows (assoc arrays).
   */
  public static function getAvailableProductsForLanguage($id_lang, $limit=false, $offset=false, $fields=null)
  {
    if (null === $fields)
    {
      // $fields = "p.*, pl.*, cl.link_rewrite as cat_link_rew, i.id_image";
      $fields = "pl.id_product, p.id_category_default, m.name AS manufacturer, pl.name, pl.description, pl.description_short, pl.link_rewrite, p.quantity, p.supplier_reference, p.upc, p.ean13, cl.link_rewrite as cat_link_rew, i.id_image";
    }

    $shopSqlJoin = Shop::addSqlAssociation('image', 'i');

    $sql = "SELECT $fields
            FROM
              _DB_PREFIX_product p
              LEFT JOIN _DB_PREFIX_product_lang pl
                ON p.id_product = pl.id_product
              LEFT JOIN _DB_PREFIX_category_lang cl
                ON cl.id_category = p.id_category_default
              LEFT JOIN _DB_PREFIX_image i
                ON i.id_product = p.id_product
              LEFT JOIN _DB_PREFIX_manufacturer m
                ON m.id_manufacturer = p.id_manufacturer
              $shopSqlJoin
            WHERE
              p.active = 1
              AND pl.id_lang = _ID_LANG_
              AND cl.id_lang = _ID_LANG_
              AND image_shop.cover = 1
            ORDER BY p.id_product";
    $sql = self::limitSQL($sql, $limit, $offset);
    $sql = self::prepareSQL($sql, array('_ID_LANG_' => $id_lang));

    return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
  }

  /**
   * Returns the product IDs available for a language
   * @param int Language ID.
   * @param int Optional. Default false. Number of products to get.
   * @param int Optional. Default false. Offset to start the select from.
   * @return array of int
   */
  public static function getAvailableProductIdsForLanguage($id_lang, $limit=false, $offset=false)
  {
    $sql = "SELECT p.id_product AS id
            FROM _DB_PREFIX_product p
            LEFT JOIN _DB_PREFIX_product_lang pl
              ON p.id_product = pl.id_product
            WHERE p.active = 1
              AND pl.id_lang = _ID_LANG_
            ORDER BY p.id_product";

    $sql = self::limitSQL($sql, $limit, $offset);
    $sql = self::prepareSQL($sql, array('_ID_LANG_' => $id_lang)).";";

    $ids = array();
    foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $value)
      $ids[] = $value['id'];
    return $ids;
  }

  protected static
    $root_category_ids = null,
    $cached_category_paths = array();

  /**
   * Returns an array of "root" categories in Prestashop for a language.
   * The results are cached in a protected, static variable.
   * @return array.
   */
  public static function getRootCategoryIds($id_lang)
  {
    if (null === self::$root_category_ids)
    {
      self::$root_category_ids = array();
      foreach (Category::getRootCategories($id_lang) as $category)
        self::$root_category_ids[] = $category['id_category'];
    }

    return self::$root_category_ids;
  }

  /**
   * Returns the path to the first, no root ancestor category for the selected
   * category ID in a language for the selected shop.
   * Results are cached by category ID.
   *
   * @param int Category ID
   * @param int Language ID
   * @param int Shop ID
   * @return string
   */
  public static function getCategoryPath($id_category, $id_lang, $id_shop)
  {
    if (isset(self::$cached_category_paths[$id_category]))
      return self::$cached_category_paths[$id_category];

    $excluded_ids = self::getRootCategoryIds($id_lang);
    if ($excluded_ids)
    {
      $excluded_ids = implode(', ', $excluded_ids);
      $excluded_ids = "AND parent.id_category NOT IN ($excluded_ids)";
    }

    $sql = "SELECT DISTINCT cl.name AS name
            FROM
                _DB_PREFIX_category AS node,
                (
                  _DB_PREFIX_category AS parent
                  INNER JOIN _DB_PREFIX_category_lang AS cl
                    ON parent.id_category = cl.id_category
                )
            WHERE
                node.nleft BETWEEN parent.nleft AND parent.nright
                AND node.id_category = $id_category
                AND cl.id_shop = $id_shop
                AND cl.id_lang = $id_lang
                AND parent.level_depth <> 0
                $excluded_ids
            ORDER BY parent.nleft;";
    $sql = self::prepareSQL($sql);

    $path = array();
    foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $row)
      $path[] = $row['name'];
    $path = implode(CATEGORY_TREE_SEPARATOR, $path);

    $path = self::cleanString($path);

    self::$cached_category_paths[$id_category] = $path;
    return $path;
  }

  /**
   * Returns a string with all the paths for categories for a product in a language
   * for the selected shop. If $flat == false then returns them as an array.
   * @param int Product ID
   * @param int Language ID
   * @param int Shop ID
   * @param bool Optional implode values.
   * @return string or array
   */
  public static function getCategoriesForProductIdAndLanguage($id_product, $id_lang, $id_shop, $flat=true)
  {
    $sql = "SELECT c.id_category, c.id_parent, c.level_depth, c.nleft, c.nright
            FROM _DB_PREFIX_category_product AS pc
            INNER JOIN _DB_PREFIX_category AS c
              ON pc.id_category = c.id_category
            WHERE
              pc.id_product = $id_product
            ORDER BY
              c.nleft DESC, c.nright ASC;";
    $sql = self::prepareSQL($sql);

    $categories = array();
    $last_saved = 0;
    $id_category0 = 0;
    $nleft0 = 0;
    $nright0 = 0;

    foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $i => $row)
    {
      if (!$i)
      {
        $id_category0 = intval($row['id_category']);
        $nleft0 = intval($row['nleft']);
        $nright0 = intval($row['nright']);
      }
      else
      {
        $id_category1 = intval($row['id_category']);
        $nleft1 = intval($row['nleft']);
        $nright1 = intval($row['nright']);

        if ($nleft1 < $nleft0 && $nright1 > $nright0)
        {
          // $id_category1 is an ancestor of $id_category0
        }
        elseif ($nleft1 < $nleft0 && $nright1 > $nright0)
        {
          // $id_category1 is a child of $id_category0 so be replace $id_category0
          $id_category0 = $id_category1;
          $nleft0 = $nleft1;
          $nright0 = $nright1;
        }
        else
        {
          // $id_category1 is not a relative of $id_category0 so we save
          // $id_category0 now and make $id_category1 the current category.
          $categories[] = self::getCategoryPath($id_category0, $id_lang, $id_shop);
          $last_saved = $id_category0;

          $id_category0 = $id_category1;
          $nleft0 = $nleft1;
          $nright0 = $nright1;
        }
      }
    } // endforeach

    if ($last_saved != $id_category0)
      // The last item in loop didn't trigger the $id_category0 saving event.
      $categories[] = self::getCategoryPath($id_category0, $id_lang, $id_shop);

    return $flat ? implode(CATEGORY_SEPARATOR, $categories) : $categories;
  }

  //
  // Text Tools
  //

  public static function truncateText($text, $length)
  {
    $l = intval($length);
    $c = trim(preg_replace('/\s+/', ' ', $text));

    if (strlen($c) <= $l)
      return $c;

    $n = 0;
    $r = "";
    foreach (explode(' ', $c) as $p)
    {
      if (($tmp = $n + strlen($p) + 1) <= $l)
      {
        $n = $tmp;
        $r .= " $p";
      }
      else
        break;
    }

    return $r;
  }

  public static function cleanString($text)
  {
    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    $text = str_replace(array(chr(9), chr(10)), " ", $text);
    return trim(preg_replace('/[\t\s]+|[|\r\n]/', " ", $text));
  }

  /**
   * Cleans a string in an extreme way to deal with conflictive strings like
   * titles that contains references that can be searched with or without
   * certain characters.
   *
   * TODO: Make it configurable from the admin.
   */
  public static function cleanReferences($text)
  {
    $forbidden = array('-');
    return str_replace($forbidden, "", $text);
  }

  public static function splitReferences($text)
  {
    return preg_replace("/([^\d\s])([\d])/", "$1 $2", $text);
  }

  //
  // Things from request / URL Tools
  //

  public static function getLanguageFromRequest()
  {
    $context = Context::getContext();

    // lang is the OLDER param name. Here for compatibility.
    $id_lang = Tools::getValue('language', Tools::getValue('lang', $context->language->id));

    if (!is_numeric($id_lang))
      $id_lang = Language::getIdByIso($id_lang);

    return new Language($id_lang);
  }

  public static function getCurrencyForLanguage($code)
  {
    $optname = 'DF_GS_CURRENCY_'.strtoupper($code);
    $id_currency = Doofinder::cfg($optname, false);

    if ($id_currency)
      return new Currency(Currency::getIdByIsoCode($id_currency));

    return new Currency(Context::getContext()->currency->id);
  }

  public static function getCurrencyForLanguageFromRequest(Language $lang)
  {
    if ($id_currency = Tools::getValue('currency'))
    {
      if (is_numeric($id_currency))
        $id_currency = intval($id_currency);
      else
        $id_currency = Currency::getIdByIsoCode(strtoupper($id_currency));
    }
    else
    {
      $optname = 'DF_GS_CURRENCY_'.strtoupper($lang->iso_code);
      $id_currency = Currency::getIdByIsoCode(Configuration::get($optname));
    }

    if (!$id_currency)
    {
      $context = Context::getContext();
      $id_currency = $context->currency->id;
    }

    return new Currency($id_currency);
  }

  public static function getModuleLink($path, $ssl = false)
  {
    $context = Context::getContext();

    $base = (($ssl && $context->link->ssl_enable) ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_);

    return $base._MODULE_DIR_.basename(dirname(__FILE__))."/".$path;
  }

  public static function getFeedURL($langIsoCode)
  {
    $currency = self::getCurrencyForLanguage($langIsoCode);
    return self::getModuleLink('feed.php')."?language=".$langIsoCode."&currency=".$currency->iso_code;
  }

  public static function fixScriptTag($jsCode)
  {
    $result = trim(preg_replace('/<!--(.*?)-->/', '', $jsCode));
    if (strlen($result) && !preg_match('/<script([^>]*?)>/', $result))
      $result = "<script type=\"text/javascript\">\n$result\n</script>";
    return $result;
  }

  public static function fixStyleTag($cssCode)
  {
    $result = trim(preg_replace('/<!--(.*?)-->/', '', $cssCode));
    if (strlen($result) && !preg_match('/<style([^>]*?)>/', $result))
      $result = "<style type=\"text/css\">\n$result\n</style>";
    return $result;
  }

  public static function flush()
  {
    if (function_exists('flush'))
      flush();
    if (function_exists('ob_flush'))
      ob_flush();
  }
}
