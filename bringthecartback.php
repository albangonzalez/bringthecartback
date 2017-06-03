<?php
/**
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2017 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class BringTheCartBack extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'bringthecartback';
        $this->tab = 'emailing';
        $this->version = '0.1.0';
        $this->author = 'Traxlead';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Bring the Cart Back');
        $this->description = $this->l('This module allows you to send emails to identified clients who didn\'t finish the checkout process. This bring back a cart to a client through a reminder.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        $this->ps_versions_compliancy = array('min' => '1.6.x', 'max' => '1.7.x');
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        Configuration::updateValue('BCB_REMINDER_LIMIT', 7);
        Configuration::updateValue('BCB_FALSE_ABANDONNED_CART_LIMIT', 3);
        

        $this->installModuleTab();

        return parent::install();
    }
    private function installModuleTab()
    {

        $languageId['en'] = LanguageCore::getIdByIso('en');
        $languageId['fr'] = LanguageCore::getIdByIso('fr');
        $adminOrdersTabId = Tab::getIdFromClassName('AdminParentOrders');
        
        if($adminOrdersTabId != 0)
        {
            // Bring the Cart Back module tab creation
            $moduleTab = new Tab();
            $moduleTab->id_parent = $adminOrdersTabId;
            $moduleTab->name = array(
                $languageId['en'] => 'Bring the Cart Back',
                $languageId['fr'] => 'Relance des paniers'
            );
            $moduleTab->class_name = 'AdminBringTheCartBack';
            $moduleTab->module = $this->name;
            
            if(!$moduleTab->save())
            {
                return 0;
            }
        }
    }
    private function uninstallModuleTab()
    {
        $tabId = Tab::getIdFromClassName('AdminBringTheCartBack');
        if ($tabId != 0)
        {
            $tabId = new Tab($tabId);
            $tabId->delete();

            return true;
        }
        return false;
    }

    public function uninstall()
    {
        $this->uninstallModuleTab();

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

}

?>