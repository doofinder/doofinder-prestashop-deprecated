<?php
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

  //
  // General Shop Info
  //

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

  public static function getAvailableProductsForLanguage($id_lang, $limit=false, $offset=false)
  {
    $sql = "SELECT *
            FROM _DB_PREFIX_product p
            LEFT JOIN _DB_PREFIX_product_lang pl
              ON p.id_product = pl.id_product
            WHERE p.active = 1
              AND pl.id_lang = _ID_LANG_
            ORDER BY p.id_product";

    if (false !== $limit && is_numeric($limit))
    {
      $sql .= " LIMIT ".intval($limit);
    }

    if (false !== $offset && is_numeric($offset))
    {
      $sql .= " OFFSET ".intval($offset);
    }

    $sql = self::prepareSQL($sql, array('_ID_LANG_' => $id_lang)).";";

    return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
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
  public static function purgeString($text)
  {
    $forbidden = array('-');
    return str_replace($forbidden, "", $text);
  }

  public static function getLanguageFromRequest($param = 'lang')
  {
    $context = Context::getContext();

    $id_lang = Tools::getValue($param);
    if (!is_numeric($id_lang))
      $id_lang = intval($id_lang ? Language::getIdByIso($id_lang) : (int) $context->language->id);

    return new Language($id_lang);
  }

  public static function getCurrencyForLanguageFromRequest(Language $lang, $param = 'currency')
  {
    if ($id_currency = Tools::getValue($param))
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

  public static function getBaseUrl(array $serverInfo)
  {
    $baseUrl = isset($serverInfo['HTTPS']) ? "https://" : "http://";
    $baseUrl .= $serverInfo['SERVER_NAME'];
    $baseUrl .= dirname($serverInfo['REQUEST_URI']);

    return $baseUrl;
  }
}
