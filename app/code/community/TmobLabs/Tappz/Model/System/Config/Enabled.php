<?php

class TmobLabs_Tappz_Model_System_Config_Enabled
{
    /**
     * get option array for system config
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        $options[] = array('value' => true, 'label' => 'True ');
        $options[] = array('value' => false, 'label' => 'False ');
        return $options;
    }
}
