<?php
require_once(dirname(__FILE__) . '/../../config/config.inc.php');
require_once(dirname(__FILE__) . '/../../init.php');
require_once(dirname(__FILE__) . '/doofinder.php');

define('TXT_SEPARATOR', '|');

$baseUrl = dfTools::getBaseUrl($_SERVER)."/feed1_part.php";

$sql = "SELECT a.id_product
        FROM ps_product a";
$rows = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

$a = array();
$numPaquetes = 15;
$blocks = array_chunk($rows, ceil(count($rows) / $numPaquetes));
foreach($blocks as $k => $rows){
    foreach($rows as $row){
        $a[$k][] = $row['id_product'];
    }
}

header("Content-Type:text/plain; charset=utf-8");

$header = array('id', 'title', 'link', 'description', 'price', 'sale_price', 'image_link', 'categories', 'availability', 'brand', 'gtin', 'mpn', 'extra_title');
echo implode(TXT_SEPARATOR, $header) . PHP_EOL;

foreach($a as $ids){
    echo file_get_contents($baseUrl.'?ids='.implode(',',$ids));
    flush();ob_flush();
}
