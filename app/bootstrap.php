<?php

/**
 * Maho
 *
 * @package    Mage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
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
// These are registered as lazy aliases through the autoloader
spl_autoload_register(function ($class) {
    static $aliases = [
        // Convert classes
        'Varien_Convert' => \Maho\Convert::class,
        'Varien_Convert_Action' => \Maho\Convert\Action::class,
        'Varien_Convert_Action_Abstract' => \Maho\Convert\Action\AbstractAction::class,
        'Varien_Convert_Action_Interface' => \Maho\Convert\Action\ActionInterface::class,
        'Varien_Convert_Container_Abstract' => \Maho\Convert\Container\AbstractContainer::class,
        'Varien_Convert_Container_Collection' => \Maho\Convert\Container\Collection::class,
        'Varien_Convert_Container_Generic' => \Maho\Convert\Container\Generic::class,
        'Varien_Convert_Container_Interface' => \Maho\Convert\Container\ContainerInterface::class,
        'Varien_Convert_Exception' => \Maho\Convert\Exception::class,
        'Varien_Convert_Mapper_Abstract' => \Maho\Convert\Mapper\AbstractMapper::class,
        'Varien_Convert_Mapper_Column' => \Maho\Convert\Mapper\Column::class,
        'Varien_Convert_Mapper_Interface' => \Maho\Convert\Mapper\MapperInterface::class,
        'Varien_Convert_Parser_Abstract' => \Maho\Convert\Parser\AbstractParser::class,
        'Varien_Convert_Parser_Csv' => \Maho\Convert\Parser\Csv::class,
        'Varien_Convert_Parser_Interface' => \Maho\Convert\Parser\ParserInterface::class,
        'Varien_Convert_Parser_Serialize' => \Maho\Convert\Parser\Serialize::class,
        'Varien_Convert_Parser_Xml_Excel' => \Maho\Convert\Parser\Xml\Excel::class,
        'Varien_Convert_Profile' => \Maho\Convert\Profile::class,
        'Varien_Convert_Profile_Abstract' => \Maho\Convert\Profile\AbstractProfile::class,
        'Varien_Convert_Profile_Collection' => \Maho\Convert\Profile\Collection::class,

        // Data classes
        'Varien_Data_Collection' => \Maho\Data\Collection::class,
        'Varien_Data_Collection_Db' => \Maho\Data\Collection\Db::class,
        'Varien_Data_Collection_Filesystem' => \Maho\Data\Collection\Filesystem::class,
        'Varien_Data_Form' => \Maho\Data\Form::class,
        'Varien_Data_Form_Abstract' => \Maho\Data\Form\AbstractForm::class,
        'Varien_Data_Form_Element_Abstract' => \Maho\Data\Form\Element\AbstractElement::class,
        'Varien_Data_Form_Element_Boolean' => \Maho\Data\Form\Element\Boolean::class,
        'Varien_Data_Form_Element_Button' => \Maho\Data\Form\Element\Button::class,
        'Varien_Data_Form_Element_Checkbox' => \Maho\Data\Form\Element\Checkbox::class,
        'Varien_Data_Form_Element_Checkboxes' => \Maho\Data\Form\Element\Checkboxes::class,
        'Varien_Data_Form_Element_Collection' => \Maho\Data\Form\Element\Collection::class,
        'Varien_Data_Form_Element_Color' => \Maho\Data\Form\Element\Color::class,
        'Varien_Data_Form_Element_Column' => \Maho\Data\Form\Element\Column::class,
        'Varien_Data_Form_Element_Date' => \Maho\Data\Form\Element\Date::class,
        'Varien_Data_Form_Element_Datetime' => \Maho\Data\Form\Element\Datetime::class,
        'Varien_Data_Form_Element_Editor' => \Maho\Data\Form\Element\Editor::class,
        'Varien_Data_Form_Element_Fieldset' => \Maho\Data\Form\Element\Fieldset::class,
        'Varien_Data_Form_Element_File' => \Maho\Data\Form\Element\File::class,
        'Varien_Data_Form_Element_Gallery' => \Maho\Data\Form\Element\Gallery::class,
        'Varien_Data_Form_Element_Hidden' => \Maho\Data\Form\Element\Hidden::class,
        'Varien_Data_Form_Element_Image' => \Maho\Data\Form\Element\Image::class,
        'Varien_Data_Form_Element_Imagefile' => \Maho\Data\Form\Element\Imagefile::class,
        'Varien_Data_Form_Element_Info' => \Maho\Data\Form\Element\Info::class,
        'Varien_Data_Form_Element_Label' => \Maho\Data\Form\Element\Label::class,
        'Varien_Data_Form_Element_Link' => \Maho\Data\Form\Element\Link::class,
        'Varien_Data_Form_Element_Multiline' => \Maho\Data\Form\Element\Multiline::class,
        'Varien_Data_Form_Element_Multiselect' => \Maho\Data\Form\Element\Multiselect::class,
        'Varien_Data_Form_Element_Note' => \Maho\Data\Form\Element\Note::class,
        'Varien_Data_Form_Element_Obscure' => \Maho\Data\Form\Element\Obscure::class,
        'Varien_Data_Form_Element_Password' => \Maho\Data\Form\Element\Password::class,
        'Varien_Data_Form_Element_Radio' => \Maho\Data\Form\Element\Radio::class,
        'Varien_Data_Form_Element_Radios' => \Maho\Data\Form\Element\Radios::class,
        'Varien_Data_Form_Element_Renderer_Interface' => \Maho\Data\Form\Element\Renderer\RendererInterface::class,
        'Varien_Data_Form_Element_Reset' => \Maho\Data\Form\Element\Reset::class,
        'Varien_Data_Form_Element_Select' => \Maho\Data\Form\Element\Select::class,
        'Varien_Data_Form_Element_Submit' => \Maho\Data\Form\Element\Submit::class,
        'Varien_Data_Form_Element_Text' => \Maho\Data\Form\Element\Text::class,
        'Varien_Data_Form_Element_Textarea' => \Maho\Data\Form\Element\Textarea::class,
        'Varien_Data_Form_Element_Time' => \Maho\Data\Form\Element\Time::class,
        'Varien_Data_Form_Filter_Date' => \Maho\Data\Form\Filter\Date::class,
        'Varien_Data_Form_Filter_Datetime' => \Maho\Data\Form\Filter\Datetime::class,
        'Varien_Data_Form_Filter_Escapehtml' => \Maho\Data\Form\Filter\Escapehtml::class,
        'Varien_Data_Form_Filter_Interface' => \Maho\Data\Form\Filter\FilterInterface::class,
        'Varien_Data_Form_Filter_Striptags' => \Maho\Data\Form\Filter\Striptags::class,
        'Varien_Data_Tree' => \Maho\Data\Tree::class,
        'Varien_Data_Tree_Dbp' => \Maho\Data\Tree\Dbp::class,
        'Varien_Data_Tree_Node' => \Maho\Data\Tree\Node::class,
        'Varien_Data_Tree_Node_Collection' => \Maho\Data\Tree\Node\Collection::class,

        // Db classes
        'Varien_Db_Expr' => \Maho\Db\Expr::class,
        'Varien_Db_Exception' => \Maho\Db\Exception::class,
        'Varien_Db_Select' => \Maho\Db\Select::class,
        'Varien_Db_Helper' => \Maho\Db\Helper::class,
        'Varien_Db_Adapter_Interface' => \Maho\Db\Adapter\AdapterInterface::class,
        'Varien_Db_Adapter_Pdo_Mysql' => \Maho\Db\Adapter\Pdo\Mysql::class,
        'Varien_Db_Ddl_Table' => \Maho\Db\Ddl\Table::class,
        'Varien_Db_Statement_Parameter' => \Maho\Db\Statement\Parameter::class,
        'Varien_Db_Statement_Pdo_Mysql' => \Maho\Db\Statement\Pdo\Mysql::class,

        // Event classes
        'Varien_Event' => \Maho\Event::class,
        'Varien_Event_Collection' => \Maho\Event\Collection::class,
        'Varien_Event_Observer' => \Maho\Event\Observer::class,
        'Varien_Event_Observer_Collection' => \Maho\Event\Observer\Collection::class,

        // File classes
        'Varien_File_Csv' => \Maho\File\Csv::class,
        'Varien_File_Uploader' => \Maho\File\Uploader::class,

        // Filter classes
        'Varien_Filter_Array' => \Maho\Filter\ArrayFilter::class,
        'Varien_Filter_Array_Grid' => \Maho\Filter\ArrayNS\Grid::class,
        'Varien_Filter_Email' => \Maho\Filter\Email::class,
        'Varien_Filter_FormElementName' => \Maho\Filter\FormElementName::class,
        'Varien_Filter_Object' => \Maho\Filter\ObjectFilter::class,
        'Varien_Filter_Object_Grid' => \Maho\Filter\ObjectNS\Grid::class,
        'Varien_Filter_Sprintf' => \Maho\Filter\Sprintf::class,
        'Varien_Filter_Template' => \Maho\Filter\Template::class,
        'Varien_Filter_Template_Simple' => \Maho\Filter\Template\Simple::class,
        'Varien_Filter_Template_Tokenizer_Abstract' => \Maho\Filter\Template\Tokenizer\AbstractTokenizer::class,
        'Varien_Filter_Template_Tokenizer_Parameter' => \Maho\Filter\Template\Tokenizer\Parameter::class,
        'Varien_Filter_Template_Tokenizer_Variable' => \Maho\Filter\Template\Tokenizer\Variable::class,

        // Io classes
        'Varien_Io_Abstract' => \Maho\Io\AbstractIo::class,
        'Varien_Io_Exception' => \Maho\Io\Exception::class,
        'Varien_Io_File' => \Maho\Io\File::class,
        'Varien_Io_Ftp' => \Maho\Io\Ftp::class,
        'Varien_Io_Interface' => \Maho\Io\IoInterface::class,
        'Varien_Io_Sftp' => \Maho\Io\Sftp::class,

        // Object classes
        'Varien_Object' => \Maho\DataObject::class,
        'Varien_Object_Cache' => \Maho\DataObject\Cache::class,
        'Varien_Object_Mapper' => \Maho\DataObject\Mapper::class,

        // Simplexml classes
        'Varien_Simplexml_Config' => \Maho\Simplexml\Config::class,
        'Varien_Simplexml_Element' => \Maho\Simplexml\Element::class,

        // Other classes
        'Varien_Exception' => \Maho\Exception::class,
        'Varien_Profiler' => \Maho\Profiler::class,
    ];

    if (isset($aliases[$class])) {
        class_alias($aliases[$class], $class);
    }
}, true, true);

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
