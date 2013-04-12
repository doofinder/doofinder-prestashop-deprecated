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

$required_classes = array('dfTools', 'dfForm');

foreach ($required_classes as $classname)
  if (!class_exists($classname))
    require_once(dirname(__FILE__)."/$classname.class.php");

class Doofinder extends Module
{
  protected $_html = '';
  protected $_postErrors = array();

  const VERSION = "1.1.1";
  const GS_SHORT_DESCRIPTION = 1;
  const GS_LONG_DESCRIPTION = 2;


  public function __construct()
  {
    $this->name = "doofinder";
    $this->tab = "search_filter";
    $this->version = self::VERSION;
    $this->author = "Doofinder (http://www.doofinder.com)";
    $this->ps_versions_compliancy = array('min' => '1.4', 'max' => '1.4');

    parent::__construct();

    $this->displayName = 'Doofinder';
    $this->description = $this->l('Install Doofinder in your shop with no effort.');

    $this->confirmUninstall = $this->l('Are you sure? This will not cancel your account in Doofinder service.');
  }


  public function install()
  {
    if (!parent::install() || !$this->registerHook('top') ||
        !$this->registerHook('header'))
      return false;

    return true;
  }

  private function configureHookCommon($params)
  {
    global $smarty;
    global $cookie;

    $language = new Language($cookie->id_lang);
    $lang = strtoupper($language->iso_code);

    $smarty->assign(array(
      'ENT_QUOTES' => ENT_QUOTES,
      'lang' => strtolower($lang),
      'searchbox_enabled' => (int) Configuration::get('DOOFINDER_INPUT_ENABLED'),
      'script' => Configuration::get("DOOFINDER_SCRIPT_$lang"),
      'self' => dirname(__FILE__),
    ));

    return true;
  }

  public function hookHeader($params)
  {
    Tools::addCSS(($this->_path).'css/layer.css', 'all');
    $this->configureHookCommon($params);

    return $this->display(__FILE__, 'script.tpl');
  }

