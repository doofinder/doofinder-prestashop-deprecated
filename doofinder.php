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

if (strpos(__FILE__, 'doofinder.php') === false)
  $basedir = _PS_ROOT_DIR_.'/modules/doofinder';
else
  $basedir = dirname(__FILE__);

foreach ($required_classes as $classname)
  if (!class_exists($classname))
    require_once(realpath("$basedir/$classname.class.php"));


class Doofinder extends Module
{
  protected $_html = '';
  protected $_postErrors = array();

  const GS_SHORT_DESCRIPTION = 1;
  const GS_LONG_DESCRIPTION = 2;
  const VERSION = "1.1.3.6";

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
    $script = self::cfg("DOOFINDER_SCRIPT_$lang");
    $searchbox_enabled = self::cfg('DF_DISPLAY_SEARCHBOX', self::YES);
    $extra_css = self::cfg('DF_EXTRA_CSS');

    $smarty->assign(array(
      'ENT_QUOTES' => ENT_QUOTES,
      'lang' => strtolower($lang),
      'script' => dfTools::fixScriptTag($script),
      'extra_css' => dfTools::fixStyleTag($extra_css),
      'self' => dirname(__FILE__),
      'df_searchbox_enabled' => intval($searchbox_enabled),
    ));

    return true;
  }

  public function hookHeader($params)
  {
    if (self::cfg('DF_DISPLAY_SEARCHBOX', self::YES) == self::YES)
      Tools::addCSS(($this->_path).'css/layer.css', 'all');

    $this->configureHookCommon($params);
    return $this->display(__FILE__, 'script.tpl');
  }

  public function hookTop($params)
  {
    $this->configureHookCommon($params);
    return $this->display(__FILE__, 'searchbox-top.tpl');
  }

  public function hookLeftColumn($params)
  {
    $this->configureHookCommon($params);
    return $this->display(__FILE__, 'searchbox-block.tpl');
  }

  public function hookRightColumn($params)
  {
    $this->configureHookCommon($params);
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
      'DF_GS_DISPLAY_PRICES' => $this->l('Display Prices in Data Feed'),
      'DF_GS_PRICES_USE_TAX' => $this->l('Display Prices With Taxes'),
      'DF_DISPLAY_SEARCHBOX' => $this->l('Display Searchbox'),
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

    $cfgCodeStrValues = array(
        'DF_EXTRA_CSS',
      );

    foreach ($cfgCodeStrValues as $optname)
    {
      $optvalue = Tools::getValue($optname);
      Configuration::updateValue($optname, $optvalue, true);
    }

    // If I don't do this the DOOFINDER_SCRIPT_* values loose PHP_EOLs.
    Configuration::loadConfiguration();

    $cfgStrValues = array(
      // 'DOOFINDER_INPUT_WIDTH' => $this->l('Doofinder Searchbox Width'),
      // 'DOOFINDER_INPUT_TOP' => $this->l('Doofinder Searchbox Offset Top'),
      // 'DOOFINDER_INPUT_LEFT' => $this->l('Doofinder Searchbox Offset Left'),
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

    $defaultLanguage = intval(self::cfg('PS_LANG_DEFAULT'));
    $default_currency = Currency::getDefaultCurrency();
    $languages = Language::getLanguages();
    $yesNoChoices = array(
      self::NO  => $this->l('No'),
      self::YES => $this->l('Yes'),
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
    // DATA FEED SETTINGS
    //

    $this->_html .= dfForm::fieldset($this->l('Data Feed Settings'));

    $optname = 'DF_GS_IMAGE_SIZE';
    $optvalue = self::cfg($optname);
    $field = dfForm::getSelectFor($optname, $optvalue, dfTools::getAvailableImageSizes());
    $label = $this->l('Product Image Size');
    $this->_html .= dfForm::wrapField($optname, $label, $field);


    $optname = 'DF_GS_DESCRIPTION_TYPE';
    $optvalue = self::cfg($optname);
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
      $optvalue = self::cfg($optname, $default_currency->iso_code);
      $field = dfForm::getSelectFor($optname, $optvalue, dfTools::getAvailableCurrencies());
      $label = sprintf($this->l("Currency for %s"), $lang['name']);
      $this->_html .= dfForm::wrapField($optname, $label, $field);
    }

    // DF_FETCH_FEED_MODE
    $optname = 'DF_FETCH_FEED_MODE';
    $optvalue = self::cfg($optname, self::FETCH_MODE_ALT1);
    $choices = array(
      self::FETCH_MODE_FAST => $this->l('Fastest (experimental)'),
      self::FETCH_MODE_ALT1 => $this->l('Normal (default)'),
      self::FETCH_MODE_ALT2 => $this->l('Slower'),
      );
    $field = dfForm::getSelectFor($optname, $optvalue, $choices);
    $label = $this->l('Feed Generation Mode');
    $options = array('desc' => $this->l('If the feed is not generated try changing this value.'));
    $this->_html .= dfForm::wrapField($optname, $label, $field, $options);


    // DF_GS_DISPLAY_PRICES
    $optname = 'DF_GS_DISPLAY_PRICES';
    $optvalue = self::cfg($optname, self::YES);
    $field = dfForm::getSelectFor($optname, $optvalue, $yesNoChoices);
    $label = $this->l('Display Prices in Data Feed');
    $this->_html .= dfForm::wrapField($optname, $label, $field);


    // DF_GS_PRICES_USE_TAX
    $optname = 'DF_GS_PRICES_USE_TAX';
    $optvalue = self::cfg($optname, self::YES);
    $field = dfForm::getSelectFor($optname, $optvalue, $yesNoChoices);
    $label = $this->l('Display Prices With Taxes');
    $this->_html .= dfForm::wrapField($optname, $label, $field);

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

      $url = dfTools::getFeedURL($lang['iso_code']);

      $optname = $baseoptname.strtoupper($lang['iso_code']);
      $optvalue = self::cfg($optname);
      $attrs = array('cols' => 100, 'rows' => 10, 'class' => 'df-script');
      $extra = array('desc' => sprintf('<span class="df-notice"><b>%s [%s]:</b> <a href="%s" target="_blank">%s</a></span>%s', $this->l('Data Feed URL'), strtoupper($lang['iso_code']), $url, htmlentities($url), $desc));
      $field = dfForm::getTextareaFor($optname, $optvalue, $attrs);
      $label = $lang['name'];
      $this->_html .= dfForm::wrapField($optname, $label, $field, $extra);
    }

    // DF_EXTRA_CSS
    $optname = 'DF_EXTRA_CSS';
    $optvalue = self::cfg($optname);
    $attrs = array('cols' => 100, 'rows' => 10, 'class' => 'df-script');
    $extra = array('desc' => $this->l('Extra CSS to adjust Doofinder to your template.'));
    $field = dfForm::getTextareaFor($optname, $optvalue, $attrs);
    $label = $this->l('Extra CSS');
    $this->_html .= dfForm::wrapField($optname, $label, $field, $extra);

    $this->_html .= $submitButton;
    $this->_html .= '</fieldset>';


    //
    // SEARCH BOX
    //

    $this->_html .= dfForm::fieldset($this->l('Searchbox in Page Top'));

    // DF_DISPLAY_SEARCHBOX
    $optname = 'DF_DISPLAY_SEARCHBOX';
    $optvalue = self::cfg($optname, self::YES);
    $field = dfForm::getSelectFor($optname, $optvalue, $yesNoChoices);
    $label = $this->l('Display Searchbox');
    $this->_html .= dfForm::wrapField($optname, $label, $field);

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

  public static function cfg($key, $default=null)
  {
    $v = Configuration::get($key);
    if ($v !== false)
      return $v;
    return $default;
  }
}
