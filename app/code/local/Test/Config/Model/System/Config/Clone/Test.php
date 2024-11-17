<?php
class Test_Config_Model_System_Config_Clone_Test extends Mage_Core_Model_Config_Data
{
    public function getPrefixes()
    {
        $prefixes = [];

        for ($i = 1; $i <= 3; $i++) {
            $prefixes[] = [
                'field' => "test_{$i}_",
                'label' => "Test $i",
            ];
        }

        return $prefixes;
    }
}
