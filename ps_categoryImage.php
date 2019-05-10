<?php
/**
* 2007-2019 PrestaShop
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
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Ps_categoryImage extends Module
{
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'ps_categoryImage';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'webimpacto';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('categoryImage');
        $this->description = $this->l('Módulo que imprime en el front el titulo de las categorías con su imagen.');
        $this->confirmUninstall = $this->l('¿Estás seguro de que desea desinstalar el módulo?');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('PS_CATEGORYIMAGE_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('displayHome');
    }

    public function uninstall()
    {
        Configuration::deleteByName('PS_CATEGORYIMAGE_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    public function getContent()
    {
        $html;

        $this->context->smarty->assign('module_dir', $this->_path);
        $html .= $this->renderForm();

        if (((bool)Tools::isSubmit('submitCategoryImageModule'))) {
            if ($this->boolCategoryDisplayed($this->postProcess())) {
                Db::getInstance()->execute("INSERT INTO "._DB_PREFIX_."categoryImage(id_category) values(".$this->postProcess().")");
            } else {
                $html .= $this->display(__FILE__, 'views/templates/admin/error.tpl');
            }
        }

        if (((bool)Tools::isSubmit('deletecategoryImage'))) {
            $idToDelete = $_REQUEST['id_ps_categoryImage'];
            Db::getInstance()->execute("DELETE FROM "._DB_PREFIX_."categoryImage WHERE id_ps_categoryImage = $idToDelete");
        }

        $html .= $this->renderList();

        return $html;
    }

    public function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCategoryImageModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        return $helper->generateForm(array($this->getConfigForm()));
    }

    public function getConfigForm()
    {
        $categories = $this->getCategories();
        $categoriesNameId = array();

        foreach($categories as $values) {
            $categoriesNameId[$values['id_category']] = array('id' => $values['id_category'], 'name' => $values['name']);
        }

        return array(
            'form' => array(
                'name' => 'test',
                'legend' => array(
                'title' => 'Configuración',
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->trans('Categorías', array(), 'Admin.Global'),
                        'name' => 'CATEGORYIMAGE_ACCOUNT_CATEGORY',
                        'col' => '4',
                        'options' => array(
                            'query' => $categories,
                            'id' => 'id_category',
                            'name' => 'name',
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'), 
                ),
            ),
        );  
    }

    public function renderList()
    {

        $fields_list = array(
            'id_ps_categoryImage' => array(
                'title' => $this->trans('Id', array(), 'Admin.Global'),
                'search' => false,
            ),
            'title' => array(
                'title' => $this->trans('Título', array(), 'Admin.Global'),
                'search' => false,
            ),
        );

        if (!Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            unset($fields_list['shop_name']);
        }

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->no_link = true;
        $helper_list->show_toolbar = true;
        $helper_list->simple_header = false;
        $helper_list->identifier = 'id_ps_categoryImage';
        $helper_list->table = 'categoryImage';
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name;
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->actions = array (
            'delete'
        );

        $categories = $this->getCategoriesDisplayed();
        $categoriesDisplayed = Db::getInstance()->ExecuteS("SELECT * FROM ". _DB_PREFIX_ ."categoryImage");

        return $helper_list->generateList($categories, $fields_list);
    }

    /**
     * @return array
     */
    function getCategoriesDisplayed() 
    {
        $id_lang = is_null($id_lang) ? Context::getContext()->language->id : (int)$id_lang;
        $id_shop = Context::getContext()->shop->id;

        $categories = array();

        $categoriesDisplayed = Db::getInstance()->ExecuteS("SELECT * FROM ". _DB_PREFIX_ ."categoryImage");
        foreach($categoriesDisplayed as $key => $value) {
            $title;
            $titleUri;
            $name = Db::getInstance()->ExecuteS("SELECT ". _DB_PREFIX_ ."category_lang.name FROM ". _DB_PREFIX_ ."category_lang
                WHERE id_category = ". $value['id_category'] ." AND id_shop = $id_shop AND id_lang = $id_lang");
            foreach($name as $val) {
                $title = $val['name'];
            }
            $temp = array(
                'id_ps_categoryImage' => $value['id_ps_categoryImage'],
                'id_category' => $value['id_category'],
                'position' => $value['position'],
                'title' => $title
            );
            array_push($categories, $temp);
        }

        return $categories;
    }

    /**
     * @return array
     */
    function getCategories()
    {
        $id_lang = is_null($id_lang) ? Context::getContext()->language->id : (int)$id_lang;
        $id_shop = Context::getContext()->shop->id;

        return Db::getInstance()->ExecuteS("SELECT * FROM ". _DB_PREFIX_ ."category_lang
            WHERE id_shop = $id_shop AND id_lang = $id_lang");
    }

    function getConfigFormValues()
    {
        return Tools::getValue('CATEGORYIMAGE_ACCOUNT_CATEGORY'. Configuration::get('CATEGORYIMAGE_ACCOUNT_CATEGORY'));
    }

    /**
     * @return int
     */
    function postProcess()
    {
        return $this->getConfigFormValues();
    }

    function boolCategoryDisplayed($id)
    {
        if (count(Db::getInstance()->ExecuteS("SELECT id_category FROM ". _DB_PREFIX_ ."categoryImage WHERE id_category = $id")) != 0) {   
            return false;
        } else {
            return true;
        }
    }
    
    function getCategoryIdTitleDisplayed()
    {
        $ids = array();
        $idsDisplayed = Db::getInstance()->ExecuteS("SELECT id_category FROM ". _DB_PREFIX_ ."categoryImage");   
        foreach ($idsDisplayed as $value) {
            array_push($ids, $value['id_category']);
        }
        return $ids;
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookDisplayHome()
    {
        $this->context->smarty->assign('datos', $this->getCategoriesDisplayed());
        return $this->display(__FILE__,'categoryImage.tpl');
    }
}
