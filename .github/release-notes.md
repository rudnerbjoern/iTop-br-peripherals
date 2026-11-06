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
