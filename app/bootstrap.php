<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

// Require the autoloader if not already loaded
if (!class_exists('Mage')) {
    if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
        require __DIR__ . '/../vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
        require __DIR__ . '/../../../autoload.php';
    } else {
        throw new Exception('Autoloader not found. Please run \'composer install\'.');
    }
}

// Varien to Maho namespace aliases for backward compatibility
// Create eager aliases so type hints work correctly with inheritance
// TODO: Migrate all core Maho code from Varien_* to Maho\* namespace, then change the default value to 0
if (($_ENV['MAHO_ENABLE_VARIEN_ALIASES'] ?? $_SERVER['MAHO_ENABLE_VARIEN_ALIASES'] ?? '1') !== '0') {
    class_alias(\Maho\Convert::class, 'Varien_Convert');
    class_alias(\Maho\Convert\Action::class, 'Varien_Convert_Action');
    class_alias(\Maho\Convert\Action\AbstractAction::class, 'Varien_Convert_Action_Abstract');
    class_alias(\Maho\Convert\Action\ActionInterface::class, 'Varien_Convert_Action_Interface');
    class_alias(\Maho\Convert\Container\AbstractContainer::class, 'Varien_Convert_Container_Abstract');
    class_alias(\Maho\Convert\Container\Collection::class, 'Varien_Convert_Container_Collection');
    class_alias(\Maho\Convert\Container\Generic::class, 'Varien_Convert_Container_Generic');
    class_alias(\Maho\Convert\Container\ContainerInterface::class, 'Varien_Convert_Container_Interface');
    class_alias(\Maho\Convert\Exception::class, 'Varien_Convert_Exception');
    class_alias(\Maho\Convert\Mapper\AbstractMapper::class, 'Varien_Convert_Mapper_Abstract');
    class_alias(\Maho\Convert\Mapper\Column::class, 'Varien_Convert_Mapper_Column');
    class_alias(\Maho\Convert\Mapper\MapperInterface::class, 'Varien_Convert_Mapper_Interface');
    class_alias(\Maho\Convert\Parser\AbstractParser::class, 'Varien_Convert_Parser_Abstract');
    class_alias(\Maho\Convert\Parser\Csv::class, 'Varien_Convert_Parser_Csv');
    class_alias(\Maho\Convert\Parser\ParserInterface::class, 'Varien_Convert_Parser_Interface');
    class_alias(\Maho\Convert\Parser\Serialize::class, 'Varien_Convert_Parser_Serialize');
    class_alias(\Maho\Convert\Parser\Xml\Excel::class, 'Varien_Convert_Parser_Xml_Excel');
    class_alias(\Maho\Convert\Profile::class, 'Varien_Convert_Profile');
    class_alias(\Maho\Convert\Profile\AbstractProfile::class, 'Varien_Convert_Profile_Abstract');
    class_alias(\Maho\Convert\Profile\Collection::class, 'Varien_Convert_Profile_Collection');
    class_alias(\Maho\Data\Collection::class, 'Varien_Data_Collection');
    class_alias(\Maho\Data\Collection\Db::class, 'Varien_Data_Collection_Db');
    class_alias(\Maho\Data\Collection\Filesystem::class, 'Varien_Data_Collection_Filesystem');
    class_alias(\Maho\Data\Form::class, 'Varien_Data_Form');
    class_alias(\Maho\Data\Form\AbstractForm::class, 'Varien_Data_Form_Abstract');
    class_alias(\Maho\Data\Form\Element\AbstractElement::class, 'Varien_Data_Form_Element_Abstract');
    class_alias(\Maho\Data\Form\Element\Boolean::class, 'Varien_Data_Form_Element_Boolean');
    class_alias(\Maho\Data\Form\Element\Button::class, 'Varien_Data_Form_Element_Button');
    class_alias(\Maho\Data\Form\Element\Checkbox::class, 'Varien_Data_Form_Element_Checkbox');
    class_alias(\Maho\Data\Form\Element\Checkboxes::class, 'Varien_Data_Form_Element_Checkboxes');
    class_alias(\Maho\Data\Form\Element\Collection::class, 'Varien_Data_Form_Element_Collection');
    class_alias(\Maho\Data\Form\Element\Color::class, 'Varien_Data_Form_Element_Color');
    class_alias(\Maho\Data\Form\Element\Column::class, 'Varien_Data_Form_Element_Column');
    class_alias(\Maho\Data\Form\Element\Date::class, 'Varien_Data_Form_Element_Date');
    class_alias(\Maho\Data\Form\Element\Datetime::class, 'Varien_Data_Form_Element_Datetime');
    class_alias(\Maho\Data\Form\Element\Editor::class, 'Varien_Data_Form_Element_Editor');
    class_alias(\Maho\Data\Form\Element\Fieldset::class, 'Varien_Data_Form_Element_Fieldset');
    class_alias(\Maho\Data\Form\Element\File::class, 'Varien_Data_Form_Element_File');
    class_alias(\Maho\Data\Form\Element\Gallery::class, 'Varien_Data_Form_Element_Gallery');
    class_alias(\Maho\Data\Form\Element\Hidden::class, 'Varien_Data_Form_Element_Hidden');
    class_alias(\Maho\Data\Form\Element\Image::class, 'Varien_Data_Form_Element_Image');
    class_alias(\Maho\Data\Form\Element\Imagefile::class, 'Varien_Data_Form_Element_Imagefile');
    class_alias(\Maho\Data\Form\Element\Info::class, 'Varien_Data_Form_Element_Info');
    class_alias(\Maho\Data\Form\Element\Label::class, 'Varien_Data_Form_Element_Label');
    class_alias(\Maho\Data\Form\Element\Link::class, 'Varien_Data_Form_Element_Link');
    class_alias(\Maho\Data\Form\Element\Multiline::class, 'Varien_Data_Form_Element_Multiline');
    class_alias(\Maho\Data\Form\Element\Multiselect::class, 'Varien_Data_Form_Element_Multiselect');
    class_alias(\Maho\Data\Form\Element\Note::class, 'Varien_Data_Form_Element_Note');
    class_alias(\Maho\Data\Form\Element\Obscure::class, 'Varien_Data_Form_Element_Obscure');
    class_alias(\Maho\Data\Form\Element\Password::class, 'Varien_Data_Form_Element_Password');
    class_alias(\Maho\Data\Form\Element\Radio::class, 'Varien_Data_Form_Element_Radio');
    class_alias(\Maho\Data\Form\Element\Radios::class, 'Varien_Data_Form_Element_Radios');
    class_alias(\Maho\Data\Form\Element\Renderer\RendererInterface::class, 'Varien_Data_Form_Element_Renderer_Interface');
    class_alias(\Maho\Data\Form\Element\Reset::class, 'Varien_Data_Form_Element_Reset');
    class_alias(\Maho\Data\Form\Element\Select::class, 'Varien_Data_Form_Element_Select');
    class_alias(\Maho\Data\Form\Element\Submit::class, 'Varien_Data_Form_Element_Submit');
    class_alias(\Maho\Data\Form\Element\Text::class, 'Varien_Data_Form_Element_Text');
    class_alias(\Maho\Data\Form\Element\Textarea::class, 'Varien_Data_Form_Element_Textarea');
    class_alias(\Maho\Data\Form\Element\Time::class, 'Varien_Data_Form_Element_Time');
    class_alias(\Maho\Data\Form\Filter\Date::class, 'Varien_Data_Form_Filter_Date');
    class_alias(\Maho\Data\Form\Filter\Datetime::class, 'Varien_Data_Form_Filter_Datetime');
    class_alias(\Maho\Data\Form\Filter\Escapehtml::class, 'Varien_Data_Form_Filter_Escapehtml');
    class_alias(\Maho\Data\Form\Filter\FilterInterface::class, 'Varien_Data_Form_Filter_Interface');
    class_alias(\Maho\Data\Form\Filter\Striptags::class, 'Varien_Data_Form_Filter_Striptags');
    class_alias(\Maho\Data\Tree::class, 'Varien_Data_Tree');
    class_alias(\Maho\Data\Tree\Dbp::class, 'Varien_Data_Tree_Dbp');
    class_alias(\Maho\Data\Tree\Node::class, 'Varien_Data_Tree_Node');
    class_alias(\Maho\Data\Tree\Node\Collection::class, 'Varien_Data_Tree_Node_Collection');
    class_alias(\Maho\Db\Expr::class, 'Varien_Db_Expr');
    class_alias(\Maho\Db\Exception::class, 'Varien_Db_Exception');
    class_alias(\Maho\Db\Select::class, 'Varien_Db_Select');
    class_alias(\Maho\Db\Helper::class, 'Varien_Db_Helper');
    class_alias(\Maho\Db\Adapter\AdapterInterface::class, 'Varien_Db_Adapter_Interface');
    class_alias(\Maho\Db\Adapter\Pdo\Mysql::class, 'Varien_Db_Adapter_Pdo_Mysql');
    class_alias(\Maho\Db\Ddl\Table::class, 'Varien_Db_Ddl_Table');
    class_alias(\Maho\Db\Statement\Parameter::class, 'Varien_Db_Statement_Parameter');
    class_alias(\Maho\Db\Statement\Pdo\Mysql::class, 'Varien_Db_Statement_Pdo_Mysql');
    class_alias(\Maho\Event::class, 'Varien_Event');
    class_alias(\Maho\Event\Collection::class, 'Varien_Event_Collection');
    class_alias(\Maho\Event\Observer::class, 'Varien_Event_Observer');
    class_alias(\Maho\Event\Observer\Collection::class, 'Varien_Event_Observer_Collection');
    class_alias(\Maho\File\Csv::class, 'Varien_File_Csv');
    class_alias(\Maho\File\Uploader::class, 'Varien_File_Uploader');
    class_alias(\Maho\Filter\ArrayFilter::class, 'Varien_Filter_Array');
    class_alias(\Maho\Filter\ArrayFilter\Grid::class, 'Varien_Filter_Array_Grid');
    class_alias(\Maho\Filter\Email::class, 'Varien_Filter_Email');
    class_alias(\Maho\Filter\FormElementName::class, 'Varien_Filter_FormElementName');
    class_alias(\Maho\Filter\ObjectFilter::class, 'Varien_Filter_Object');
    class_alias(\Maho\Filter\ObjectFilter\Grid::class, 'Varien_Filter_Object_Grid');
    class_alias(\Maho\Filter\Sprintf::class, 'Varien_Filter_Sprintf');
    class_alias(\Maho\Filter\Template::class, 'Varien_Filter_Template');
    class_alias(\Maho\Filter\Template\Simple::class, 'Varien_Filter_Template_Simple');
    class_alias(\Maho\Filter\Template\Tokenizer\AbstractTokenizer::class, 'Varien_Filter_Template_Tokenizer_Abstract');
    class_alias(\Maho\Filter\Template\Tokenizer\Parameter::class, 'Varien_Filter_Template_Tokenizer_Parameter');
    class_alias(\Maho\Filter\Template\Tokenizer\Variable::class, 'Varien_Filter_Template_Tokenizer_Variable');
    class_alias(\Maho\Io::class, 'Varien_Io_Abstract');
    class_alias(\Maho\Io\Exception::class, 'Varien_Io_Exception');
    class_alias(\Maho\Io\File::class, 'Varien_Io_File');
    class_alias(\Maho\Io\Ftp::class, 'Varien_Io_Ftp');
    class_alias(\Maho\Io\IoInterface::class, 'Varien_Io_Interface');
    class_alias(\Maho\Io\Sftp::class, 'Varien_Io_Sftp');
    class_alias(\Maho\DataObject::class, 'Varien_Object');
    class_alias(\Maho\DataObject\Cache::class, 'Varien_Object_Cache');
    class_alias(\Maho\DataObject\Mapper::class, 'Varien_Object_Mapper');
    class_alias(\Maho\Simplexml\Config::class, 'Varien_Simplexml_Config');
    class_alias(\Maho\Simplexml\Element::class, 'Varien_Simplexml_Element');
    class_alias(\Maho\Exception::class, 'Varien_Exception');
    class_alias(\Maho\Profiler::class, 'Varien_Profiler');
}

defined('DS') || define('DS', DIRECTORY_SEPARATOR);
defined('PS') || define('PS', PATH_SEPARATOR);
defined('BP') || define('BP', Maho::getBasePath());

/** @deprecated */
defined('MAGENTO_ROOT') || define('MAGENTO_ROOT', BP);

if (!empty($_SERVER['MAGE_IS_DEVELOPER_MODE']) || !empty($_ENV['MAGE_IS_DEVELOPER_MODE'])) {
    Mage::setIsDeveloperMode(true);

    ini_set('display_errors', '1');
    ini_set('error_prepend_string', '<pre>');
    ini_set('error_append_string', '</pre>');

    // Fix for overriding zf1-future during development
    ini_set('opcache.revalidate_path', 1);

    // Update Composer's autoloader during development in case new files are added
    Maho::updateComposerAutoloader();

    // Check if we used `composer dump --optimize-autoloader` in development
    if (Maho::isComposerAutoloaderOptimized()) {
        Mage::addBootupWarning('Optimized autoloader detected in developer mode.');
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            \Symfony\Component\VarDumper\VarDumper::dump($var);
        }
        die();
    }
}
