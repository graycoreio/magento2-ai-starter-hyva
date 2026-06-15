#!/usr/bin/env php
<?php
/**
 * Update Component Schema Reference
 *
 * Reads the Hyvä CMS JSON schema files and generates the component-schema.md reference file.
 * Run this script when Hyvä CMS is updated to ensure the skill documentation stays current.
 *
 * Usage: php scripts/update_component_schema.php
 */

declare(strict_types=1);

// Find project root (look for vendor directory)
$dir = __DIR__;
while ($dir !== '/' && !is_dir($dir . '/vendor')) {
    $dir = dirname($dir);
}

if ($dir === '/') {
    fwrite(STDERR, "Error: Could not find project root (no vendor directory found)\n");
    exit(1);
}

$projectRoot = $dir;
$schemaDir = $projectRoot . '/vendor/hyva-themes/commerce-module-cms/src/liveview-editor/etc/hyva_cms/jsonschema';

if (!is_dir($schemaDir)) {
    fwrite(STDERR, "Error: Schema directory not found: $schemaDir\n");
    fwrite(STDERR, "Make sure hyva-themes/commerce-module-cms is installed.\n");
    exit(1);
}

$componentDeclarationSchema = $schemaDir . '/component-declaration.json';
$fieldDeclarationSchema = $schemaDir . '/component-field-declaration.json';

if (!file_exists($componentDeclarationSchema)) {
    fwrite(STDERR, "Error: Component declaration schema not found: $componentDeclarationSchema\n");
    exit(1);
}

if (!file_exists($fieldDeclarationSchema)) {
    fwrite(STDERR, "Error: Field declaration schema not found: $fieldDeclarationSchema\n");
    exit(1);
}

$componentSchema = json_decode(file_get_contents($componentDeclarationSchema), true);
$fieldSchema = json_decode(file_get_contents($fieldDeclarationSchema), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    fwrite(STDERR, "Error: Failed to parse JSON schema\n");
    exit(1);
}

// Extract component properties
$componentProps = $componentSchema['additionalProperties']['properties'] ?? [];

// Extract field properties
$fieldProps = $fieldSchema['additionalProperties']['properties'] ?? [];

// Extract field types
$fieldTypes = $fieldSchema['additionalProperties']['properties']['type']['enum'] ?? [];

// Extract validation attributes
$validationAttrs = $fieldSchema['additionalProperties']['properties']['attributes']['properties'] ?? [];

// Build markdown content
$md = <<<'HEADER'
# Hyvä CMS Component Schema Reference

> **Auto-generated file** - Run `scripts/update_component_schema.php` to regenerate from the Hyvä CMS JSON schema.
>
> Schema source: `vendor/hyva-themes/commerce-module-cms/src/liveview-editor/etc/hyva_cms/jsonschema/`

## Component Declaration Properties

**IMPORTANT:** Only these properties are allowed at the component level. Using any other property will cause a schema validation error.

| Property | Type | Required | Default | Description |
|----------|------|----------|---------|-------------|

HEADER;

// Add component properties
foreach ($componentProps as $name => $prop) {
    $type = is_array($prop['type'] ?? null) ? implode('/', $prop['type']) : ($prop['type'] ?? 'any');
    $required = $name === 'label' ? 'Yes*' : 'No';
    $default = isset($prop['default']) ? (is_bool($prop['default']) ? ($prop['default'] ? '`true`' : '`false`') : "`\"{$prop['default']}\"`") : '-';
    $comment = $prop['$comment'] ?? '';

    // Special handling for some properties
    if ($name === 'template') {
        $default = 'Auto';
        $comment = 'Template path in format `Vendor_Module::path/to/template.phtml`, or `false` for child-only components. If not set, defaults to `[Vendor]_[Module]::elements/[component-name].phtml`';
    } elseif ($name === 'icon') {
        $default = 'Default';
        $comment = 'Icon path in format `Vendor_Module::path/to/icon.svg`. Supports: jpg, jpeg, png, gif, webp, svg, avif. Defaults to `Hyva_CmsLiveviewEditor::images/components/default.svg`';
    } elseif ($name === 'children') {
        $comment = 'Set to `true` to enable children, or an object with `config` for specific configuration.';
    } elseif ($name === 'content') {
        $comment = 'Field definitions for the Content tab.';
    } elseif ($name === 'design') {
        $comment = 'Field definitions for the Design tab.';
    } elseif ($name === 'advanced') {
        $comment = 'Field definitions for the Advanced tab.';
    } elseif ($name === 'custom_properties') {
        $comment = 'Array for extending the component schema by developers.';
    }

    $md .= "| `$name` | $type | $required | $default | $comment |\n";
}

$md .= <<<'SECTION'

*Required unless `disabled: true` is set.

### Component Name Rules

Component names (the JSON object keys) must:
- Be alphanumeric with dashes and underscores only
- Be at least 3 characters long
- Match pattern: `^[a-zA-Z0-9-_]+$`

## Field Declaration Properties

Fields within `content`, `design`, and `advanced` sections support these properties:

| Property | Type | Required | Description |
|----------|------|----------|-------------|

SECTION;

