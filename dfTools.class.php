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
  // General Shop Info
  //

  public static function getAvailableImageSizes()
  {
    $sizes = array();
    $sql = "
      SELECT
        `name`
      FROM
        `_DB_PREFIX_image_type`
      WHERE
        `products` = 1
      ORDER BY `name`;
    ";

    foreach (Db::getInstance()->ExecuteS(self::prepareSQL($sql)) as $size)
      $sizes[$size['name']] = $size['name'];

    return $sizes;
  }

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
    {
      $currencies[$currency['iso_code']] = $currency['name'];
    }

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
  public static function getAvailableProductsForLanguage($id_lang, $limit=false, $offset=false)
  {
    $sql = "
      SELECT
        p.id_product,
        p.id_category_default,

        m.name AS manufacturer,

        IF(p.ean13, p.ean13, p.upc) AS product_code,
        p.ean13,
        p.supplier_reference,

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
        LEFT JOIN _DB_PREFIX_product_lang pl
          ON (p.id_product = pl.id_product AND pl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_manufacturer m
          ON (p.id_manufacturer = m.id_manufacturer)
        LEFT JOIN _DB_PREFIX_category_lang cl
          ON (p.id_category_default = cl.id_category AND cl.id_lang = _ID_LANG_)
        LEFT JOIN _DB_PREFIX_image im
          ON (p.id_product = im.id_product AND im.cover = 1)
      WHERE
        p.active = 1
      ORDER BY
        p.id_product
    ";

    $sql = self::limitSQL($sql, $limit, $offset);
    $sql = self::prepareSQL($sql, array('_ID_LANG_' => $id_lang));

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
      $sql = "
        SELECT
          id_category
        FROM
          _DB_PREFIX_category
        WHERE
          id_parent = 0;
      ";

      $sql = self::prepareSQL($sql);

      self::$root_category_ids = array();
      foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $category)
        self::$root_category_ids[] = $category['id_category'];
    }

    return self::$root_category_ids;
  }

  /**
   * Returns the path to the first, no root ancestor category for the selected
   * category ID in a language.
   * Results are cached by category ID.
   *
   * @param int Category ID
   * @param int Language ID
   * @return string
   */
  public static function getCategoryPath($id_category, $id_lang)
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
                                        '_ID_LANG_' => $id_lang,
                                        '_EXCLUDED_IDS_' => $excluded_ids));

    $path = array();
    foreach (Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql) as $row)
      $path[] = str_replace(array(CATEGORY_SEPARATOR, CATEGORY_TREE_SEPARATOR),
                            "-", $row['name']);
    $path = implode(CATEGORY_TREE_SEPARATOR, $path);

    $path = self::cleanString($path);

    self::$cached_category_paths[$id_category] = $path;
    return $path;
  }

  /**
   * Returns a string with all the paths for categories for a product in a
   * language. If $flat == false then returns them as an array.
   *
   * @param int Product ID
   * @param int Language ID
   * @param bool Optional implode values.
   * @return string or array
   */
  public static function getCategoriesForProductIdAndLanguage($id_product, $id_lang, $flat=true)
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
      WHERE
        c.active = 1
      ORDER BY
        c.nleft DESC,
        c.nright ASC
      ;
    ";

    $sql = self::prepareSQL($sql, array('_ID_PRODUCT_' => $id_product));

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
          $categories[] = self::getCategoryPath($id_category0, $id_lang);
          $last_saved = $id_category0;

          $id_category0 = $id_category1;
          $nleft0 = $nleft1;
          $nright0 = $nright1;
        }
      }
    } // endforeach

    if ($last_saved != $id_category0)
      // The last item in loop didn't trigger the $id_category0 saving event.
      $categories[] = self::getCategoryPath($id_category0, $id_lang);

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
    $text = preg_replace('/\<br(\s*)?\/?\>/i', $blank, $text);
    $text = strip_tags($text);

    return $text;
  }

  public static function cleanString($text, $is_link = false)
  {
    // http://stackoverflow.com/questions/4224141/php-removing-invalid-utf-8-characters-in-xml-using-filter
    $valid_utf8 = '/([\x09\x0A\x0D\x20-\x7E]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})|./x';

    $blank = $is_link ? "" : " ";
    $sep_r = $is_link ? urlencode(TXT_SEPARATOR) : " - ";

    $text = str_replace(TXT_SEPARATOR, $sep_r, $text);
    $text = str_replace(array("\t", "\r", "\n", chr(9), chr(10)), $blank, $text);

    if ($is_link)
    {
      $text = str_replace(" ", $blank, $text);
    }
    else
    {
      $text = self::stripHtml($text);
      $text = preg_replace('/\s+/', $blank, $text);
    }

    $text = trim($text);
    // remove first quotes
    $text = preg_replace('/^["\']+/', '', $text);

    return preg_replace($valid_utf8, '$1', $text);
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
    global $cookie;

    // lang is the OLDER param name. Here for compatibility.
    $id_lang = Tools::getValue('language', (int) $cookie->id_lang);

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
    global $cookie;

    $optname = 'DF_GS_CURRENCY_'.strtoupper($code);
    $id_currency = Doofinder::cfg($optname, false);

    if ($id_currency)
      return new Currency(Currency::getIdByIsoCode($id_currency));

    return new Currency($cookie->id_currency);
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
    global $cookie;

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
      $id_currency = $cookie->id_currency;
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
    global $link;

    $base = (($ssl && $link->ssl_enable) ? _PS_BASE_URL_SSL_ : _PS_BASE_URL_);

    return $base._MODULE_DIR_.basename(dirname(__FILE__))."/".$path;
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
   * Wraps a Javascript piece of code if no <script> tag is found.
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
   * Returns a configuration value for a $key. If the value is not found (or
   * it's false) then returns a $default value.
   * @param string $key Configuration variable name.
   * @param mixed $default Default value.
   * @return mixed
   */
  public static function cfg($key, $default = false)
  {
    $v = Configuration::get($key);
    if ($v === false)
      return $default;
    return $v;
  }

  public static function json_encode($data)
  {
    array_walk_recursive($data, function(&$item, $key) {
      if (is_string($item))
        $item = htmlentities($item);
    });

    return str_replace("\\/", "/", html_entity_decode(json_encode($data)));
  }
}
