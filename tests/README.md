# PHP tests

Run from the repository root with PHP 8.0 or later, SimpleXML, and DOM enabled.
First download the two standalone schemas used by the project's XML files:

```sh
mkdir -p .tmp/schemas/3.2
curl --fail --location https://rudnerbjoern.github.io/iTop-schema/extension.xsd -o .tmp/schemas/extension.xsd
curl --fail --location https://rudnerbjoern.github.io/iTop-schema/3.2/itop_design.xsd -o .tmp/schemas/3.2/itop_design.xsd
```

PowerShell alternative:

```powershell
New-Item -ItemType Directory -Force .tmp/schemas/3.2 | Out-Null
Invoke-WebRequest https://rudnerbjoern.github.io/iTop-schema/extension.xsd -OutFile .tmp/schemas/extension.xsd
Invoke-WebRequest https://rudnerbjoern.github.io/iTop-schema/3.2/itop_design.xsd -OutFile .tmp/schemas/3.2/itop_design.xsd
```

Alternatively, set `ITOP_SCHEMA_DIR` to an existing iTop-schema checkout containing
`extension.xsd` and `3.2/itop_design.xsd`. Then run:

```sh
php tests/run.php
```

On Windows, use the same command with `php.exe` if needed.
No Composer packages, database, or iTop installation are required.
The runner prints each result and exits with `0` on success or `1` on failure.

For every PR targeting `main`, CI sets up PHP 8.3 with DOM and SimpleXML,
downloads both schemas into `.tmp/schemas`, and runs the same command.
This includes PRs from forks: PHP tests use read permissions and no repository
secrets. GitHub may require maintainer approval for a fork's workflow run,
depending on repository settings. The workflow uses `pull_request` to test the
PR merge revision. Normal pushes do not execute PHP tests.
The required `PR checks` job also requires this test job to succeed; a schema
download failure fails the job. Push-only runs continue to run Gitleaks only.
For `release/*` PRs, CI also validates the `release/X.Y.Z` branch name against
the manifest and module versions, and includes that result in `PR checks`.

The release preparation tests cover all three module registrations, metadata-only
updates, internal bridge dependency updates, preserved external minimum versions,
malformed versions, version mismatches, and invalid dates. The XSD tests
validate the manifest and all three module data models against local schemas.
Missing schemas fail with setup instructions. Tests run offline after setup;
refresh the cached schemas explicitly when needed.

The module tests load the real `PeripheralsUtils` and `_Workstation` classes
with minimal in-memory iTop substitutes. They cover boolean settings, device
slots against the XML model, assignment and replacement, optional location and
cost center propagation, contact operations, and deletion cleanup.

Contact operations are recorded, not executed against a database. These tests
do not verify contact-link SQL, iTop event dispatch, attribute flags, persistence,
or installation. No dependencies are added.

The core iTop XSD does not declare TeemIP's `AttributeMacAddress` type. For the
TeemIP bridge only, the validator adds that type as a string attribute to an
in-memory copy of the schema when absent. This validates the XML structure,
not MAC address semantics or compatibility with an installed TeemIP version.
The cached schema and the project's data model remain unchanged.
