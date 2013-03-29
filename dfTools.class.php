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
    $sql = self::prepareSQL("SELECT `name` FROM `_DB_PREFIX_image_type` WHERE `products` = 1 ORDER BY `name`;");

    foreach (Db::getInstance()->ExecuteS($sql) as $size)
    {
      $sizes[$size['name']] = $size['name'];
    }
    return $sizes;
  }

  public static function getAvailableCurrencies()
  {
    $currencies = array();

    $sql = self::prepareSQL("SELECT `iso_code`, `name` FROM `_DB_PREFIX_currency` WHERE `active` = 1 ORDER BY `name`;");

    foreach (Db::getInstance()->ExecuteS($sql) as $currency)
    {
      $currencies[$currency['iso_code']] = $currency['name'];
    }

    return $currencies;
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
    $blank = " ";
    $text = trim($text);
    $text = preg_replace('#<br ?/?>#isU', $blank, $text);
    $text = preg_replace('/(\r\n|\n|\r)/', $blank, $text);
    $text = strip_tags(html_entity_decode($text, ENT_QUOTES, 'UTF-8'));
    $text = ereg_replace("&euro;", "€", $text);
    $text = preg_replace('#\t+#', $blank, $text);
    $text = preg_replace('#\t+#', $blank, $text);
    $text = preg_replace('#'.CHR(10).'+#', $blank,$text);
    $text = str_replace(CHR(9), $blank, $text);
    $text = preg_replace('# +#', $blank, $text);
    $text = str_replace('|', $blank, $text);
    return trim($text);
  }
}
