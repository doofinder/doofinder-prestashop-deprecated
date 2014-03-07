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

define('CATEGORY_SEPARATOR', '%%');
define('CATEGORY_TREE_SEPARATOR', '>');
define('TXT_SEPARATOR', '|');

class dfTools
{
  // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
  const VALID_UTF8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|./x';

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
    $sql = "
      SELECT
        `name` AS DF_GS_IMAGE_SIZE,
        `name`
      FROM
        `_DB_PREFIX_image_type`
      WHERE
        `products` = 1
      ORDER BY
        `name`;
    ";

    foreach (Db::getInstance()->ExecuteS(self::prepareSQL($sql)) as $size)
      $sizes[$size['DF_GS_IMAGE_SIZE']] = $size;

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
    $sql = "
      SELECT
        `iso_code`,
        `name`
      FROM
        `_DB_PREFIX_currency`
      WHERE
        `active` = 1
      ORDER BY `name`;
    ";

    foreach (Db::getInstance()->ExecuteS(self::prepareSQL($sql)) as $currency)
      $currencies[$currency['iso_code']] = $currency;

    return $currencies;
  }


  /**
   * Returns the products available for a language
   * @param int Language ID.
   * @param int Optional. Default false. Number of products to get.
   * @param int Optional. Default false. Offset to start the select from.
   * @param string Optional. Fields to select.
   * @return array of rows (assoc arrays).
   */
  public static function getAvailableProductsForLanguage($id_lang, $id_shop, $limit=false, $offset=false)
  {
    $sql = "
      SELECT
        ps.id_product,
        ps.id_category_default,

        m.name AS manufacturer,

        p.__MPN__ AS mpn,

        pl.name,
        pl.description,
        pl.description_short,
        pl.meta_title,
        pl.meta_keywords,
        pl.meta_description,

        pl.link_rewrite,
        cl.link_rewrite AS cat_link_rew,

        im.id_image
      FROM
        _DB_PREFIX_product p
        INNER JOIN _DB_PREFIX_product_shop ps
          ON (p.id_product = ps.id_product AND ps.id_shop = _ID_SHOP_)
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON (p.id_product = pl.id_product AND pl.id_shop = _ID_SHOP_ AND pl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_manufacturer m
          ON (p.id_manufacturer = m.id_manufacturer)
        LEFT JOIN _DB_PREFIX_category_lang cl
          ON (p.id_category_default = cl.id_category AND cl.id_shop = _ID_SHOP_ AND cl.id_lang = _ID_LANG_)
        LEFT JOIN (_DB_PREFIX_image im INNER JOIN _DB_PREFIX_image_shop ims ON im.id_image = ims.id_image)
          ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND ims.cover = 1)
      WHERE
        ps.active = 1
        AND ps.visibility IN ('search', 'both')
      ORDER BY
        p.id_product
    ";

    $mpn_field = dfTools::cfg($id_shop, 'DF_GS_MPN_FIELD', 'reference');

    $sql = self::limitSQL($sql, $limit, $offset);
    $sql = self::prepareSQL($sql, array('_ID_LANG_' => $id_lang,
                                        '_ID_SHOP_' => $id_shop,
                                        '__MPN__' => $mpn_field));

    return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
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
   * @param bool Return full category path.
   * @return string
   */
  public static function getCategoryPath($id_category, $id_lang, $id_shop, $full = true)
  {
    if (isset(self::$cached_category_paths[$id_category]))
      return self::$cached_category_paths[$id_category];

    $sql = "
      SELECT
        cl.name
      FROM
        _DB_PREFIX_category_lang cl INNER JOIN _DB_PREFIX_category parent
          ON (parent.id_category = cl.id_category),
        _DB_PREFIX_category node
      WHERE
        node.nleft BETWEEN parent.nleft AND parent.nright
        AND node.id_category = _ID_CATEGORY_
        AND cl.id_shop = _ID_SHOP_
        AND cl.id_lang = _ID_LANG_
        AND parent.level_depth <> 0
        AND parent.active = 1
        AND parent.id_category NOT IN (_EXCLUDED_IDS_)
      ORDER BY
        parent.nleft
      ;
    ";

    $excluded_ids = implode(',', self::getRootCategoryIds($id_lang));
    $sql = self::prepareSQL($sql, array('_ID_CATEGORY_' => $id_category,
                                        '_ID_SHOP_' => $id_shop,
                                        '_ID_LANG_' => $id_lang,
                                        '_EXCLUDED_IDS_' => $excluded_ids));

    $path = array();
    foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $row)
      $path[] = str_replace(array(CATEGORY_TREE_SEPARATOR, CATEGORY_SEPARATOR),
                            "-", $row['name']);

    if ( $full )
      $path = implode(CATEGORY_TREE_SEPARATOR, $path);
    else
      $path = end($path);

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
    $sql = "
      SELECT
        c.id_category,
        c.id_parent,
        c.level_depth,
        c.nleft,
        c.nright
      FROM
        _DB_PREFIX_category c
        INNER JOIN _DB_PREFIX_category_product cp
          ON (c.id_category = cp.id_category AND cp.id_product = _ID_PRODUCT_)
        INNER JOIN _DB_PREFIX_category_shop cs
          ON (c.id_category = cs.id_category AND cs.id_shop = _ID_SHOP_)
      WHERE
        c.active = 1
      ORDER BY
        c.nleft DESC,
        c.nright ASC;
    ";
    $sql = self::prepareSQL($sql, array('_ID_PRODUCT_' => $id_product,
                                        '_ID_SHOP_' => $id_shop));

    $categories = array();
    $last_saved = 0;
    $id_category0 = 0;
    $nleft0 = 0;
    $nright0 = 0;
    $use_full_path = (bool) dfTools::cfg($id_shop, 'DF_FEED_FULL_PATH', Doofinder::YES);

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
          $categories[] = self::getCategoryPath($id_category0, $id_lang, $id_shop, $use_full_path);
          $last_saved = $id_category0;

          $id_category0 = $id_category1;
          $nleft0 = $nleft1;
          $nright0 = $nright1;
        }
      }
    } // endforeach

    if ($last_saved != $id_category0)
      // The last item in loop didn't trigger the $id_category0 saving event.
      $categories[] = self::getCategoryPath($id_category0, $id_lang, $id_shop, $use_full_path);

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

  public static function stripHtml($text)
  {
    $text = html_entity_decode($text, ENT_QUOTES, "ISO-8859-1");
    $text = preg_replace('/&#(\d+);/me',"chr(\\1)",$text);  // decimal notation
    $text = preg_replace('/&#x([a-f0-9]+);/mei',"chr(0x\\1)",$text);  // hex notation
    $text = str_replace("><", "> <", $text);
    $text = preg_replace('/\<br(\s*)?\/?\>/i', " ", $text);
    $text = strip_tags($text);

    return $text;
  }

  public static function cleanURL($text)
  {
    $text = trim($text);
    $text = explode("?", $text);

    $baseUrl = array();
    foreach (explode("/", $text[0]) as $part)
    {
      if (in_array(strtolower($part), array('http:', 'https:', '')))
        $baseUrl[] = $part;
      else
        $baseUrl[] = rawurlencode($part);
    }
    $text[0] = implode("/", $baseUrl);

    if (isset($text[1]))
    {
      $params = array();
      foreach (explode("&", $text[1]) as $param)
      {
        $param = explode("=", $param);
        foreach ($param as $idx => $part)
          $param[$idx] = urlencode($part);
        $params[] = implode("=", $param);
      }
      $text[1] = implode('&', $params);
    }

    $text = implode('?', $text);

    return preg_replace(self::VALID_UTF8, '$1', $text);
  }

  public static function cleanString($text)
  {
    $text = str_replace(TXT_SEPARATOR, "-", $text);
    $text = str_replace(array("\t", "\r", "\n"), " ", $text);

    $text = self::stripHtml($text);
    $text = preg_replace('/\s+/', " ", $text);

    $text = trim($text);
    $text = preg_replace('/^["\']+/', '', $text); // remove first quotes

    return preg_replace(self::VALID_UTF8, '$1', $text);
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

  /**
   * Returns a boolean value for the $parameter specified. If the parameter does
   * not exist (is NULL) then $default is returned instead.
   *
   * This method supports multiple ways of saying YES or NO.
   */
  public static function getBooleanFromRequest($parameter, $default = false)
  {
    $v = Tools::getValue($parameter, null);

    if ($v === null)
      return $default;

    switch (strtolower($v))
    {
      case 'false':
      case 'off':
      case 'no':
        return false;
      case 'true':
      case 'on':
      case 'yes':
      case 'si':
        return true;
      default:
        return (bool) $v;
    }
  }

  /**
   * Returns a Language object based on the 'language' parameter from the
   * request. If no language is found then the default one from the current
   * context is used.
   *
   * @return Language
   */
  public static function getLanguageFromRequest()
  {
    $context = Context::getContext();
    $id_lang = Tools::getValue('language', $context->language->id);

    if (!is_numeric($id_lang))
      $id_lang = Language::getIdByIso($id_lang);

    return new Language($id_lang);
  }

  /**
   * Returns a Currency object with the currency configured in the plugin for
   * the given ISO language $code parameter. If no currency is found the method
   * returns the default one for the current context.
   *
   * @param string $code ISO language code.
   * @return Currency
   */
  public static function getCurrencyForLanguage($code)
  {
    $optname = 'DF_GS_CURRENCY_'.strtoupper($code);
    $id_currency = Configuration::get($optname);

    if ($id_currency)
      return new Currency(Currency::getIdByIsoCode($id_currency));

    return new Currency(Context::getContext()->currency->id);
  }

  /**
   * Returns a Currency object based on the 'currency' parameter from the
   * request. If no currency is found then the function searches one in the
   * plugin configuration based on the $lang parameter. If none is configured
   * then the default one from the current context is used.
   *
   * @param Language $lang
   * @return Currency
   */
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

  /**
   * Returns a HTTP(S) link for a file from this module.
   * @param string $path file path relative to this module's root.
   * @param boolean $ssl Return a secure URL.
   * @return string URL
   */
  public static function getModuleLink($path, $ssl = false)
  {
    $context = Context::getContext();
    $shop = new Shop($context->shop->id);
    $base = (($ssl && $context->link->ssl_enable) ? 'https://' : 'http://') . $shop->domain;

    return $base._MODULE_DIR_.basename(dirname(__FILE__))."/".$path;
  }

  public static function fixURL($url)
  {
    if (preg_match('~^https?://~', $url) === 0)
      $url = "http://$url";

    return $url;
  }

  public static function getImageLink($id_product, $id_image, $link_rewrite, $image_size)
  {
    $context = Context::getContext();
    $url = $context->link->getImageLink($link_rewrite, "$id_product-$id_image", $image_size);
    return self::fixURL($url);
  }

  /**
   * Returns a data feed link for a given language ISO code. The link declares
   * the usage of the currency configured in the plugin by default.
   * @param string $langIsoCode ISO language code
   * @return string URL
   */
  public static function getFeedURL($langIsoCode)
  {
    $currency = self::getCurrencyForLanguage($langIsoCode);
    return self::getModuleLink('feed.php')."?language=".strtoupper($langIsoCode)."&currency=".strtoupper($currency->iso_code);
  }

  /**
   * Wraps a Javascript piece of code if no script tag is found.
   * @param string $jsCode Javascript code.
   * @return string
   */
  public static function fixScriptTag($jsCode)
  {
    $result = trim(preg_replace('/<!--(.*?)-->/', '', $jsCode));
    if (strlen($result) && !preg_match('/<script([^>]*?)>/', $result))
      $result = "<script type=\"text/javascript\">\n$result\n</script>";
    return $result;
  }

  /**
   * Wraps a CSS piece of code if no <style> tag is found.
   * @param string $cssCode CSS code.
   * @return string
   */
  public static function fixStyleTag($cssCode)
  {
    $result = trim(preg_replace('/<!--(.*?)-->/', '', $cssCode));
    if (strlen($result) && !preg_match('/<style([^>]*?)>/', $result))
      $result = "<style type=\"text/css\">\n$result\n</style>";
    return $result;
  }

  /**
   * Flush buffers
   * @return void
   */
  public static function flush()
  {
    if (function_exists('flush'))
      @flush();
    if (function_exists('ob_flush'))
      @ob_flush();
  }

  /**
   * Returns a configuration value for a $key and a $id_shop. If the value is
   * not found (or it's false) then returns a $default value.
   * @param integer $id_shop Shop id.
   * @param string $key Configuration variable name.
   * @param mixed $default Default value.
   * @return mixed
   */
  public static function cfg($id_shop, $key, $default = false)
  {
    $v = Configuration::get($key, null, null, $id_shop);
    if ($v === false)
      return $default;
    return $v;
  }

  public static function walk_apply_html_entities(&$item, $key)
  {
    if (is_string($item))
      $item = htmlentities($item);
  }

  public static function json_encode($data)
  {
    array_walk_recursive($data, array(get_class(), 'walk_apply_html_entities'));
    return str_replace("\\/", "/", html_entity_decode(json_encode($data)));
  }
}
