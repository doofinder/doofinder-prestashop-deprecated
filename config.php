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

require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

$context = Context::getContext();
$baseUrl = dfTools::getModuleLink('feed.php');

header("Content-Type:application/json; charset=utf-8");

$feeds = array();
$languages = array();
$currencies = array_keys(dfTools::getAvailableCurrencies());

foreach (Language::getLanguages(true, $context->shop->id) as $lang)
{
  $iso_code = strtoupper($lang['iso_code']);
  $feeds[$iso_code] = dfTools::getFeedURL($lang['iso_code']);
  $languages[] = $iso_code;
}

$cfg = array(
  "platform" => array(
    "name" => "Prestashop",
    "version" => _PS_VERSION_
  ),
  "module" => array(
    "version" => Doofinder::VERSION,
    "feeds" => $feeds,
    "options" => array(
      'offset' => 0,
      'limit' => 1000,
      'language' => $languages,
      'currency' => $currencies,
      'prices' => (bool) dfTools::cfg($context->shop->id, 'DF_GS_DISPLAY_PRICES', true),
      'taxes' => (bool) dfTools::cfg($context->shop->id, 'DF_GS_PRICES_USE_TAX', true),
    ),
  ),
);

echo dfTools::json_encode($cfg);
