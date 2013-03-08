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
    $this->version = "0.1";
    $this->author = "Doofinder (http://www.doofinder.com)";
    $this->ps_versions_compliancy = array('min' => '1.4', 'max' => '1.6');

    parent::__construct();

    $this->displayName = $this->l('Doofinder');
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
    $script = Configuration::get("DOOFINDER_SCRIPT_$lang");

    $this->smarty->assign(array(
      'ENT_QUOTES' => ENT_QUOTES,
      'script' => $script,
      'enabled' => !(bool)Configuration::get('DF_SC_TESTMODE'),
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
    $this->configureHookCommon($params);
    $this->smarty->assign(array(
      'hook_top' => true,
      'placeholder' => $this->l('Enter a product name to search'),
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

    foreach (Language::getLanguages() as $lang)
    {
      $total++;
      $optname = 'DOOFINDER_SCRIPT_'.strtoupper($lang['iso_code']);
      if (Configuration::deleteByName($optname))
        $uninstalled++;
    }

    return parent::uninstall() && ($total == $uninstalled) &&
           Configuration::deleteByName('DF_SC_TESTMODE') &&
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

    $found = 0;
    $total = 0;
    foreach (Language::getLanguages() as $lang)
    {
      $total++;
      $optname = 'DOOFINDER_SCRIPT_'.strtoupper($lang['iso_code']);
      if (Configuration::get($optname))
        $found++;
    }

    if (!$found)
    {
      $this->_html .= $this->displayError($this->l("Don't forget to setup your Doofinder script!"));
    }
    elseif ($found < $total)
    {
      $this->_html .= $this->displayError($this->l('Doofinder will only work for the configured languages.'));
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
      'DF_GS_DESCRIPTION_TYPE' => $this->l('Product Description length'),
      'DF_SC_TESTMODE' => $this->l('Doofinder Test Mode'),
      );

    foreach ($cfgIntValues as $optname => $optname_alt)
    {
      $optvalue = intval((Tools::getValue($optname)));

      if (Validate::isGenericName($optvalue))
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

    foreach (Language::getLanguages() as $lang)
    {
      $optname = 'DOOFINDER_SCRIPT_'.strtoupper($lang['iso_code']);
      Configuration::updateValue($optname, Tools::getValue($optname), true);
    }
  }


  /**
   * Configures the settings form and generates its output.
   */
  protected function _displayForm()
  {
    $helper = new HelperForm();
    $fields = array();

    $default_lang_id = (int) Configuration::get('PS_LANG_DEFAULT');

    //
    // DATA FEED SETTINGS
    //

    $fields_form[0]['form'] = array(
      'legend' => array('title' => $this->l('Data Feed Settings')),
      'input'  => null,
      //'submit' => array('title' => $this->l('Save'), 'class' => 'button'),
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


    $fields_form[0]['form']['input'] = $fields;


    //
    // DOOFINDER SCRIPT SETTINGS
    //

    $fields = array();

    $fields_form[1]['form'] = array(
      'legend' => array('title' => $this->l('Doofinder Script')),
      'input'  => null,
      'submit' => array('title' => $this->l('Save'), 'class' => 'button'),
      );

    // DOOFINDER_SCRIPT
    $optname = 'DOOFINDER_SCRIPT_';

    foreach (Language::getLanguages(true) as $lang)
    {
      $realoptname = $optname.strtoupper($lang['iso_code']);
      $fields[] = array(
        'label' => $lang['name'],
        'desc' => $this->l("Copy the script as you got it from Doofinder."),

        'type' => 'textarea',
        'cols' => 100,
        'rows' => 10,
        'name' => $realoptname,
        'class' => 'doofinder_script',
        'required' => false,
        );

      $helper->fields_value[$realoptname] = Configuration::get($realoptname);
    }

    // DF_SC_TESTMODE
    $optname = 'DF_SC_TESTMODE';
    $optcfg = $this->getYesNoSelectFor($optname, $this->l('Doofinder Test Mode'));
    $optcfg['desc'] = $this->l('Deactivates the layer script to execute the layer installer with no problems.');
    $fields[] = $optcfg;
    $helper->fields_value[$optname] = Configuration::get($optname);


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
          array($optname => 0, 'name' => $this->l('No')),
          array($optname => 1, 'name' => $this->l('Yes')),
          ),
        'id' => $optname,
        'name' => 'name',
        ),
      'name' => $optname,
      );
  }
}
