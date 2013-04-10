<?php
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

$context = Context::getContext();

$shop = new Shop((int) $context->shop->id);
if (!$shop->id)
  die('NOT PROPERLY CONFIGURED');

$lang = dfTools::getLanguageFromRequest();
$currency = dfTools::getCurrencyForLanguageFromRequest($lang);

$limit = intval(Tools::getValue('limit', 500));
$nb_rows = dfTools::countAvailableProductsForLanguage($lang->id);

$baseUrl = dfTools::getBaseUrl($_SERVER)."/feed_part.php";

if (isset($_SERVER['HTTPS']))
{
  header('Strict-Transport-Security: max-age=500');
}
header("Content-Type:text/plain; charset=utf-8");

for ($offset = 0; $offset < $nb_rows; $offset += $limit)
{
  $url = $baseUrl."?lang=".$lang->id."&currency=".$currency->id."&limit=".$limit."&offset=".$offset;
  $fp = fopen($url, "r");
  while (false !== ($line = fgets($fp)))
  {
    echo $line;
    flush();ob_flush();
  }
  fclose($fp);
}
