<?php

declare(strict_types=1);

$schemaDirectory = getenv('ITOP_SCHEMA_DIR') ?: __DIR__ . '/../.tmp/schemas';
$documents = [
    'extension manifest matches its XSD' => ['extension.xml', 'extension.xsd'],
    'Peripherals data model matches the iTop 3.2 XSD' => [
        'br-peripherals/datamodel.br-peripherals.xml', '3.2/itop_design.xsd',
    ],
    'br-costcenter-bridge-for-br-peripherals data model matches the iTop 3.2 XSD' => [
        'br-costcenter-bridge-for-br-peripherals/datamodel.br-costcenter-bridge-for-br-peripherals.xml', '3.2/itop_design.xsd',
    ],
    'TeemIP bridge structure matches the iTop 3.2 XSD with custom type support' => [
        'br-peripherals-bridge-for-teemip-ip-mgmt/datamodel.br-peripherals-bridge-for-teemip-ip-mgmt.xml', '3.2/itop_design.xsd',
    ],
];

$tests = [];
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