// Add field properties
$requiredFields = $fieldSchema['additionalProperties']['required'] ?? [];
foreach ($fieldProps as $name => $prop) {
    $type = is_array($prop['type'] ?? null) ? implode('/', $prop['type']) : ($prop['type'] ?? 'any');
    $required = in_array($name, $requiredFields) ? 'Yes' : 'No';
    $comment = $prop['$comment'] ?? '';

    // Simplify some descriptions
    if ($name === 'type') {
        $comment = 'The field type (see Field Types below)';
    } elseif ($name === 'label') {
        $comment = 'The label for the field in the editor form';
    } elseif ($name === 'default_value') {
        $comment = 'The initial value when a new component is created';
    } elseif ($name === 'options') {
        $comment = 'For select fields: array of {value, label} or source model class';
    } elseif ($name === 'config') {
        $comment = 'Additional configuration (accepts, excludes, max_children for children)';
    } elseif ($name === 'custom_type') {
        $comment = 'Custom type identifier when `type` is `custom_type`';
    } elseif ($name === 'custom_properties') {
        $comment = 'Array for extending the field schema';
    }

    $md .= "| `$name` | $type | $required | $comment |\n";
}

$md .= <<<'SECTION'

### Special Property: includes

The `includes` property in `content`, `design`, or `advanced` sections allows inheriting fields from another file:

```json
{
    "design": {
        "includes": "Hyva_CmsBase::etc/hyva_cms/default_design.json"
    }
}
```

Or multiple files:

```json
{
    "design": {
        "includes": [
            "Hyva_CmsBase::etc/hyva_cms/default_design.json",
            "Hyva_CmsBase::etc/hyva_cms/default_design_typography.json"
        ]
    }
}
```

## Field Types

| Type | Description |
|------|-------------|

SECTION;

// Add field types
$typeDescriptions = [
    'boolean' => 'Toggle switch for true/false values',
    'color' => 'Color picker input',
    'date' => 'Date picker',
    'datetime' => 'Date and time picker',
    'html' => 'Raw HTML code input',
    'image' => 'Image upload field',
    'link' => 'URL/link configuration with target options',
    'multiselect' => 'Multiple selection dropdown',
    'number' => 'Numeric input',
    'range' => 'Slider input for numeric values',
    'products' => 'Product selector',
    'richtext' => 'WYSIWYG rich text editor',
    'select' => 'Dropdown selection',
    'searchable_select' => 'Searchable dropdown for large option lists',
    'text' => 'Single-line text input',
    'text-align' => 'Text alignment selector',
    'textarea' => 'Multi-line text input',
    'variant' => 'Template variant selector',
    'widget' => 'Magento widget selector',
    'custom_type' => 'Custom field type (requires `custom_type` property)',
];

foreach ($fieldTypes as $type) {
    $desc = $typeDescriptions[$type] ?? '';
    $md .= "| `$type` | $desc |\n";
}

$md .= <<<'SECTION'

## Validation Attributes

Common attributes for field validation:

| Attribute | Description |
|-----------|-------------|

SECTION;

// Add validation attributes (non-data- prefixed ones)
$attrDescriptions = [
    'required' => 'Field must have a value (boolean)',
    'minlength' => 'Minimum text length',
    'maxlength' => 'Maximum text length',
    'min' => 'Minimum numeric value',
    'max' => 'Maximum numeric value',
    'step' => 'Numeric step increment',
    'pattern' => 'Regex pattern for validation',
    'placeholder' => 'Placeholder text',
    'comment' => 'Help text displayed below the field',
];

foreach ($validationAttrs as $attr => $prop) {
    if (strpos($attr, 'data-') !== 0) {
        $desc = $attrDescriptions[$attr] ?? ($prop['$comment'] ?? '');
        $md .= "| `$attr` | $desc |\n";
    }
}

$md .= <<<'SECTION'

### Custom Validation Messages

| Attribute | Description |
|-----------|-------------|

SECTION;

// Add data- prefixed attributes
$dataAttrDescriptions = [
    'data-required-msg' => 'Custom message for required validation',
    'data-min-msg' => 'Custom message for minimum validation',
    'data-max-msg' => 'Custom message for maximum validation',
    'data-pattern-msg' => 'Custom message for pattern validation',
];

foreach ($validationAttrs as $attr => $prop) {
    if (strpos($attr, 'data-') === 0 && strpos($attr, '-msg') !== false) {
        $desc = $dataAttrDescriptions[$attr] ?? ($prop['$comment'] ?? '');
        $md .= "| `$attr` | $desc |\n";
    }
}

$md .= <<<'SECTION'

## Invalid Properties

The following properties do **NOT** exist and will cause schema errors:

- `hidden` - Use `require_parent: true` for child-only components, or `disabled: true` to hide from editor
- Any property not listed in the Component Declaration Properties table above

SECTION;

// Write the output file
$outputFile = dirname(__DIR__) . '/references/component-schema.md';
if (file_put_contents($outputFile, $md) === false) {
    fwrite(STDERR, "Error: Failed to write output file: $outputFile\n");
    exit(1);
}

echo "Successfully updated: $outputFile\n";
echo "Schema version based on: hyva-themes/commerce-module-cms\n";