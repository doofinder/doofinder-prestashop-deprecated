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

if (!defined('_PS_VERSION_'))
  exit;

if (!class_exists('dfTools'))
  require_once(dirname(__FILE__).'/dfTools.class.php');

class Doofinder extends Module
{
  protected $_html = '';
  protected $_postErrors = array();
  protected $_productLinks = array();

  const GS_SHORT_DESCRIPTION = 1;
  const GS_LONG_DESCRIPTION = 2;
  const VERSION = "2.2.1";
  const YES = 1;
  const NO = 0;

  public function __construct()
  {
    $this->name = "doofinder";
    $this->tab = "search_filter";
    $this->version = self::VERSION;
    $this->author = "Doofinder (http://www.doofinder.com)";
    $this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');

    parent::__construct();

    $this->displayName = 'Doofinder';
    $this->description = $this->l('Install Doofinder in your shop with no effort.');

    $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service.');
  }


  public function install()
  {
    if (!parent::install() ||
        !$this->registerHook('header') ||
        !$this->registerHook('displayMobileTopSiteMap'))
      return false;
    
    if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true) {
        // Hook the module either on the left or right column
        $theme = new Theme(Context::getContext()->shop->id_theme);
        if ((!$theme->default_left_column || !$this->registerHook('leftColumn')) && (!$theme->default_right_column || !$this->registerHook('rightColumn'))) {
            // If there are no colums implemented by the template, throw an error and uninstall the module
            $this->_errors[] = $this->l('This module need to be hooked in a column and your theme does not implement one if you want Search Facets');
        }
    } else
        $this->registerHook('leftColumn');

