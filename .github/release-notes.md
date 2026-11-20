## iTop compatibility

The 3.3.x series requires iTop 3.3.0 or later. For iTop 3.2, use a 3.2.x
release or the [iTop 3.2 maintenance branch](https://github.com/rudnerbjoern/iTop-br-peripherals/tree/itop/3.2).

## Changes in 3.3.0

- Workstation links now appear in the native iTop 3.3 layouts for PCs,
  printers, mobile phones, and IP phones. The existing device fields and
  workstation synchronization are preserved.
- The optional cost center bridge requires **br-costcenter 3.3.0** or later.
  The optional TeemIP bridge supports **TeemIP Core 3.3.1** with the
  Configuration Management and End-User Devices adapters.

## 📦 Installation

1. Download the `br-peripherals-vX.Y.Z.zip` asset attached to this release.
2. Extract its contents into your iTop installation's `extensions/` directory,
   preserving the included folder structure. The package creates a single
   `extensions/br-peripherals/` folder containing the manifest and all three modules.
3. Run iTop setup and select **Datamodel: Peripheral devices for PCs** to activate the extension.

### Verify your download

Download the matching `.zip.sha256` file alongside the ZIP. From the folder
containing both files, verify the package before extracting it:

```sh
sha256sum --check br-peripherals-vX.Y.Z.zip.sha256
```

On Windows, use `Get-FileHash .\br-peripherals-vX.Y.Z.zip -Algorithm SHA256`
and compare the result with the hash in the `.sha256` file.

The cost center and TeemIP bridges are selected when their companion modules
are selected during setup.
