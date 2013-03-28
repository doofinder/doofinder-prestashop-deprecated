<?php
/**
 * Doofinder
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


  public function __construct()
  {
    $this->name = "doofinder";
    $this->tab = "search_filter";
    $this->version = "1.1.2";
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
      'searchbox_enabled' => (int) Configuration::get('DOOFINDER_INPUT_ENABLED'),
      'script' => Configuration::get("DOOFINDER_SCRIPT_$lang"),
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
    $width = Configuration::get("DOOFINDER_INPUT_WIDTH");
    $top = Configuration::get("DOOFINDER_INPUT_TOP");
    $left = Configuration::get("DOOFINDER_INPUT_LEFT");
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


  public function uninstall()
  {
    $total = 0;
    $uninstalled = 0;
    $cfgLangStrValues = array('DOOFINDER_SCRIPT_');

    foreach (Language::getLanguages() as $lang)
    {
      foreach ($cfgLangStrValues as $prefix)
      {
        $total++;
        $optname = $prefix.strtoupper($lang['iso_code']);
        if (Configuration::deleteByName($optname))
          $uninstalled++;
      }
    }

    return parent::uninstall() && ($total == $uninstalled) &&
           Configuration::deleteByName('DF_GS_DESCRIPTION_TYPE') &&
           Configuration::deleteByName('DF_GS_IMAGE_SIZE');
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

    $cfgLangStrValues = array('DOOFINDER_SCRIPT_' => true);
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
    $default_lang_id = (int) Configuration::get('PS_LANG_DEFAULT');



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
    $helper->fields_value[$optname] = Configuration::get($optname);


    $optname = 'DOOFINDER_INPUT_WIDTH';
    $fields[] = array(
      'label' => $this->l('Searchbox Width'),
      'desc' => 'i.e: 396px',
      'name' => $optname,
      'type' => 'text',
      'class' => 'doofinder_dimensions',
      );
    $helper->fields_value[$optname] = Configuration::get($optname, '');


    $optname = 'DOOFINDER_INPUT_TOP';
    $fields[] = array(
      'label' => $this->l('Top Position'),
      'desc' => 'i.e: 48px',
      'name' => $optname,
      'type' => 'text',
      'class' => 'doofinder_dimensions',
      );
    $helper->fields_value[$optname] = Configuration::get($optname, '');


    $optname = 'DOOFINDER_INPUT_LEFT';
    $fields[] = array(
      'label' => $this->l('Left Position'),
      'desc' => 'i.e: 50%',
      'name' => $optname,
      'type' => 'text',
      'class' => 'doofinder_dimensions',
      );
    $helper->fields_value[$optname] = Configuration::get($optname, '');


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

    $helper->fields_value[$optname] = Configuration::get($optname);


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

    $helper->fields_value[$optname] = Configuration::get($optname);


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

      $helper->fields_value[$realoptname] = Configuration::get($realoptname);
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
          array($optname => '0', 'name' => $this->l('No')),
          array($optname => '1', 'name' => $this->l('Yes')),
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
}