    return true;
  }

  private function configureHookCommon($params)
  {
    $lang = strtoupper($this->context->language->iso_code);
    $script = $this->cfg("DOOFINDER_SCRIPT_$lang");
    $extra_css = $this->cfg('DF_EXTRA_CSS');

    $this->smarty->assign(array(
      'ENT_QUOTES' => ENT_QUOTES,
      'lang' => strtolower($lang),
      'script' => dfTools::fixScriptTag($script),
      'extra_css' => dfTools::fixStyleTag($extra_css),
      'productLinks' => $this->_productLinks,
      'self' => dirname(__FILE__),
    ));

    return true;
  }

  public function hookHeader($params)
  {
    $this->configureHookCommon($params);
    if(isset($this->context->controller->php_self) && $this->context->controller->php_self == 'search' ){
        
        $overwrite_search = Configuration::get('DF_OWSEARCH', null);
        $overwrite_facets = Configuration::get('DF_OWSEARCHFAC', null);
        if ($overwrite_search && $overwrite_facets){
            $css_path = str_replace('doofinder', 'blocklayered',$this->_path);
            if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true){
                $this->context->controller->addJS(($this->_path) . 'js/doofinder-pagination.js');
                if (file_exists(_PS_MODULE_DIR_.'blocklayered/blocklayered.css')){
                    $this->context->controller->addCSS($css_path.'blocklayered.css', 'all');
                }else{
                    $this->context->controller->addCSS(($this->_path) . 'css/doofinder-filters.css', 'all');
                }
            }else{
                $this->context->controller->addJS(($this->_path) . 'js/doofinder-pagination_15.js');
                if (file_exists(_PS_MODULE_DIR_.'blocklayered/blocklayered-15.css')){
                    $this->context->controller->addCSS($css_path.'blocklayered-15.css', 'all');
                }else{       
                    $this->context->controller->addCSS(($this->_path) . 'css/doofinder-filters-15.css', 'all');
                }

            }
            $this->context->controller->addJS(($this->_path) . 'js/doofinder_facets.js');
        }
        $this->context->controller->addJS(($this->_path) . 'js/js.cookie.js');
        $this->context->controller->addJQueryUI('ui.slider');
        $this->context->controller->addJQueryUI('ui.accordion');
        $this->context->controller->addJqueryPlugin('multiaccordion');
        $this->context->controller->addJQueryUI('ui.sortable');
        $this->context->controller->addJqueryPlugin('jscrollpane');
        
        $this->context->controller->addJQueryPlugin('scrollTo');
    }
    return $this->display(__FILE__, 'script.tpl');
  }

  //
  // Configuration & Validation
  //

  /**
   * This method renders the configuration page in the backoffice.
   */
  public function getContent()
  {
    $this->addCSS('css/doofinder.css');

    
    if (isset($_POST['submit'.$this->name]))
    {
      $this->_updateConfiguration();

      if (!count($this->_postErrors))
      {
        $doofinder_hash = Configuration::get('DF_FEED_HASH');
        $enable_hash = Configuration::get('DF_ENABLE_HASH', null);
        $this->_html .= $this->displayConfirmation($this->l('Settings updated!'));
        $this->_html .= $this->displayError($this->l('IF YOU HAVE CHANGED ANYTHING IN YOUR DATA FEED SETTINGS, REMEMBER YOU MUST REPROCESS.'));
        if(!empty($doofinder_hash) && $enable_hash){
            $this->_html .= $this->displayError($this->l('CHECK ALSO THAT THE NEW FEED URL IS THE SAME THAT ON YOUR DOOFINDER PANEL.'));
        }
      }

      else
      {
        foreach ($this->_postErrors as $error)
          $this->_html .= $this->displayError($error);
      }
    }

    $this->_displayForm();

    return $this->_html;
  }

  protected function addCSS($url)
  {
    $this->_html .= '<link rel="stylesheet" type="text/css" href="'.__PS_BASE_URI__.'modules/doofinder/'.$url.'" />';
  }

  /**
   * Validates configuration form input and creates validation output.
   * Saves valid configuration values found.
   */
  protected function _updateConfiguration()
  {
      
    $df_invalid_msg = $this->l('Please, select a valid option for %s.');
    $df_required_msg = $this->l('%s field is mandatory.');

    $cfgIntValues = array(
      'DF_GS_DESCRIPTION_TYPE' => $this->l('Product Description Length'),
      'DF_GS_DISPLAY_PRICES' => $this->l('Display Prices in Data Feed'),
      'DF_GS_PRICES_USE_TAX' => $this->l('Display Prices With Taxes'),
      'DF_FEED_FULL_PATH' => $this->l('Export full categories path in the feed'),
      'DF_SHOW_PRODUCT_VARIATIONS' => $this->l('Include product variations in feed'),
      'DF_SHOW_PRODUCT_FEATURES' => $this->l('Include product features in feed'),
      'DF_OWSEARCH' => $this->l('Overwrite Search page with Doofinder results'),
      'DF_OWSEARCHFAC' => $this->l('Enable facets on Overwrite Search Page'),
      'DF_ENABLE_HASH' => $this->l('Enable security hash on feed URL'),
      'DF_DEBUG' => $this->l('Activate to write debug info in file.')
      );

    foreach ($cfgIntValues as $optname => $optname_alt)
    {
      $optvalue = Tools::getValue($optname);

      if (Validate::isGenericName($optvalue) && (empty($optvalue) || is_numeric($optvalue)))
      {
        Configuration::updateValue($optname, $optvalue);
      }
      else
      {
        $this->_postErrors[] = sprintf($df_invalid_msg, $optname_alt);
      }
    }
    
    $doofinder_hash = Configuration::get('DF_FEED_HASH');
    if(empty($doofinder_hash)){
        $enable_hash = Configuration::get('DF_ENABLE_HASH', null);
        if($enable_hash){
            $doofinder_hash = md5('PrestaShop_Doofinder_'.date('YmdHis'));
            Configuration::updateValue('DF_FEED_HASH',$doofinder_hash);
        }
    }
    

    $cfgStrSelectValues = array(
      'DF_GS_IMAGE_SIZE' => array( // Image Size
        'valid' => array_keys(dfTools::getAvailableImageSizes()),
        'label' => $this->l('Product Image Size'),
        ),
      'DF_GS_MPN_FIELD' => array(
        'valid' => array('reference', 'supplier_reference', 'ean13', 'upc'),
        'label' => $this->l('MPN Field for Data Feed'),
        ),
      'DF_FEATURES_SHOWN' => array(
        'label' => 'Features',
        ),
      );

    foreach ($cfgStrSelectValues as $optname => $cfg)
    {
      $optvalue = Tools::getValue($optname);
      
      if ($optname === "DF_FEATURES_SHOWN")
      {
        if($optvalue)
          Configuration::updateValue($optname, implode(',', $optvalue));
        else
          Configuration::deleteByName($optname);
      }

      else if (dfTools::isBasicValue($optvalue))
      {
        
        if (in_array($optvalue, $cfg['valid']))
        {
          Configuration::updateValue($optname, $optvalue);
        }
        else
        {
          $this->_postErrors[] = sprintf($df_invalid_msg, $cfg['label']);
        }
      }
      else
      {
        $this->_postErrors[] = sprintf($df_required_msg, $cfg['label']);
      }
    }

    $cfgLangStrValues = array('DOOFINDER_SCRIPT_' => true, 'DF_GS_CURRENCY_' => false, 'DF_HASHID_' => false);
    foreach ($cfgLangStrValues as $prefix => $html)
    {
      foreach (Language::getLanguages(true, $this->context->shop->id) as $lang)
      {
        $optname = $prefix.strtoupper($lang['iso_code']);
        // Cleaning script tags
        if ('DOOFINDER_SCRIPT_' === $prefix){
          $value = str_replace('<script type="text/javascript">', '', Tools::getValue($optname));
          $value = str_replace("<script type='text/javascript'>", '', Tools::getValue($optname));
          $value = str_replace('</script>', '', $value);
        }
        else{
          $value = Tools::getValue($optname);
        }
        Configuration::updateValue($optname, $value, $html);
      }
    }

    $cfgCodeStrValues = array(
        'DF_EXTRA_CSS',
        'DF_API_KEY',
        'DF_CUSTOMEXPLODEATTR'
      );

    foreach ($cfgCodeStrValues as $optname)
    {
      $optvalue = Tools::getValue($optname);
      Configuration::updateValue($optname, $optvalue, true);
    }

    $cfgStrValues = array();

    foreach ($cfgStrValues as $optname => $optname_alt)
    {
      $optvalue = Tools::getValue($optname);

      if (Validate::isGenericName($optvalue) || empty($optvalue))
      {
        Configuration::updateValue($optname, $optvalue);
      }
      else
      {
        $this->_postErrors[] = sprintf($df_invalid_msg, $optname_alt);
      }
    }
  }


  /**
   * Configures the settings form and generates its output.
   */
  protected function _displayForm()
  {
    $helper = new HelperForm();
    $default_lang_id = (int) $this->cfg('PS_LANG_DEFAULT', 1);
    $default_currency = Currency::getDefaultCurrency();

    //
    // DATA FEED SETTINGS
    //

    $fields = array();

    $fields_form[0]['form'] = array(
      'legend' => array('title' => $this->l('Data Feed Settings')),
      'input'  => null,
      'submit' => array('title' => $this->l('Save'), 'class' => 'button'),
      );


    // DF_GS_IMAGE_SIZE
    $optname = 'DF_GS_IMAGE_SIZE';
    $fields[] = array(
      'label' => $this->l('Product Image Size'),

      'type' => 'select',
      'options' => array(
        'query' => dfTools::getAvailableImageSizes(),
        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      'required' => true,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);


    // DF_GS_DESCRIPTION_TYPE
    $optname = 'DF_GS_DESCRIPTION_TYPE';
    $fields[] = array(
      'label' => $this->l('Product Description Length'),

      'type' => 'select',
      'options' => array(
        'query' => array(
          array($optname => self::GS_SHORT_DESCRIPTION, 'name' => $this->l('Short')),
          array($optname => self::GS_LONG_DESCRIPTION, 'name' => $this->l('Long')),
          ),
        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);

    // DF_GS_CURRENCY_<LANG>
    $optname = 'DF_GS_CURRENCY_';
    foreach (Language::getLanguages(true, $this->context->shop->id) as $lang)
    {
      $realoptname = $optname.strtoupper($lang['iso_code']);
      $fields[] = array(
        'label' => sprintf($this->l("Currency for %s"), $lang['name']),
        'type' => 'select',
        'options' => array(
          'query' => dfTools::getAvailableCurrencies(),
          'id' => 'iso_code',
          'name' => 'name',
          ),
        'name' => $realoptname,
        'required' => true,
        );
      $helper->fields_value[$realoptname] = $this->cfg($realoptname, $default_currency->iso_code);
    }

    // DF_GS_DISPLAY_PRICES
    $optname = 'DF_GS_DISPLAY_PRICES';
    $field = $this->getYesNoSelectFor($optname, $this->l('Display Prices in Data Feed'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::YES);


    // DF_GS_PRICES_USE_TAX
    $optname = 'DF_GS_PRICES_USE_TAX';
    $field = $this->getYesNoSelectFor($optname, $this->l('Display Prices With Taxes'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::YES);

    // DF_SHOW_PRODUCT_VARIATIONS
    $optname = 'DF_SHOW_PRODUCT_VARIATIONS';
    $fields[] = array(
      'label' => $this->l('Include product variations in feed'),

      'type' => 'select',
      'options' => array(
        'query' => array(
          array($optname => '0', 'name' => $this->l('No, only product')),
          array($optname => '1', 'name' => $this->l('Yes, Include each variations')),
          array($optname => '2', 'name' => $this->l('Only product but all possible attribute for them')),
        ),

        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      'required' => true,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);
    
    

    // DF_SHOW_PRODUCT_FEATURES
    $optname = 'DF_SHOW_PRODUCT_FEATURES';
    $field = $this->getYesNoSelectFor($optname, $this->l('Include product features in feed'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::NO);

    // DF_OWSEARCH
    $optname = 'DF_OWSEARCH';
    $field = $this->getYesNoSelectFor($optname, $this->l('Overwrite Search page with Doofinder results'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::NO);
    
    // DF_OWSEARCHFAC
    $optname = 'DF_OWSEARCHFAC';
    $field = $this->getYesNoSelectFor($optname, $this->l('Enable facets on Overwrite Search Page'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::NO);
    
    // DF_ENABLE_HASH
    $optname = 'DF_ENABLE_HASH';
    $field = $this->getYesNoSelectFor($optname, $this->l('Enable security hash on feed URL'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::NO);

    // DF_DEBUG
    $optname = 'DF_DEBUG';
    $field = $this->getYesNoSelectFor($optname, $this->l('Debug Mode. Write info logs in doofinder.log file.'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::NO);

    // DF_GS_MPN_FIELD
    $optname = 'DF_GS_MPN_FIELD';
    $fields[] = array(
      'label' => $this->l('MPN Field for Data Feed'),

      'type' => 'select',
      'options' => array(
        'query' => array(
          array($optname => 'reference', 'name' => 'reference'),
          array($optname => 'supplier_reference', 'name' => 'supplier_reference'),
          array($optname => 'upc', 'name' => 'upc'),
          array($optname => 'ean13', 'name' => 'ean13'),
        ),

        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      'required' => true,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);


    // DF_FEED_FULL_PATH
    $optname = 'DF_FEED_FULL_PATH';
    $field = $this->getYesNoSelectFor($optname, $this->l('Export full categories path in the feed'));
    $fields[] = $field;
    $helper->fields_value[$optname] = $this->cfg($optname, self::YES);
    // PS FEATURES SHOWN
    $optname = 'DF_FEATURES_SHOWN';
    $features = dfTools::getFeatureKeysForShopAndLang($this->context->shop->id, $lang['id_lang']);
    $opts = array();
    
    foreach($features as $key => $feature)
    {  
        $opts[] = array($optname => $key, 'name' => $feature);
    }

    $fields[] = array(
      'label' => 'Select features will be shown in feed',
      'type' => 'select',
      'multiple' => true,
      'options' => array(
        'query' => $opts,

        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname.'[]',
      'required' => false

    );

    $helper->fields_value[$optname . '[]'] = explode(',', $this->cfg($optname));
    
    $fields_form[0]['form']['input'] = $fields;


    //
    // DOOFINDER SCRIPTS
    //

    $fields = array();

    $fields_form[1]['form'] = array(
      'legend' => array('title' => $this->l('Doofinder Script')),
      'input'  => null,
      'submit' => array('title' => $this->l('Save'), 'class' => 'button'),
      );


    // DOOFINDER_SCRIPT
    $optname = 'DOOFINDER_SCRIPT_';
    $desc = $this->l('Paste the script as you got it from Doofinder.');
    $doofinder_hash = Configuration::get('DF_FEED_HASH');
    $enable_hash = Configuration::get('DF_ENABLE_HASH', null);
    foreach (Language::getLanguages(true, $this->context->shop->id) as $lang)
    {
      $realoptname = $optname.strtoupper($lang['iso_code']);
      $url = dfTools::getFeedURL($lang['iso_code']);
      if(!empty($doofinder_hash) && $enable_hash){
          $url.='&dfsec_hash='.$doofinder_hash;
      }
      $fields[] = array(
        'label' => $lang['name'],
        'desc' => sprintf('<span class="df-notice"><b>%s [%s]:</b> <a href="%s" target="_blank">%s</a></span>%s', $this->l('Data Feed URL'), strtoupper($lang['iso_code']), $url, htmlentities($url), $desc),

        'type' => 'textarea',
        'cols' => 100,
        'rows' => 10,
        'name' => $realoptname,
        'required' => false,
        );

      $helper->fields_value[$realoptname] = $this->cfg($realoptname);
      
        // DF_HASHID_LANG
        $real_optname_hash = 'DF_HASHID_'.strtoupper($lang['iso_code']);
        $fields[] = array(
          'label' => $this->l('Hash ID').' ('.strtoupper($lang['iso_code']).')',
          'desc' => $this->l('Hash ID, needed to overwrite Search page.'),
          'type' => 'text',
          'name' => $real_optname_hash,
          'required' => false,
          'size' => 100,
          );
        $helper->fields_value[$real_optname_hash] = $this->cfg($real_optname_hash);
    }

    // DF_EXTRA_CSS
    $optname = 'DF_EXTRA_CSS';
    $fields[] = array(
      'label' => $this->l('Extra CSS'),
      'desc' => $this->l('Extra CSS to adjust Doofinder to your template.'),
      'type' => 'textarea',
      'cols' => 100,
      'rows' => 10,
      'name' => $optname,
      'required' => false,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);
    
    // DF_API_KEY
    $optname = 'DF_API_KEY';
    $fields[] = array(
      'label' => $this->l('Api Key'),
      'desc' => $this->l('Api Key, needed to overwrite Search page.'),
      'type' => 'text',
      'name' => $optname,
      'required' => false,
      'size' => 100,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);

    
    
    
    // DF_CUSTOMEXPLODEATTR
    $optname = 'DF_CUSTOMEXPLODEATTR';
    $fields[] = array(
      'label' => $this->l('Custom separator attribute'),
      'desc' => $this->l('Used if your feed have a custom separator to concatenate id_product and id_product_attribute .'),
      'type' => 'text',
      'name' => $optname,
      'required' => false,
      );
    $helper->fields_value[$optname] = $this->cfg($optname);

    $fields_form[1]['form']['input'] = $fields;


    //
    // OTHER SETTINGS
    //

    // Module, token, index
    $helper->module = $this;
    $helper->name_controller = $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;


    // Language
    $helper->default_form_language = $default_lang_id;
    $helper->allow_employee_form_lang = $default_lang_id;


    // Title and toolbar
    $helper->title = $this->displayName;
    $helper->show_toolbar = true; // false -> remove toolbar
    $helper->toolbar_scroll = true; // yes - > Toolbar is always visible on the top of the screen.
    $helper->submit_action = 'submit'.$this->name;
    $helper->toolbar_btn = array(
      'save' => array(
        'desc' => $this->l('Save'),
        'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                  '&token='.Tools::getAdminTokenLite('AdminModules'),
      ),
      'back' => array(
        'desc' => $this->l('Back to list'),
        'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
      ),
    );

    $this->_html .= $helper->generateForm($fields_form);
  }

  protected function getYesNoSelectFor($optname, $label)
  {
    return array(
      'label' => $label,

      'type' => 'select',
      'options' => array(
        'query' => array(
          array($optname => self::NO,  'name' => $this->l('No')),
          array($optname => self::YES, 'name' => $this->l('Yes')),
          ),
        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      );
  }

  public function cfg($key, $default=null)
  {
    if(isset($this->context->id_shop)){
      return dfTools::cfg($this->context->id_shop, $key, $default);
    }
    else{
      return dfTools::cfg(null, $key, $default);
    }
  }
    private function debug($message){
      $debug = Configuration::get('DF_DEBUG', null);
      if(isset($debug) && $debug)
        error_log("$message\n", 3, dirname(__FILE__).'/doofinder.log');
    }
    
    public function getDoofinderTermsOptions($only_facets=true){
        $debug = Configuration::get('DF_DEBUG', null);
        if (isset($debug) && $debug){
            $this->debug('Get Terms Options API Start');
        }
        
        $hash_id = Configuration::get('DF_HASHID_'.strtoupper(Context::getContext()->language->iso_code), null);
        $api_key = Configuration::get('DF_API_KEY', null);
        if($hash_id && $api_key){
            $fail = false;
            try {
                if(!class_exists('DoofinderApi')){
                    include_once dirname(__FILE__) . '/lib/doofinder_api.php';
                }
                $df = new DoofinderApi($hash_id, $api_key,false,array('apiVersion'=>'5'));
                $dfOptions = $df->getOptions();
                if($dfOptions){
                    $options = json_decode($dfOptions, true);
                }
                if (isset($debug) && $debug){
                    $this->debug("Options: ".  var_export($dfOptions,true));
                }
                if($only_facets){
                    $facets = $options['facets'];
                    $r_facets = array();
                    foreach($facets as $f_key => $f_values){
                        $r_facets[$f_values['name']] = $f_values['label'];
                    }
                    return $r_facets;
                }else{
                    return $options;
                }
            }

            catch(Exception $e){
                $fail = true;
                if (isset($debug) && $debug){
                    $this->debug("Exception:  ".$e->getMessage());
                }
            }
          
        }
    }
    
    public function searchOnApi($string,$page=1,$page_size=12,$timeout=8000,$filters = null,$return_facets = false){
        $debug = Configuration::get('DF_DEBUG', null);
        if (isset($debug) && $debug){
          $this->debug('Search On API Start');
        }
        
        $hash_id = Configuration::get('DF_HASHID_'.strtoupper(Context::getContext()->language->iso_code), null);
        $api_key = Configuration::get('DF_API_KEY', null);
        $show_variations = Configuration::get('DF_SHOW_PRODUCT_VARIATIONS', null);
        if((int)$show_variations !== 1){
            $show_variations = false;
        }
        
        if($hash_id && $api_key){
            $fail = false;
            try {
                if(!class_exists('DoofinderApi')){
                    include_once dirname(__FILE__) . '/lib/doofinder_api.php';
                }
                $df = new DoofinderApi($hash_id, $api_key,false,array('apiVersion'=>'5'));
                $dfResults = $df->query($string, $page, array('rpp' => $page_size,         // results per page
                                 'timeout' => $timeout,  // timeout in milisecs
                                 'types' => array(   // types of item 
                                     'product',
                                 ), 'transformer'=>'dflayer', 'filter' => $filters));
            }
            catch(Exception $e){
                $fail = true;
            }  
            
            
            if($fail || !$dfResults->isOk())
                return false;
            
            
            $dfResultsArray = $dfResults->getResults();  
            global $product_pool_attributes;
            $product_pool_attributes = array();
            if(!function_exists('cb')){
                function cb($entry){
                        if($entry['type'] == 'product'){
                            global $product_pool_attributes;
                            $customexplodeattr = Configuration::get('DF_CUSTOMEXPLODEATTR', null);
                            if(!empty($customexplodeattr) && strpos($entry['id'],$customexplodeattr)!==false){
                                $id_products = explode($customexplodeattr, $entry['id']);
                                $product_pool_attributes[] = $id_products[1];
                                return $id_products[0];
                            }
                            if(strpos($entry['id'],'VAR-')===false){
                                return $entry['id'];
                            }else{
                                $id_product_attribute = str_replace('VAR-','',$entry['id']);
                                if(!in_array($id_product_attribute, $product_pool_attributes)){
                                    $product_pool_attributes[] = $id_product_attribute;
                                }
                                $id_product = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT id_product FROM '._DB_PREFIX_.'product_attribute WHERE id_product_attribute = '.$id_product_attribute);
                                return ((!empty($id_product)) ? $id_product : 0 );
                            }
                        }

                }
            }
            $map = array_map('cb', $dfResultsArray);
            $product_pool = implode(', ', $map);
            
            // To avoid SQL errors.
            if($product_pool == ""){
              $product_pool = "0";
            }

            if (isset($debug) && $debug){
              $this->debug("Product Pool: $product_pool");
            }

            $product_pool_attributes = implode(',', $product_pool_attributes);
            
            if (!isset($context) || !$context)
                $context = Context::getContext();
            // Avoids SQL Error  
            if ($product_pool_attributes == ""){
              $product_pool_attributes = "0";
            }

            if (isset($debug) && $debug){
              $this->debug("Product Pool Attributes: $product_pool_attributes");
            }
            $db = Db::getInstance(_PS_USE_SQL_SLAVE_);
            $id_lang = $context->language->id;
            $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity,
				pl.`description_short`, pl.`available_now`, pl.`available_later`, pl.`link_rewrite`, pl.`name`,
			 '.(Combination::isFeatureActive() && $show_variations ? ' IF(pai.`id_image` IS NULL OR pai.`id_image` = 0, MAX(image_shop.`id_image`),pai.`id_image`) id_image, ':'i.id_image, '). '
                         il.`legend`, m.`name` manufacturer_name '.(Combination::isFeatureActive() ? (($show_variations)?', MAX(product_attribute_shop.`id_product_attribute`) id_product_attribute':', product_attribute_shop.`id_product_attribute` id_product_attribute') : '').',
				DATEDIFF(
					p.`date_add`,
					DATE_SUB(
						NOW(),
						INTERVAL '.(Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20).' DAY
					)
				) > 0 new'.(Combination::isFeatureActive() ? ', MAX(product_attribute_shop.minimal_quantity) AS product_attribute_minimal_quantity' : '').'
				FROM '._DB_PREFIX_.'product p
				'.Shop::addSqlAssociation('product', 'p').'
				INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON (
					p.`id_product` = pl.`id_product`
					AND pl.`id_lang` = '.(int)$id_lang.Shop::addSqlRestrictionOnLang('pl').'
				)
				'.(Combination::isFeatureActive() ? 'LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa	ON (p.`id_product` = pa.`id_product`)
				'.Shop::addSqlAssociation('product_attribute', 'pa', false, (($show_variations)?'':' product_attribute_shop.default_on = 1')).'
				'.Product::sqlStock('p', 'product_attribute_shop', false, $context->shop) :  Product::sqlStock('p', 'product', false, Context::getContext()->shop)).'
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON m.`id_manufacturer` = p.`id_manufacturer`
				LEFT JOIN `'._DB_PREFIX_.'image` i ON (i.`id_product` = p.`id_product` '.((Combination::isFeatureActive() && $show_variations)?'':'AND i.cover=1').')  
                                '.((Combination::isFeatureActive() && $show_variations) ? ' LEFT JOIN `'._DB_PREFIX_.'product_attribute_image` pai ON (pai.`id_product_attribute` = product_attribute_shop.`id_product_attribute`) ':' ').
				Shop::addSqlAssociation('image', 'i', false, 'i.cover=1').' 
				LEFT JOIN `'._DB_PREFIX_.'image_lang` il ON (i.`id_image` = il.`id_image` AND il.`id_lang` = '.(int)$id_lang.')
				WHERE p.`id_product` IN ('.$product_pool.') '.
                                (($show_variations)? ' AND (product_attribute_shop.`id_product_attribute` IS NULL OR product_attribute_shop.`id_product_attribute` IN ('.$product_pool_attributes.')) ':'').
				' GROUP BY product_shop.id_product '.(($show_variations)?' ,  product_attribute_shop.`id_product_attribute` ':'').
                                ' ORDER BY FIELD (p.`id_product`,'.$product_pool.') '.(($show_variations)?' , FIELD (product_attribute_shop.`id_product_attribute`,'.$product_pool_attributes.')':'');
		if (isset($debug) && $debug){
      $this->debug("SQL: $sql");
    }
   
    $result = $db->executeS($sql);
    

		if (!$result)
			return false;
		else
			$result_properties = Product::getProductsProperties((int)$id_lang, $result);
    // To print the id and links in the javascript so I can register the clicks
    $this->_productLinks = array();
    
    foreach($result_properties as $rp){
      $this->_productLinks[$rp['link']] = $rp['id_product'];
    }

                if($return_facets){
                    return array('total' => $dfResults->getProperty('total'),'result' => $result_properties, 'facets' => $dfResults->getFacets(), 'filters'=> $df->getFilters());
                }
		return array('total' => $dfResults->getProperty('total'),'result' => $result_properties);
        }else{
            return false;
        }
    }
  
    public function getFormatedName($name)
    {
            $theme_name = Context::getContext()->shop->theme_name;
            $name_without_theme_name = str_replace(array('_'.$theme_name, $theme_name.'_'), '', $name);

            //check if the theme name is already in $name if yes only return $name
            if (strstr($name, $theme_name) && ImageType::getByNameNType($name))
                    return $name;
            elseif (ImageType::getByNameNType($name_without_theme_name.'_'.$theme_name))
                    return $name_without_theme_name.'_'.$theme_name;
            elseif (ImageType::getByNameNType($theme_name.'_'.$name_without_theme_name))
                    return $theme_name.'_'.$name_without_theme_name;
            else
                    return $name_without_theme_name.'_default';
    }
    
    public function hookLeftColumn($params) {
        if(isset($this->context->controller->php_self) && $this->context->controller->php_self == 'search' ){
            return $this->generateSearch();
        }
        return false;
    }

    public function hookRightColumn($params) {
        return $this->hookLeftColumn($params);
    }
    
    public function generateSearch($returnToSearchController=false){
        $overwrite_search = Configuration::get('DF_OWSEARCH', null);
        $overwrite_facets = Configuration::get('DF_OWSEARCHFAC', null);
        if ($overwrite_search && ($overwrite_facets || $returnToSearchController)){
            $query = Tools::getValue('search_query', Tools::getValue('ref'));
            $p = abs((int)(Tools::getValue('p', 1)));
            $n = abs((int)(Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
            $filters = Tools::getValue('filters', NULL);
            if (($search = $this->searchOnApi($query,$p,$n,8000,$filters,true)) 
                    && $query
                    && !is_array($query)){
                if($returnToSearchController){
                    return $search;
                }
                
                return $this->generateFiltersBlock($search['facets'],$search['filters']);
            }else{
                return false;
            }
        }
    }
    
    public function generateFiltersBlock($facets,$filters){
        global $smarty;
        if ($filter_block = $this->getFilterBlock($facets,$filters)) {
            if ($filter_block['nbr_filterBlocks'] == 0)
                return false;

            $translate = array();
            $translate['price'] = $this->l('price');
            $translate['weight'] = $this->l('weight');

            $smarty->assign($filter_block);
            $smarty->assign(array(
                'hide_0_values' => Configuration::get('PS_LAYERED_HIDE_0_VALUES'),
                'blocklayeredSliderName' => $translate,
                'col_img_dir' => _PS_COL_IMG_DIR_
            ));
            return $this->display(__FILE__, 'views/templates/front/doofinder_facets.tpl');
        } else
            return false;
    }
    
    public function getFilterBlock($facets,$filters){ 
        $cacheOptionsDoofinderFileName = _PS_CACHE_DIR_.'smarty/compile/OptionsDoofinderFileName-'.Context::getContext()->shop->id.'-'.Context::getContext()->language->id.'-'.hash_hmac('sha256', 'OptionsDoofinderFileName', 'cache').'-'.date('Ymd').'.html';
        $optionsDoofinder = '';
        if(file_exists($cacheOptionsDoofinderFileName)){
            $optionsDoofinder = json_decode(file_get_contents($cacheOptionsDoofinderFileName),true);
        }      
        if(empty($optionsDoofinder)){
            $optionsDoofinder = $this->getDoofinderTermsOptions(false);
            $jsonCacheOptionsDoofinder = json_encode($optionsDoofinder);
            file_put_contents($cacheOptionsDoofinderFileName, $jsonCacheOptionsDoofinder);
        }
		
        $r_facets = array();
        $t_facets = array();
        foreach($optionsDoofinder['facets'] as $f_key => $f_values){
                $r_facets[$f_values['name']] = $f_values['label'];
                $t_facets[$f_values['name']] = $f_values['type'];
        }
		
        //Reorder filter block as doofinder dashboard
        $facetsBlock = array();
        foreach($r_facets as $key_o => $value_o){
            $facetsBlock[$key_o] = $facets[$key_o];
            $this->multi_rename_key($facetsBlock[$key_o]['terms']['buckets'],array("key","doc_count"), array("term","count"));
            $facetsBlock[$key_o]['terms'] = $facetsBlock[$key_o]['terms']['buckets'];
            if(count($facetsBlock[$key_o]['terms'])){
                foreach($facetsBlock[$key_o]['terms'] as $key_t => $value_t){
                    $facetsBlock[$key_o]['terms'][$key_t]['selected'] = 0;
                }
            }
            $facetsBlock[$key_o]['_type'] = $t_facets[$key_o];
            if($t_facets[$key_o] == 'range'){
                $facetsBlock[$key_o]['ranges'][0] = array(
                    'from' => $facets[$key_o]['range']['buckets'][0]['from'],
                    'count' => $facets[$key_o]['range']['buckets'][0]['doc_count'],
                    'min' => $facets[$key_o]['range']['buckets'][0]['stats']['min'],
                    'max' => $facets[$key_o]['range']['buckets'][0]['stats']['max'],
                    'total_count' => $facets[$key_o]['range']['buckets'][0]['stats']['count'],
                    'total' => $facets[$key_o]['range']['buckets'][0]['stats']['sum'],
                    'mean' => $facets[$key_o]['range']['buckets'][0]['stats']['avg'],
                    'selected_from' => false,
                    'selected_to' => false,
                );
            }
        }
        $facets = $facetsBlock;

        
        return array('options'=>$r_facets,
            'facets'=>$facets,
            'filters'=>$filters,
            'nbr_filterBlocks' => 1);
                
        
        return false;
    }
    
    public function getSelectedFilters(){
        $options = $this->getDoofinderTermsOptions();
        
        $filters = array();
        foreach($options as $key => $value){
            if($selected = Tools::getValue('layered_terms_'.$key,false)){
                $filters[$key] = $selected;
            }else if($selected = Tools::getValue('layered_'.$key.'_slider',false)){
                $selected = explode('_', $selected);
                $filters[$key] = array(
                    'from'=>$selected[0],
                    'to'=>$selected[1]
                );
            }
        }
        return $filters;
    }
    
    public function getPaginationValues($nb_products,$p,$n,&$pages_nb,&$range,&$start,&$stop){
        $range = 2; /* how many pages around page selected */

        if ($n <= 0)
            $n = 1;

        if ($p < 0)
            $p = 0;

        if ($p > ($nb_products / $n))
            $p = ceil($nb_products / $n);
        $pages_nb = ceil($nb_products / (int) ($n));

        $start = (int) ($p - $range);
        if ($start < 1)
            $start = 1;

        $stop = (int) ($p + $range);
        if ($stop > $pages_nb)
            $stop = (int) ($pages_nb);
    }
    
    public function ajaxCall() {
        global $smarty, $cookie;

        $selected_filters = $this->getSelectedFilters();
        $_POST['filters'] = $selected_filters;

        
        $search = $this->generateSearch(true);
        $products = $search['result'];
        $p = abs((int)(Tools::getValue('p', 1)));
        $n = abs((int)(Tools::getValue('n', Configuration::get('PS_PRODUCTS_PER_PAGE'))));
        if(!$n)
        {
            $n = Configuration::get('PS_PRODUCTS_PER_PAGE');
        }
        
        // Add pagination variable
        $nArray = (int) Configuration::get('PS_PRODUCTS_PER_PAGE') != 10 ? array((int) Configuration::get('PS_PRODUCTS_PER_PAGE'), 10, 20, 50) : array(10, 20, 50);
        // Clean duplicate values
        $nArray = array_unique($nArray);
        asort($nArray);

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true)
            $this->context->controller->addColorsToProductList($products);

        $category = new Category(Tools::getValue('id_category_layered', Configuration::get('PS_HOME_CATEGORY')), (int) $cookie->id_lang);

        // Generate meta title and meta description
        $category_title = (empty($category->meta_title) ? $category->name : $category->meta_title);
        $category_metas = Meta::getMetaTags((int) $cookie->id_lang, 'category');
        $title = '';
        $keywords = '';

        if (isset($filter_block) && is_array($filter_block['title_values']))
            foreach ($filter_block['title_values'] as $key => $val) {
                $title .= ' > ' . $key . ' ' . implode('/', $val);
                $keywords .= $key . ' ' . implode('/', $val) . ', ';
            }

        $title = $category_title . $title;

        if (!empty($title))
            $meta_title = $title;
        else
            $meta_title = $category_metas['meta_title'];

        $meta_description = $category_metas['meta_description'];

        $keywords = substr(strtolower($keywords), 0, 1000);
        if (!empty($keywords))
            $meta_keywords = rtrim($category_title . ', ' . $keywords . ', ' . $category_metas['meta_keywords'], ', ');

        $nb_products = $search['total'];
        //var_dump($search);
        $this->getPaginationValues($nb_products, $p, $n, $pages_nb, $range, $start, $stop);
        $smarty->assign(
                array(
                    'homeSize' => Image::getSize(ImageType::getFormatedName('home')),
                    'nb_products' => $nb_products,
                    'category' => $category,
                    'pages_nb' => (int) $pages_nb,
                    'p' => (int) $p,
                    'n' => (int) $n,
                    'range' => (int) $range,
                    'start' => (int) $start,
                    'stop' => (int) $stop,
                    'n_array' => ((int) Configuration::get('PS_PRODUCTS_PER_PAGE') != 10) ? array((int) Configuration::get('PS_PRODUCTS_PER_PAGE'), 10, 20, 50) : array(10, 20, 50),
                    'comparator_max_item' => (int) (Configuration::get('PS_COMPARATOR_MAX_ITEM')),
                    'products' => $products,
                    'products_per_page' => (int) Configuration::get('PS_PRODUCTS_PER_PAGE'),
                    'static_token' => Tools::getToken(false),
                    'page_name' => 'search',
                    'nArray' => $nArray,
                    'compareProducts' => CompareProduct::getCompareProducts((int) $this->context->cookie->id_compare)
                )
        );

        // Prevent bug with old template where category.tpl contain the title of the category and category-count.tpl do not exists
        if (file_exists(_PS_THEME_DIR_ . 'category-count.tpl'))
            $category_count = $smarty->fetch(_PS_THEME_DIR_ . 'category-count.tpl');
        else
            $category_count = '';

        if ($nb_products == 0)
            $product_list = $this->display(__FILE__, 'views/templates/front/doofinder-no-products.tpl');
        else{
            $product_list = $smarty->fetch(_PS_THEME_DIR_ . 'product-list.tpl');
        }
        // To avoid Notice
        if (!isset($filter_block)){
          $filter_block = array('current_friendly_url' => '');
        }
        $vars = array(
            //'filtersBlock' => utf8_encode($this->generateFiltersBlock($search['facets'],$search['filters'])),
            'productList' => utf8_encode($product_list),
            'pagination' => $smarty->fetch(_PS_THEME_DIR_ . 'pagination.tpl'),
            'categoryCount' => $category_count,
            'meta_title' => $meta_title . ' - ' . Configuration::get('PS_SHOP_NAME'),
            'heading' => $meta_title,
            'meta_keywords' => isset($meta_keywords) ? $meta_keywords : null,
            'meta_description' => $meta_description,
            'current_friendly_url' => ((int) $n == (int) $nb_products) ? '#/show-all' : '#' . $filter_block['current_friendly_url'],
            //'filters' => $filter_block['filters'],
            'nbRenderedProducts' => (int) $nb_products,
            'nbAskedProducts' => (int) $n
        );

        if (version_compare(_PS_VERSION_, '1.6.0', '>=') === true)
            $vars = array_merge($vars, array('pagination_bottom' => $smarty->assign('paginationId', 'bottom')
                        ->fetch(_PS_THEME_DIR_ . 'pagination.tpl')));
        /* We are sending an array in jSon to the .js controller, it will update both the filters and the products zones */
        return Tools::jsonEncode($vars);
    }
    
    //http://stackoverflow.com/a/17254761
    function multi_rename_key(&$array, $old_keys, $new_keys)
    {
        if(!is_array($array)){
            ($array=="") ? $array=array() : false;
            return $array;
        }
        foreach($array as &$arr){
            if (is_array($old_keys))
            {
                    foreach($new_keys as $k => $new_key)
                    {
                            (isset($old_keys[$k])) ? true : $old_keys[$k]=NULL;
                            $arr[$new_key] = (isset($arr[$old_keys[$k]]) ? $arr[$old_keys[$k]] : null);
                            unset($arr[$old_keys[$k]]);
                    }
            }else{
                    $arr[$new_keys] = (isset($arr[$old_keys]) ? $arr[$old_keys] : null);
                    unset($arr[$old_keys]);
            }
        }
        return $array;
    }
    
    function getSQLOnlyProductsWithAttributes(){
        $attr_groups = AttributeGroup::getAttributesGroups((int)Configuration::get('PS_LANG_DEFAULT'));
        
        $sql_select_attributes = array();
        $sql_from_attributes = array();
        $sql_from_only = ' LEFT JOIN _DB_PREFIX_product_attribute pa ON (p.id_product = pa.id_product)
                                        LEFT JOIN _DB_PREFIX_product_attribute_combination pac ON (pa.id_product_attribute = pac.id_product_attribute) ';
        foreach($attr_groups as $a_group){
            $a_group_name = str_replace('-','_',Tools::str2url($a_group['name']));
            $sql_select_attributes[] = ' GROUP_CONCAT(DISTINCT REPLACE(pal_'.$a_group['id_attribute_group'].'.name,\'/\',\'\/\/\') SEPARATOR \'/\') as attributes_'.$a_group_name;
            $sql_from_attributes[] = '  LEFT JOIN _DB_PREFIX_attribute pat_'.$a_group['id_attribute_group'].' ON (pat_'.$a_group['id_attribute_group'].'.id_attribute = pac.id_attribute AND pat_'.$a_group['id_attribute_group'].'.id_attribute_group = '.$a_group['id_attribute_group'].' )
                                        LEFT JOIN _DB_PREFIX_attribute_lang pal_'.$a_group['id_attribute_group'].' ON (pal_'.$a_group['id_attribute_group'].'.id_attribute = pat_'.$a_group['id_attribute_group'].'.id_attribute AND pal_'.$a_group['id_attribute_group'].'.id_lang = '.(int)Configuration::get('PS_LANG_DEFAULT').') ';
        
        }
        
        $sql = "
            SELECT
              ps.id_product,
              __ID_CATEGORY_DEFAULT__,

              m.name AS manufacturer,

              p.__MPN__ AS mpn,
              p.ean13 AS ean13,

              pl.name,
              pl.description,
              pl.description_short,
              pl.meta_title,
              pl.meta_keywords,
              pl.meta_description,
              GROUP_CONCAT(tag.name SEPARATOR '/') AS tags,
              pl.link_rewrite,
              cl.link_rewrite AS cat_link_rew,

              im.id_image,

              p.available_for_order ".(count($sql_select_attributes)?','.implode(',',$sql_select_attributes):'')."
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
                ON (p.id_product = im.id_product AND ims.id_shop = _ID_SHOP_ AND _IMS_COVER_)
              LEFT JOIN (_DB_PREFIX_tag tag INNER JOIN _DB_PREFIX_product_tag pt ON tag.id_tag = pt.id_tag AND tag.id_lang = _ID_LANG_)
                ON (pt.id_product = p.id_product)
                ".(count($sql_from_attributes)? $sql_from_only.implode(' ',$sql_from_attributes):'')."
            WHERE
              __IS_ACTIVE__
              __VISIBILITY__
            GROUP BY
              p.id_product
            ORDER BY
              p.id_product
          ";
        
        return $sql;
    }
    
}
