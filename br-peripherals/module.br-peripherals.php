<?php

/**
 * @copyright   Copyright (C) 2024 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2024-10-18
 *
 * iTop module definition file
 */

SetupWebPage::AddModule(
    __FILE__, // Path to the current file, all other file names are relative to the directory containing this file
    'br-peripherals/3.1.10',
    array(
        // Identification
        //
        'label' => 'Datamodel: Peripheral devices for PCs',
        'category' => 'business',

        // Setup
        //
        'dependencies' => array(
            'itop-config-mgmt/3.1.0',
            'itop-endusers-devices/3.1.0',
        ),
        'mandatory' => false,
        'visible' => true,

        // Components
        //
        'datamodel' => array(),
        'webservice' => array(),
        'data.struct' => array(),
        'data.sample' => array(),

        // Documentation
        //
        'doc.manual_setup' => '',
        'doc.more_information' => '',

        // Default settings
        //
        'settings' => array(
            'update_contacts_from_workstation' => 'false', // update Contact of linked peripherals to the User of the workstation
            'update_locations_from_workstation' => 'false', // update location of linked peripherals to the location of the workstation
            'update_costcenters_from_workstation' => 'false', // update CostCenter of linked peripherals to the CostCenter of the workstation
        ),
    )
);
