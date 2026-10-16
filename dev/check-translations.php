<?php
// CI: every translation file has the same keys (resname) in de and en.
//   php dev/check-translations.php
declare(strict_types=1);

$dir = __DIR__ . '/../Resources/translations';
$failed = false;

// Keys of one XLIFF file, e.g. ['menu.abrechnung', ...]
function keys(string $file): array
{
    $xml = simplexml_load_file($file);
    if ($xml === false) {
        fwrite(STDERR, "FAIL: $file is not valid XML\n");
        exit(1);
    }
    $xml->registerXPathNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
    $keys = array_map(static fn ($unit) => (string) $unit['resname'], $xml->xpath('//x:trans-unit'));
    sort($keys);

    return $keys;
}

foreach (glob("$dir/*.de.xlf") as $de) {
    $en = substr($de, 0, -strlen('.de.xlf')) . '.en.xlf';
    $name = basename($de, '.de.xlf');
    if (!is_file($en)) {
        fwrite(STDERR, "FAIL: $name has no en file\n");
        $failed = true;
        continue;
    }
    $deKeys = keys($de);
    $enKeys = keys($en);
    $missing = [
        'en' => array_diff($deKeys, $enKeys),
        'de' => array_diff($enKeys, $deKeys),
    ];
    foreach ($missing as $lang => $keys) {
        foreach ($keys as $key) {
            fwrite(STDERR, "FAIL: $name: '$key' missing in $lang\n");
        }
    }
    if ($missing['en'] !== [] || $missing['de'] !== []) {
        $failed = true;
        continue;
    }
    echo "ok $name (" . count($deKeys) . " keys)\n";
}
exit($failed ? 1 : 0);
