# iTop-br-peripherals

Copyright (c) 2024 Björn Rudner
[![License](https://img.shields.io/github/license/rudnerbjoern/iTop-br-peripherals)](https://github.com/rudnerbjoern/iTop-br-peripherals/blob/main/LICENSE)

## What?

Adds monitor, docking station, keyboard, mouse, headset, scanner, barcode scanner, conference system

### End user devices

![End user devices](Screenshots/EndUserDevices.png)

### Workstation in use

![Workstation](Screenshots/Workstation.png)

### Relations

![Workstation Depends on ...](Screenshots/WorkstationDependsOn.png)

## Dependencies

* iTop End-User Devices (itop-endusers-devices) needs to be enabled during setup

## Installation

Place the extension in the `extensions` folder of your iTop instance and run iTop setup again.
Be sure to enable the extension during setup.

## Configuration

After installation, it is possible to change some settings in the iTop configuration.

### update_contacts_from_workstation

Specify if the user of a Workstation should be added to the Contacts of the assigned peripheral devices.

* `true`
* `false` (default)

### update_locations_from_workstation

Specify if the location of peripheral devices assigned to a Workstation should be set to the same location as the workstation.

* `true`
* `false` (default)

## Classes

### Workstation

![Workstation Icon](br-peripherals/images/workstation.png)

| Name                 | Type                                                | Mandatory? |
| -------------------- | --------------------------------------------------- | ---------- |
| Name                 | Alphanumeric string                                 | Yes        |
| Organization         | Foreign key to a(n) Organization                    | Yes        |
| Status               | Foreign key to a(n) Organization                    | No         |
| Business criticality | Possible values: Low, Medium, High                  | No         |
| Location             | Foreign key to a Location                           | No         |
| User                 | Foreign key to a Person/Contact                     | No         |
| PC                   | Foreign key to a [PC](#pc)                          | Yes        |
| Docking Station      | Foreign key to a [Docking Station](#dockingstation) | No         |
| Monitor A            | Foreign key to a [Monitor](#monitor)                | No         |
| Monitor B            | Foreign key to a [Monitor](#monitor)                | No         |
| Keyboard             | Foreign key to a [Keyboard](#keyboard)              | No         |
| Mouse                | Foreign key to a [Mouse](#mouse)                    | No         |
| Headset              | Foreign key to a [Headset](#headset)                | No         |
| Mobile Phone         | Foreign key to a [Mobile Phone](#mobilephone)       | No         |
| Telephone A          | Foreign key to a Telephony CI                       | No         |
| Telephone B          | Foreign key to a Telephony CI                       | No         |
| Printer A            | Foreign key to a [Printer](#printer)                | No         |
| Printer B            | Foreign key to a [Printer](#printer)                | No         |
| Scanner              | Foreign key to a [Scanner](#scanner)                | No         |
| Barcode Scanner      | Foreign key to a [Barcode Scanner](#barcodescanner) | No         |
| Description          | Multiline character string                          | No         |

#### Automation

If enabled by the setting `update_locations_from_workstation` the assigned `Location`
will also set the specific location on the following linked items:
`PC`, `Docking Station`,  `Monitor A`, `Monitor B`, `Keyboard`, `Mouse`,
`Headset`, `Mobile Phone`, `Telephone A`, `Telephone B`, `Printer A`, `Printer B`,
`Scanner`, `Barcode Scanner`

If enabled by the setting `update_contacts_from_workstation` the assigned `User`
will also add to the Contacts of the `Workstation` itself and on the following linked items:
`PC`, `Docking Station`,  `Monitor A`, `Monitor B`, `Keyboard`, `Mouse`,
`Headset`, `Mobile Phone`, `Telephone A`, `Telephone B`, `Printer A`, `Printer B`,
`Scanner`, `Barcode Scanner`

If the User is removed or changed it will also get removed or changed on the linked items.

### PC

### DockingStation

![Docking Station Icon](br-peripherals/images/usb-hub.png)

### Monitor

![Monitor Icon](br-peripherals/images/monitor.png)

### Keyboard

![Keyboard Icon](br-peripherals/images/keyboard.png)

### Mouse

![Mouse Icon](br-peripherals/images/mouse.png)

### Headset

![Headset Icon](br-peripherals/images/headset.png)

### Printer

### Scanner

![Scanner Icon](br-peripherals/images/scanner.png)

### BarcodeScanner

![Barcode Scanner Icon](br-peripherals/images/barcode-scanner.png)

### MobilePhone

### IPPhone

### ConferenceSystem

![Conference System Icon](br-peripherals/images/conference-system.png)

## iTop Compatibility

The branch [2.7.10](https://github.com/rudnerbjoern/iTop-br-peripherals/tree/itop/2.7) is compatible to iTop 2.7 and iTop 3.1.

The branch [main](https://github.com/rudnerbjoern/iTop-br-peripherals/tree/main) will only be compatible to iTop 3.1 and above.

The extension was tested on iTop 2.7.10, 3.1.1 and 3.2.0-2.

## Attribution

This Extension uses Icons from:

![Workstation Icon](br-peripherals/images/workstation.png) [Workstation Icon](https://www.flaticon.com/free-icons/office) Office icons created by surang - Flaticon

![Docking Station Icon](br-peripherals/images/usb-hub.png) [Docking Station Icon](https://www.flaticon.com/free-icons/usb-hub) Usb-hub icons created by Nikita Golubev - Flaticon

![Monitor Icon](br-peripherals/images/monitor.png) [Monitor Icon](https://www.flaticon.com/free-icons/monitor) Monitor icons created by xnimrodx - Flaticon

![Headset Icon](br-peripherals/images/headset.png) [Headset Icon](https://www.flaticon.com/free-icons/headset) Headset icons created by Freepik - Flaticon

![Mouse Icon](br-peripherals/images/mouse.png) [Mouse Icon](https://www.flaticon.com/free-icons/computer)Computer icons created by surang - Flaticon

![Keyboard Icon](br-peripherals/images/keyboard.png) [Keyboard Icon](https://www.flaticon.com/free-icons/electric-keyboard) Electric keyboard icons created by Iconic Panda - Flaticon

![Scanner Icon](br-peripherals/images/scanner.png) [Scanner Icon](https://www.flaticon.com/free-icons/scanner) Scanner icons created by Vectors Tank - Flaticon

![Barcode Scanner Icon](br-peripherals/images/barcode-scanner.png) [Barcode Scanner Icon](https://www.flaticon.com/free-icons/barcode-scanner) Barcode scanner icons created by Freepik - Flaticon

![Conference System Icon](br-peripherals/images/conference-system.png) [Conference System Icon](https://www.flaticon.com/free-icons/conference) Conference icons created by Freepik - Flaticon
