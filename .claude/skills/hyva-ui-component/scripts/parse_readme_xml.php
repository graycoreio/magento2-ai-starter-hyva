#!/usr/bin/env php
<?php
/**
 * Parse XML configuration block from a Hyva UI component README.
 * Extracts <var> elements with their values and inline comments.
 *
 * Usage: php parse_readme_xml.php [--format=table|md|json]
 *   Reads XML from stdin
 *
 * Example:
 *   cat readme_xml_block.xml | php parse_readme_xml.php
 *   php parse_readme_xml.php --format=md < readme_xml_block.xml
 */

$format = 'table';

// Parse arguments
for ($i = 1; $i < $argc; $i++) {
    if (strpos($argv[$i], '--format=') === 0) {
        $format = substr($argv[$i], 9);
    }
}

// Read XML from stdin
$xmlContent = file_get_contents('php://stdin');

if (empty(trim($xmlContent))) {
    fwrite(STDERR, "Error: No XML content provided on stdin\n");
    exit(1);
}

/**
 * Parse var elements line by line, tracking nesting via a path stack.
 * This approach preserves inline comment associations.
 *
 * @param string $xmlContent Raw XML content
 * @return array Array of options with name, value, comment
 */
function parseVarBlock(string $xmlContent): array
{
    $options = [];
    $pathStack = [];

    // Split into lines for easier processing
    $lines = explode("\n", $xmlContent);

    foreach ($lines as $line) {
        // Match opening <var name="..."> that starts a nested block (no closing tag on same line)
        if (preg_match('/<var\s+name="([^"]+)"[^>]*>\s*$/', $line, $match)) {
            // This is a container var - push to stack
            $pathStack[] = $match[1];
            continue;
        }

        // Match closing </var> alone on a line (ends a nested block)
        if (preg_match('/^\s*<\/var>/', $line) && !preg_match('/<var\s+name=/', $line)) {
            // Pop from stack
            array_pop($pathStack);
            continue;
        }

        // Match complete <var name="...">value</var> with optional trailing comment
        if (preg_match('/<var\s+name="([^"]+)"[^>]*>([^<]*)<\/var>\s*(?:<!--\s*(.*?)\s*-->)?/', $line, $match)) {
            $name = $match[1];
            $value = trim($match[2]);
            $comment = isset($match[3]) ? trim($match[3]) : '';

            // Build full path
            $fullPath = $pathStack;
            $fullPath[] = $name;
            $fullName = implode('.', $fullPath);

            $options[] = [
                'name' => $fullName,
                'value' => $value,
                'comment' => $comment,
            ];
        }
    }

    return $options;
}

/**
 * Format the parsed data as a text table.
 */
function formatAsTable(array $options): string
{
    if (empty($options)) {
        return "No configuration options found.\n";
    }

    // Calculate column widths
    $nameWidth = max(array_map(fn($o) => strlen($o['name']), $options));
    $nameWidth = max($nameWidth, 6);

    $valueWidth = max(array_map(fn($o) => strlen($o['value']), $options));
    $valueWidth = max($valueWidth, 5);
    $valueWidth = min($valueWidth, 15);

    $output = "";

    // Header
    $output .= sprintf(
        "%-{$nameWidth}s | %-{$valueWidth}s | %s\n",
        "Option",
        "Value",
        "Description"
    );
    $output .= str_repeat("-", $nameWidth) . "-+-"
             . str_repeat("-", $valueWidth) . "-+-"
             . str_repeat("-", 50) . "\n";

    // Rows
    foreach ($options as $option) {
        $value = strlen($option['value']) > $valueWidth
            ? substr($option['value'], 0, $valueWidth - 3) . '...'
            : $option['value'];

        $output .= sprintf(
            "%-{$nameWidth}s | %-{$valueWidth}s | %s\n",
            $option['name'],
            $value,
            $option['comment']
        );
    }

    return $output;
}

/**
 * Format the parsed data as a Markdown table.
 */
function formatAsMarkdown(array $options): string
{
    if (empty($options)) {
        return "No configuration options found.\n";
    }

    $output = "| Option | Value | Description |\n";
    $output .= "|--------|-------|-------------|\n";

    foreach ($options as $option) {
        $value = str_replace('|', '\\|', $option['value']);
        $comment = str_replace('|', '\\|', $option['comment']);

        $output .= sprintf(
            "| `%s` | `%s` | %s |\n",
            $option['name'],
            $value,
            $comment
        );
    }

    return $output;
}

/**
 * Strip common prefix from all option names if they all share one.
 */
function stripCommonPrefix(array $options): array
{
    if (count($options) < 2) {
        return $options;
    }

    // Get all names
    $names = array_column($options, 'name');

    // Find the common prefix by checking the first segment
    $firstParts = array_map(fn($n) => explode('.', $n)[0], $names);
    $commonPrefix = $firstParts[0];

    // Check if all names start with the same first segment
    foreach ($firstParts as $part) {
        if ($part !== $commonPrefix) {
            return $options; // No common prefix
        }
    }

    // Strip the common prefix from all names
    $prefixLen = strlen($commonPrefix) + 1; // +1 for the dot
    foreach ($options as &$option) {
        if (strlen($option['name']) > $prefixLen) {
            $option['name'] = substr($option['name'], $prefixLen);
        }
    }

    return $options;
}

// Parse and output
$parsed = parseVarBlock($xmlContent);
$parsed = stripCommonPrefix($parsed);

switch ($format) {
    case 'json':
        echo json_encode($parsed, JSON_PRETTY_PRINT) . "\n";
        break;
    case 'md':
    case 'markdown':
        echo formatAsMarkdown($parsed);
        break;
    case 'table':
    default:
        echo formatAsTable($parsed);
        break;
}