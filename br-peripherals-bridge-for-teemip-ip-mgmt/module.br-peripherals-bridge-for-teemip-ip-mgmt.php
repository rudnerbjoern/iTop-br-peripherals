<?php

/**
 * @copyright   Copyright (C) 2024 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2024-08-27
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
    __FILE__,
    'br-peripherals-bridge-for-teemip-ip-mgmt/0.7.0',
    array(
        // Identification
        //
        'label' => 'Bridge - BR-Peripherals + TeemIp IP Management',
        'category' => 'business',

        // Setup
        //
        'dependencies' => array(
            'itop-endusers-devices/2.7.0',
            'teemip-ip-mgmt/3.0.1',
            'teemip-config-mgmt-adaptor/3.0.1',
            'br-peripherals/0.7.0',
        ),
        'mandatory' => false,
        'visible' => true, // To prevent auto-install but shall not be listed in the install wizard
        'auto_select' => 'SetupInfo::ModuleIsSelected("itop-endusers-devices") && SetupInfo::ModuleIsSelected("teemip-ip-mgmt") && SetupInfo::ModuleIsSelected("teemip-config-mgmt-adaptor") && SetupInfo::ModuleIsSelected("br-peripherals")',

        // Components
        //
        'datamodel' => array(),
        'data.struct' => array(),
        'data.sample' => array(),

        // Documentation
        //
        'doc.manual_setup' => '',
        'doc.more_information' => '',

        // Default settings
        //
        'settings' => array(),
    )
);
