<?php

/**
 * @copyright   Copyright (C) 2024-2025 Björn Rudner
 * @license     https://www.gnu.org/licenses/gpl-3.0.en.html
 * @version     2025-06-12
 *
 * Localized data
 */

/** @disregard P1009 Undefined type Dict */
Dict::Add('RU RU', 'Russian', 'Русский', array(

    'Class:Workstation' => 'Рабочее место',
    'Class:Workstation+' => 'Рабочее место',
    'Class:Workstation/Attribute:user_id' => 'Пользователь',
    'Class:Workstation/Attribute:pc_id' => 'ПК',
    'Class:Workstation/Attribute:pcB_id' => 'Дополнительный ПК',
    'Class:Workstation/Attribute:monitorA_id' => 'Монитор A',
    'Class:Workstation/Attribute:monitorB_id' => 'Монитор B',
    'Class:Workstation/Attribute:dockingstation_id' => 'Docking station',
    'Class:Workstation/Attribute:keyboard_id' => 'Клавиатура',
    'Class:Workstation/Attribute:mouse_id' => 'Мышь',
    'Class:Workstation/Attribute:headset_id' => 'Гарнитура',
    'Class:Workstation/Attribute:mobilephone_id' => 'Мобильный телефон',
    'Class:Workstation/Attribute:telephonyA_id' => 'Телефон A',
    'Class:Workstation/Attribute:telephonyB_id' => 'Телефон B',
    'Class:Workstation/Attribute:printerA_id' => 'Принтер A',
    'Class:Workstation/Attribute:printerB_id' => 'Принтер B',
    'Class:Workstation/Attribute:scanner_id' => 'Сканер',
    'Class:Workstation/Attribute:barcodescanner_id' => 'Сканер штрихкодов',

    'Workstation:desktop' => 'Настольный компьютер',
    'Workstation:telephony' => 'Телефон',
    'Workstation:printerscanner' => 'Принтер и сканер',

    'Class:DockingStation' => 'Док-станция',
    'Class:DockingStation+' => 'Док-станция или USB-хаб',
    'Class:Monitor' => 'Монитор',
    'Class:Monitor+' => 'Монитор',
    'Class:Keyboard' => 'Клавиатура',
    'Class:Keyboard+' => 'Клавиатура',
    'Class:Mouse' => 'Мышь',
    'Class:Mouse+' => 'Мышь',
    'Class:Headset' => 'Гарнитура',
    'Class:Headset+' => 'Гарнитура',
    'Class:Scanner' => 'Сканер',
    'Class:Scanner+' => 'Сканер документов',
    'Class:BarcodeScanner' => 'Сканер штрихкодов',
    'Class:BarcodeScanner+' => 'Сканер штрихкодов',
    'Class:ConferenceSystem' => 'Система конференций',
    'Class:ConferenceSystem+' => 'Система конференций',

    'Class:PC/Attribute:workstation_id' => 'Рабочее место',
    'Class:Monitor/Attribute:workstation_id' => 'Рабочее место',
    'Class:DockingStation/Attribute:workstation_id' => 'Рабочее место',
    'Class:Keyboard/Attribute:workstation_id' => 'Рабочее место',
    'Class:Mouse/Attribute:workstation_id' => 'Рабочее место',
    'Class:Headset/Attribute:workstation_id' => 'Рабочее место',
    'Class:Peripheral/Attribute:workstation_id' => 'Рабочее место',
    'Class:TelephonyCI/Attribute:workstation_id' => 'Рабочее место',

    // Class:Model
    'Class:Model/Attribute:type/Value:DockingStation' => 'Док-станция',
    'Class:Model/Attribute:type/Value:DockingStation+' => 'Док-станция или USB-хаб',
    'Class:Model/Attribute:type/Value:Monitor' => 'Монитор',
    'Class:Model/Attribute:type/Value:Monitor+' => 'Монитор',
    'Class:Model/Attribute:type/Value:Keyboard' => 'Клавиатура',
    'Class:Model/Attribute:type/Value:Keyboard+' => 'Клавиатура',
    'Class:Model/Attribute:type/Value:Mouse' => 'Мышь',
    'Class:Model/Attribute:type/Value:Mouse+' => 'Мышь',
    'Class:Model/Attribute:type/Value:Headset' => 'Гарнитура',
    'Class:Model/Attribute:type/Value:Headset+' => 'Гарнитура',
    'Class:Model/Attribute:type/Value:BarcodeScanner' => 'Сканер',
    'Class:Model/Attribute:type/Value:BarcodeScanner+' => 'Сканер',
    'Class:Model/Attribute:type/Value:BarcodeScanner' => 'Сканер штрихкодов',
    'Class:Model/Attribute:type/Value:BarcodeScanner+' => 'Сканер штрихкодов',
    'Class:Model/Attribute:type/Value:ConferenceSystem' => 'Система конференции',
    'Class:Model/Attribute:type/Value:ConferenceSystem+' => 'Система конференции',

    // Menu
    'Menu:EndUserSpace' => 'Рабочие места',
    'Menu:EndUserSpace:Workstation' => 'Рабочие места и ПК',
    'Menu:EndUserSpace:Phone' => 'Телефон',
    'Menu:EndUserSpace:Printer' => 'Принтеры',
    'Menu:EndUserSpace:Scanner' => 'Сканеры',
));
