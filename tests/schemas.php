<?php

declare(strict_types=1);

$schemaDirectory = getenv('ITOP_SCHEMA_DIR') ?: __DIR__ . '/../.tmp/schemas';
$documents = [
    'extension manifest matches its XSD' => ['extension.xml', 'extension.xsd'],
    'Peripherals data model matches the iTop 3.3 XSD' => [
        'br-peripherals/datamodel.br-peripherals.xml', '3.3/itop_design.xsd',
    ],
    'br-costcenter-bridge-for-br-peripherals data model matches the iTop 3.3 XSD' => [
        'br-costcenter-bridge-for-br-peripherals/datamodel.br-costcenter-bridge-for-br-peripherals.xml', '3.3/itop_design.xsd',
    ],
    'TeemIP bridge structure matches the iTop 3.3 XSD with custom type support' => [
        'br-peripherals-bridge-for-teemip-ip-mgmt/datamodel.br-peripherals-bridge-for-teemip-ip-mgmt.xml', '3.3/itop_design.xsd',
    ],
];

$tests = [
    'manifest requires iTop 3.3 and declares the three modules as an array' => static function (): void {
        $manifest = simplexml_load_file(__DIR__ . '/../extension.xml', SimpleXMLElement::class, LIBXML_NONET);
        if ($manifest === false || trim((string) $manifest->itop_version_min) !== '3.3.0'
            || (string) $manifest->modules['type'] !== 'array' || count($manifest->modules->module) !== 3) {
            throw new RuntimeException('Expected iTop 3.3.0 as the minimum and a three-module array.');
        }
    },
    'workstation and bridge fields extend the existing iTop 3.3 device fieldsets' => static function (): void {
        $layouts = [
            'br-peripherals' => [
                ['PC', 1, 'ConfigMgmt:baseinfo', ['workstation_id']],
                ['Printer', 1, 'ConfigMgmt:baseinfo', ['workstation_id']],
                ['MobilePhone', 1, 'ConfigMgmt:baseinfo', ['workstation_id']],
                ['IPPhone', 1, 'ConfigMgmt:baseinfo', ['workstation_id']],
            ],
            'br-costcenter-bridge-for-br-peripherals' => [
                ['MobilePhone', 2, 'ConfigMgmt:otherinfo', ['costcenter_id']],
                ['IPPhone', 2, 'ConfigMgmt:otherinfo', ['costcenter_id']],
            ],
            'br-peripherals-bridge-for-teemip-ip-mgmt' => [
                ['MobilePhone', 1, 'Phone:moreinfo', ['macaddress', 'ipaddress_id']],
                ['IPPhone', 1, 'Phone:moreinfo', ['macaddress', 'ipaddress_id']],
            ],
        ];
        foreach ($layouts as $module => $classes) {
            $model = simplexml_load_file(__DIR__ . "/../$module/datamodel.$module.xml", SimpleXMLElement::class, LIBXML_NONET);
            if ($model === false || (string) $model['version'] !== '3.3') {
                throw new RuntimeException("Expected an iTop 3.3 data model for $module.");
            }
            foreach ($classes as [$class, $column, $fieldset, $fields]) {
                $items = "/itop_design/classes/class[@id='$class']/presentation/details/items";
                $group = "$items/item[@id='col:col$column' and @_delta='must_exist']/items"
                    . "/item[@id='fieldset:$fieldset' and @_delta='must_exist']/items";
                foreach ($fields as $field) {
                    if (count($model->xpath("$group/item[@id='$field' and @_delta='define']")) !== 1) {
                        throw new RuntimeException("Missing $module placement of $class.$field.");
                    }
                }
                if ($module === 'br-peripherals' && count($model->xpath("$items/item[@_delta='delete']")) !== 0) {
                    throw new RuntimeException("$class must keep the upstream details layout.");
                }
            }
        }
    },
];
foreach ($documents as $name => [$documentPath, $schemaPath]) {
    $tests[$name] = static function () use ($schemaDirectory, $documentPath, $schemaPath): void {
        $schema = $schemaDirectory . '/' . $schemaPath;
        if (!is_file($schema)) {
            throw new RuntimeException("Missing schema: $schema. See tests/README.md for setup.");
        }
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument();
            $loaded = $document->load(__DIR__ . '/../' . $documentPath, LIBXML_NONET);
            if ($documentPath === 'br-peripherals-bridge-for-teemip-ip-mgmt/datamodel.br-peripherals-bridge-for-teemip-ip-mgmt.xml') {
                // The core schema does not define TeemIP's custom attribute type.
                // Validate its XML structure as a string attribute; this does not
                // validate TeemIP's MAC address semantics or runtime behavior.
                $schemaDocument = new DOMDocument();
                if (!$schemaDocument->load($schema, LIBXML_NONET)) {
                    throw new RuntimeException('Cannot load the iTop schema.');
                }
                $namespace = 'http://www.w3.org/2001/XMLSchema';
                $xpath = new DOMXPath($schemaDocument);
                $xpath->registerNamespace('xs', $namespace);
                if ($xpath->query('/xs:schema/xs:complexType[@name="AttributeMacAddress"]')->length === 0) {
                    $type = $schemaDocument->createElementNS($namespace, 'xs:complexType');
                    $type->setAttribute('name', 'AttributeMacAddress');
                    $content = $schemaDocument->createElementNS($namespace, 'xs:complexContent');
                    $extension = $schemaDocument->createElementNS($namespace, 'xs:extension');
                    $extension->setAttribute('base', 'AttributeString');
                    $content->appendChild($extension);
                    $type->appendChild($content);
                    $schemaDocument->documentElement->appendChild($type);
                }
                $valid = $loaded && $document->schemaValidateSource($schemaDocument->saveXML(), LIBXML_NONET);
            } else {
                $valid = $loaded && $document->schemaValidate($schema, LIBXML_NONET);
            }
            if (!$valid) {
                $messages = array_map(
                    static fn (LibXMLError $error): string =>
                        'Line ' . $error->line . ': ' . trim($error->message),
                    libxml_get_errors()
                );
                throw new RuntimeException(implode('; ', $messages));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    };
}

return $tests;
