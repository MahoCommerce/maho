<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Core
 */
class Mage_Core_Block_Profiler extends Mage_Core_Block_Abstract
{
    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->_beforeToHtml()
            || !Mage::getStoreConfig('dev/debug/profiler')
            || !Mage::helper('core')->isDevAllowed()
        ) {
            return '';
        }

        $timers = Varien_Profiler::getTimers();

        $emalloc = memory_get_usage();
        $memoryUsage = memory_get_usage(true);
        $emallocMB = number_format($emalloc / 1048576, 2);
        $memoryUsageMB = number_format($memoryUsage / 1048576, 2);

        $out = "<a href=\"javascript:void(0)\" onclick=\"document.getElementById('profiler_section').style.display=document.getElementById('profiler_section').style.display=='block'?'none':'block'\">[profiler]</a>";
        $out .= '<div id="profiler_section" style="background:white;display:block">';
        $out .= '<pre>Memory usage: real: ' . $memoryUsage . '(' . $memoryUsageMB . 'MB), emalloc: ' . $emalloc . '(' . $emallocMB . 'MB)</pre>';
        $out .= '<table border="1" cellspacing="0" cellpadding="2" style="width:auto">';
        $out .= '<tr><th>Code Profiler</th><th>Time</th><th>Cnt</th><th>Emalloc</th><th>RealMem</th></tr>';
        foreach ($timers as $name => $timer) {
            $sum = Varien_Profiler::fetch($name, 'sum');
            $count = Varien_Profiler::fetch($name, 'count');
            $realmem = Varien_Profiler::fetch($name, 'realmem');
            $emalloc = Varien_Profiler::fetch($name, 'emalloc');
            if ($sum < .0010 && $count < 10 && $emalloc < 10000) {
                continue;
            }
            $out .= '<tr>'
                . '<td align="left">' . $name . '</td>'
                . '<td>' . number_format($sum, 4) . '</td>'
                . '<td align="right">' . $count . '</td>'
                . '<td align="right">' . number_format($emalloc) . '</td>'
                . '<td align="right">' . number_format($realmem) . '</td>'
                . '</tr>'
            ;
        }
        $out .= '</table>';
        $out .= '<pre>';
        $out .= print_r(Varien_Profiler::getSqlProfiler(Mage::getSingleton('core/resource')->getConnection('core_write')), 1);
        $out .= '</pre>';
        $out .= '</div>';

        return $out;
    }
}
