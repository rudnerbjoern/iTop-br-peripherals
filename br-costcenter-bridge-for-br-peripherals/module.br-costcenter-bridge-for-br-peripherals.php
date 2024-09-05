<?php

/**
 * @copyright   Copyright (C) 2024 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2024-09-05
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
    __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
    'br-costcenter-bridge-for-br-peripherals/3.1.8',
    array(
        // Identification
        'label' => 'Bridge - Costcenter + Periperals',
        'category' => 'business',

        // Setup
        'dependencies' => array(
            'br-peripherals/3.1.8||br-costcenter/0.4.0',
            'br-peripherals/3.1.8',
        ),
        'mandatory' => false,
        'visible' => true, // To prevent auto-install but shall not be listed in the install wizard
        'auto_select' => 'SetupInfo::ModuleIsSelected("br-costcenter") && SetupInfo::ModuleIsSelected("br-peripherals")',

        // Components
        'datamodel' => array(),
        'webservice' => array(),
        'dictionary' => array(),
        'data.struct' => array(),
        'data.sample' => array(),

        // Documentation
        'doc.manual_setup' => '',
        'doc.more_information' => '',

        // Default settings
        'settings' => array(),
    )
);