  public function hookTop($params)
  {
    global $smarty;

    $width = Configuration::get("DOOFINDER_INPUT_WIDTH");
    $top = Configuration::get("DOOFINDER_INPUT_TOP");
    $left = Configuration::get("DOOFINDER_INPUT_LEFT");
    $customized = !empty($width) || !empty($top) || !empty($left);

    $this->configureHookCommon($params);
    $smarty->assign(array(
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
    global $smarty;

    $this->configureHookCommon($params);
    $smarty->assign(array(
      'searchbox_type' => 'left',
      'placeholder' => $this->l('Search'),
      ));

    return $this->display(__FILE__, 'searchbox-block.tpl');
  }

  public function hookRightColumn($params)
  {
    global $smarty;

    $this->configureHookCommon($params);
    $smarty->assign(array(
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

    // If I don't do this the DOOFINDER_SCRIPT_* values loose PHP_EOLs.
    Configuration::loadConfiguration();

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
    global $currentIndex;

    $defaultLanguage = intval(Configuration::get('PS_LANG_DEFAULT'));
    $default_currency = Currency::getDefaultCurrency();
    $languages = Language::getLanguages();
    $yesNoChoices = array(
      '0' => $this->l('No'),
      '1' => $this->l('Yes'),
      );

    $this->_html .= '
      <script type="text/javascript">
        id_language = Number('.$defaultLanguage.');
      </script>
    ';

    $formAction = dfForm::formAction($currentIndex, $this->name, Tools::getAdminTokenLite('AdminModules'));
    $submitButton = dfForm::submitButton($this->name, $this->l('Save'));

    $this->_html .= '<form id="doofinder-options" action="'.$formAction.'" method="post">';


    //
    // SEARCH BOX
    //

    $this->_html .= dfForm::fieldset($this->l('Searchbox in Page Top'));

    $optname = 'DOOFINDER_INPUT_ENABLED';
    $optvalue = Configuration::get($optname, '0');
    $field = dfForm::getSelectFor($optname, $optvalue, $yesNoChoices);
    $label = $this->l('Enable Module\'s Searchbox');
    $this->_html .= dfForm::wrapField($optname, $label, $field);


    $optname = 'DOOFINDER_INPUT_WIDTH';
    $optvalue = Configuration::get($optname, '0');
    $extra = array('desc' => 'i.e: 396px');
    $attrs = array('type' => 'text', 'class' => 'doofinder_dimensions');
    $field = dfForm::getInputFor($optname, $optvalue, $attrs);
    $label = $this->l('Searchbox Width');
    $this->_html .= dfForm::wrapField($optname, $label, $field, $extra);


    $optname = 'DOOFINDER_INPUT_TOP';
    $optvalue = Configuration::get($optname, '');
    $extra = array('desc' => 'i.e: 48px');
    $attrs = array('type' => 'text', 'class' => 'doofinder_dimensions');
    $field = dfForm::getInputFor($optname, $optvalue, $attrs);
    $label = $this->l('Top Position');
    $this->_html .= dfForm::wrapField($optname, $label, $field, $extra);


    $optname = 'DOOFINDER_INPUT_LEFT';
    $optvalue = Configuration::get($optname, '');
    $extra = array('desc' => 'i.e: 50%');
    $attrs = array('type' => 'text', 'class' => 'doofinder_dimensions');
    $field = dfForm::getInputFor($optname, $optvalue, $attrs);
    $label = $this->l('Left Position');
    $this->_html .= dfForm::wrapField($optname, $label, $field, $extra);

    $this->_html .= $submitButton;
    $this->_html .= '</fieldset>';


    //
    // DATA FEED SETTINGS
    //

    $this->_html .= dfForm::fieldset($this->l('Data Feed Settings'));

    $optname = 'DF_GS_IMAGE_SIZE';
    $optvalue = Configuration::get($optname);
    $field = dfForm::getSelectFor($optname, $optvalue, dfTools::getAvailableImageSizes());
    $label = $this->l('Product Image Size');
    $this->_html .= dfForm::wrapField($optname, $label, $field);


    $optname = 'DF_GS_DESCRIPTION_TYPE';
    $optvalue = Configuration::get($optname);
    $choices = array(
      self::GS_SHORT_DESCRIPTION => $this->l('Short'),
      self::GS_LONG_DESCRIPTION => $this->l('Long'),
      );
    $field = dfForm::getSelectFor($optname, $optvalue, $choices);
    $label = $this->l('Product Description Length');
    $this->_html .= dfForm::wrapField($optname, $label, $field);


    $baseoptname = 'DF_GS_CURRENCY_';
    foreach (Language::getLanguages(true) as $lang)
    {
      $optname = $baseoptname.strtoupper($lang['iso_code']);
      $optvalue = Configuration::get($optname, $default_currency->iso_code);
      $field = dfForm::getSelectFor($optname, $optvalue, dfTools::getAvailableCurrencies());
      $label = sprintf($this->l("Currency for %s"), $lang['name']);
      $this->_html .= dfForm::wrapField($optname, $label, $field);
    }

    $this->_html .= $submitButton;
    $this->_html .= '</fieldset>';


    //
    // DOOFINDER SCRIPTS
    //

    $this->_html .= dfForm::fieldset($this->l('Doofinder Script'));

    // DOOFINDER_SCRIPT
    $baseoptname = 'DOOFINDER_SCRIPT_';
    $desc = $this->l('Paste the script as you got it from Doofinder.');

    foreach (Language::getLanguages(true) as $lang)
    {

      $url = $this->feedURLforLang($lang['iso_code']);

      $optname = $baseoptname.strtoupper($lang['iso_code']);
      $optvalue = Configuration::get($optname);
      $attrs = array('cols' => 100, 'rows' => 10, 'class' => 'df-script');
      $extra = array('desc' => sprintf('<span class="df-notice"><b>%s [%s]:</b> <a href="%s" target="_blank">%s</a></span>%s', $this->l('Data Feed URL'), strtoupper($lang['iso_code']), $url, $url, $desc));
      $field = dfForm::getTextareaFor($optname, $optvalue, $attrs);
      $label = $lang['name'];
      $this->_html .= dfForm::wrapField($optname, $label, $field, $extra);
    }

    $this->_html .= $submitButton;
    $this->_html .= '</fieldset>';

    $this->_html .= '</form>';
    return;


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

  protected function feedURLforLang($iso_code)
  {
    return Tools::getShopDomain(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/feed.php?lang='.$iso_code;
  }
}
