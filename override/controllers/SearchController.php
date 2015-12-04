<?php

class SearchController extends SearchControllerCore{
    
    public function preProcess()
    {
        $query = Tools::getValue('search_query', Tools::getValue('ref'));
        $overwrite_search = Configuration::get('DF_OWSEARCH', null);
        $m = Module::getInstanceByName('doofinder');
        $this->p = abs((int)(Tools::getValue('p', 1)));
        $this->n = abs((int)(Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
        $enabled = Db::getInstance()->getRow('SELECT active FROM `'._DB_PREFIX_.'module` 
                 WHERE `name` = \''.pSQL('doofinder').'\'');	

        if ($enabled && ($search = $m->searchOnApi($query,$this->p,$this->n)) 
                && $overwrite_search && $query
                && !is_array($query)){
					
            FrontController::preProcess();
            
            $original_query = $query;
            $query = urldecode($query);
           
            foreach ($search['result'] as &$product)
                    $product['link'] .= (strpos($product['link'], '?') === false ? '?' : '&').'search_query='.urlencode($query).'&results='.(int)$search['total'];
            
            $nbProducts = $search['total'];
            $this->pagination($nbProducts);

                
            self::$smarty->assign(array(
                    'products' => $search['result'], // DEPRECATED (since to 1.4), not use this: conflict with block_cart module
                    'search_products' => $search['result'],
                    'nbProducts' => $search['total'],
                    'search_query' => $original_query,
                    'homeSize' => Image::getSize('home')));

            self::$smarty->assign(array('add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'), 'comparator_max_item' => Configuration::get('PS_COMPARATOR_MAX_ITEM')));

        }else{
            parent::preProcess();
        }
        
        
    }
}
