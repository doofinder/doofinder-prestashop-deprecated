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

/**
 * Accepted parameters:
 *
 * - limit:      Max results in this request.
 * - offset:     Zero-based position to start getting results.
 * - chunk_size: In FETCH_MODE_FAST, the same as limit if limit is not present.
 * - language:   Language ISO code, like "es" or "en"
 * - currency:   Currency ISO code, like "EUR" or "GBP"
 */

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

$fetchMode = Doofinder::cfg('DF_FETCH_FEED_MODE', Doofinder::FETCH_MODE_ALT1);

$limit = Tools::getValue('limit', false);

if ($limit !== false && intval($limit) > 0)
  $fetchMode = Doofinder::FETCH_MODE_ALT1;

if ($fetchMode == Doofinder::FETCH_MODE_FAST)
{
  $context = Context::getContext();

  $shop = new Shop((int) $context->shop->id);
  if (!$shop->id)
    die('NOT PROPERLY CONFIGURED');

  $lang = dfTools::getLanguageFromRequest();
  $currency = dfTools::getCurrencyForLanguageFromRequest($lang);

  $chunk_size = intval(Tools::getValue('chunk_size', 1000));
  $nb_rows = dfTools::countAvailableProductsForLanguage($lang->id);

  $baseUrl = dfTools::getModuleLink('feed_part.php');

  for ($offset = 0; $offset < $nb_rows; $offset += $chunk_size)
  {
    $url = $baseUrl."?language=".$lang->id."&currency=".$currency->id."&limit=".$chunk_size."&offset=".$offset;
    $fp = fopen($url, "r");

    if ($offset == 0)
    {
      if ($fp === false)
      {
        $fetchMode = Doofinder::FETCH_MODE_ALT1;
        break;
      }
      else
      {
        if (isset($_SERVER['HTTPS']))
          header('Strict-Transport-Security: max-age=500');

        header("Content-Type:text/plain; charset=utf-8");
      }
    }

    while (false !== ($line = fgets($fp)))
    {
      echo $line;
      flush();ob_flush();
    }
    fclose($fp);
  }
}

if ($fetchMode == Doofinder::FETCH_MODE_ALT1)
{
  require_once(dirname(__FILE__) . '/feed_alt1.php');
}

if ($fetchMode == Doofinder::FETCH_MODE_ALT2)
{
  require_once(dirname(__FILE__) . '/feed_alt2.php');
}
