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
  const VERSION = "1.2.2.1";

  const YES = 1;
  const NO = 0;

  const FETCH_MODE_FAST = 'fast';
  const FETCH_MODE_ALT1 = 'alt1';
  const FETCH_MODE_ALT2 = 'alt2';


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
    if (!parent::install() || !$this->registerHook('top') ||
        !$this->registerHook('header') ||
        !$this->registerHook('displayMobileTopSiteMap'))
      return false;

    return true;
  }

  private function configureHookCommon($params)
  {
    $lang = strtoupper($this->context->language->iso_code);

    $this->smarty->assign(array(
      'ENT_QUOTES' => ENT_QUOTES,
      'lang' => strtolower($lang),
      'searchbox_enabled' => (int) self::cfg('DOOFINDER_INPUT_ENABLED'),
      'script' => self::cfg("DOOFINDER_SCRIPT_$lang"),
      'self' => dirname(__FILE__),
    ));

    return true;
  }

  public function hookHeader($params)
  {
    $this->context->controller->addCSS(($this->_path).'css/layer.css', 'all');
    $this->configureHookCommon($params);

    return $this->display(__FILE__, 'script.tpl');
  }

  public function hookdisplayMobileTopSiteMap($params)
  {
    $this->smarty->assign(array('hook_mobile' => true));
    return $this->hookTop($params);
  }

  public function hookTop($params)
  {
    $width = self::cfg("DOOFINDER_INPUT_WIDTH");
    $top = self::cfg("DOOFINDER_INPUT_TOP");
    $left = self::cfg("DOOFINDER_INPUT_LEFT");
    $customized = !empty($width) || !empty($top) || !empty($left);

    $this->configureHookCommon($params);
    $this->smarty->assign(array(
      'hook_top' => true,
      'customized' => $customized,
      'placeholder' => $this->l('Enter a product name to search'),
      'width' => $width ? $width : false,
      'top' => $top ? $top : false,
      'left' => $left ? $left : false,
      ));

    return $this->display(__FILE__, 'searchbox-top.tpl');
  }

  public function hookLeftColumn($params)
  {
    $this->configureHookCommon($params);
    $this->smarty->assign(array(
      'searchbox_type' => 'left',
      'placeholder' => $this->l('Search'),
      ));

    return $this->display(__FILE__, 'searchbox-block.tpl');
  }

  public function hookRightColumn($params)
  {
    $this->configureHookCommon($params);
    $this->smarty->assign(array(
          'searchbox_type' => 'right',
          'placeholder' => $this->l('Search'),
          ));

    return $this->display(__FILE__, 'searchbox-block.tpl');
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
      'DOOFINDER_INPUT_ENABLED' => $this->l('Doofinder Searchbox Enabled'),
      'DF_GS_DISPLAY_PRICES' => $this->l('Display Prices in Data Feed'),
      'DF_GS_PRICES_USE_TAX' => $this->l('Display Prices With Taxes'),
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
      'DF_FETCH_FEED_MODE' => array(
        'valid' => array(self::FETCH_MODE_FAST, self::FETCH_MODE_ALT1, self::FETCH_MODE_ALT2),
        'label' => $this->l('Feed Generation Mode'),
        )
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
      foreach (Language::getLanguages() as $lang)
      {
        $optname = $prefix.strtoupper($lang['iso_code']);
        Configuration::updateValue($optname, Tools::getValue($optname), $html);
      }
    }

    $cfgStrValues = array(
      'DOOFINDER_INPUT_WIDTH' => $this->l('Doofinder Searchbox Width'),
      'DOOFINDER_INPUT_TOP' => $this->l('Doofinder Searchbox Offset Top'),
      'DOOFINDER_INPUT_LEFT' => $this->l('Doofinder Searchbox Offset Left'),
      );

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
    $default_lang_id = (int) self::cfg('PS_LANG_DEFAULT', 1);
    $default_currency = Currency::getDefaultCurrency();

    //
    // SEARCH BOX
    //

    $fields = array();

    $fields_form[0]['form'] = array(
      'legend' => array('title' => $this->l('Searchbox in Page Top')),
      'input'  => null,
      'submit' => array('title' => $this->l('Save'), 'class' => 'button'),
      );

    $optname = 'DOOFINDER_INPUT_ENABLED';
    $field = $this->getYesNoSelectFor($optname, $this->l('Enable Module\'s Searchbox'));
    // $field['desc'] = $this->l("<span class='df-notice'>If <b>YES</b> remember to execute the layer installer again.</span>"); // TODO
    $fields[] = $field;
    $helper->fields_value[$optname] = self::cfg($optname);


    $optname = 'DOOFINDER_INPUT_WIDTH';
    $fields[] = array(
      'label' => $this->l('Searchbox Width'),
      'desc' => 'i.e: 396px',
      'name' => $optname,
      'type' => 'text',
      'class' => 'doofinder_dimensions',
      );
    $helper->fields_value[$optname] = self::cfg($optname, '');


    $optname = 'DOOFINDER_INPUT_TOP';
    $fields[] = array(
      'label' => $this->l('Top Position'),
      'desc' => 'i.e: 48px',
      'name' => $optname,
      'type' => 'text',
      'class' => 'doofinder_dimensions',
      );
    $helper->fields_value[$optname] = self::cfg($optname, '');


    $optname = 'DOOFINDER_INPUT_LEFT';
    $fields[] = array(
      'label' => $this->l('Left Position'),
      'desc' => 'i.e: 50%',
      'name' => $optname,
      'type' => 'text',
      'class' => 'doofinder_dimensions',
      );
    $helper->fields_value[$optname] = self::cfg($optname, '');


    $fields_form[0]['form']['input'] = $fields;



    //
    // DATA FEED SETTINGS
    //

    $fields = array();

    $fields_form[1]['form'] = array(
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
    $helper->fields_value[$optname] = self::cfg($optname);


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
    $helper->fields_value[$optname] = self::cfg($optname);


    // DF_GS_CURRENCY_<LANG>
    $optname = 'DF_GS_CURRENCY_';
    foreach (Language::getLanguages(true) as $lang)
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
      $helper->fields_value[$realoptname] = self::cfg($realoptname, $default_currency->iso_code);
    }


    // DF_FETCH_FEED_MODE
    $optname = 'DF_FETCH_FEED_MODE';
    $fields[] = array(
      'label' => $this->l('Feed Generation Mode'),
      'desc' => $this->l('If the feed is not generated try changing this value.'),

      'type' => 'select',
      'options' => array(
        'query' => array(
          array($optname => self::FETCH_MODE_FAST, 'name' => $this->l('Fastest (experimental)')),
          array($optname => self::FETCH_MODE_ALT1, 'name' => $this->l('Normal (default)')),
          array($optname => self::FETCH_MODE_ALT2, 'name' => $this->l('Slower')),
          ),
        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      );
    $helper->fields_value[$optname] = self::cfg($optname, self::FETCH_MODE_ALT1);


    // DF_GS_DISPLAY_PRICES
    $optname = 'DF_GS_DISPLAY_PRICES';
    $field = $this->getYesNoSelectFor($optname, $this->l('Display Prices in Data Feed'));
    $fields[] = $field;
    $helper->fields_value[$optname] = self::cfg($optname, self::YES);


    // DF_GS_PRICES_USE_TAX
    $optname = 'DF_GS_PRICES_USE_TAX';
    $field = $this->getYesNoSelectFor($optname, $this->l('Display Prices With Taxes'));
    $fields[] = $field;
    $helper->fields_value[$optname] = self::cfg($optname, self::YES);


    $fields_form[1]['form']['input'] = $fields;



    //
    // DOOFINDER SCRIPTS
    //

    $fields = array();

    $fields_form[2]['form'] = array(
      'legend' => array('title' => $this->l('Doofinder Script')),
      'input'  => null,
      'submit' => array('title' => $this->l('Save'), 'class' => 'button'),
      );


    // DOOFINDER_SCRIPT
    $optname = 'DOOFINDER_SCRIPT_';
    $desc = $this->l('Paste the script as you got it from Doofinder.');

    foreach (Language::getLanguages(true) as $lang)
    {
      $realoptname = $optname.strtoupper($lang['iso_code']);
      $url = $this->feedURLforLang($lang['iso_code']);
      $fields[] = array(
        'label' => $lang['name'],
        'desc' => sprintf('<span class="df-notice"><b>%s [%s]:</b> <a href="%s" target="_blank">%s</a></span>%s', $this->l('Data Feed URL'), strtoupper($lang['iso_code']), $url, $url, $desc),

        'type' => 'textarea',
        'cols' => 100,
        'rows' => 10,
        'name' => $realoptname,
        'required' => false,
        );

      $helper->fields_value[$realoptname] = self::cfg($realoptname);
    }


    $fields_form[2]['form']['input'] = $fields;



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

  protected function feedURLforLang($iso_code)
  {
    return Tools::getShopDomain(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/feed.php?lang='.$iso_code;
  }

  public static function cfg($key, $default=null)
  {
    $v = Configuration::get($key);
    if ($v !== false)
      return $v;
    return $default;
  }
}
