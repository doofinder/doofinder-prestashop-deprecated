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

  const GS_SHORT_DESCRIPTION = 1;
  const GS_LONG_DESCRIPTION = 2;
  const VERSION = "1.5.0";

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
      'self' => dirname(__FILE__),
    ));

    return true;
  }

  public function hookHeader($params)
  {
    $this->configureHookCommon($params);
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
        $this->_html .= $this->displayConfirmation($this->l('Settings updated!'));
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

    $cfgStrSelectValues = array(
      'DF_GS_IMAGE_SIZE' => array( // Image Size
        'valid' => array_keys(dfTools::getAvailableImageSizes()),
        'label' => $this->l('Product Image Size'),
        ),
      'DF_GS_MPN_FIELD' => array(
        'valid' => array('reference', 'supplier_reference', 'ean13', 'upc'),
        'label' => $this->l('MPN Field for Data Feed'),
        ),
      );

    foreach ($cfgStrSelectValues as $optname => $cfg)
    {
      $optvalue = Tools::getValue($optname);

      if (dfTools::isBasicValue($optvalue))
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

    $cfgLangStrValues = array('DOOFINDER_SCRIPT_' => true, 'DF_GS_CURRENCY_' => false);
    foreach ($cfgLangStrValues as $prefix => $html)
    {
      foreach (Language::getLanguages(true, $this->context->shop->id) as $lang)
      {
        $optname = $prefix.strtoupper($lang['iso_code']);
        Configuration::updateValue($optname, Tools::getValue($optname), $html);
      }
    }

    $cfgCodeStrValues = array(
        'DF_EXTRA_CSS',
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
    foreach (Language::getLanguages(true, $this->context->shop->id) as $lang)
    {
      $realoptname = $optname.strtoupper($lang['iso_code']);
      $url = dfTools::getFeedURL($lang['iso_code']);
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
    return dfTools::cfg($this->context->shop->id, $key, $default);
  }
}
