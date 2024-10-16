<?php

/**
 * @copyright   Copyright (C) 2024 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2024-10-16
 *
 * iTop module definition file
 */

class DockingStation extends ConnectableCI
{
    public function GetAttributeFlags2($sAttCode, &$aReasons = array(), $sTargetState = '')
    {
        // HACK: Make workstation_id field readonly to prevent update loops from Workstation
        if (($sAttCode == 'workstation_id'))
            return (OPT_ATT_READONLY | parent::GetAttributeFlags($sAttCode, $aReasons, $sTargetState));
        return parent::GetAttributeFlags($sAttCode, $aReasons, $sTargetState);
    }
}
