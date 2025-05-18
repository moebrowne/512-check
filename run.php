<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$siteYamlPath = $argv[1];

if (file_exists($siteYamlPath) === false) {
    echo 'ERROR: Unable to load ' . $siteYamlPath;
    exit(1);
}

$sites = Yaml::parseFile($siteYamlPath, Yaml::PARSE_DATETIME);

$sitesTotal = count($sites);
$sitesSmaller = 0;
$sitesLarger = 0;
$sitesUnchanged = 0;
$sitesTooBig = 0;
$sitesDead = 0;
$sitesError = 0;

foreach ($sites as $i => &$site) {
    if ($site['last_checked']->getTimestamp() > time() - 86400) {
        continue;
    }

    echo $site['url'] . ' ';

    exec('docker run -i --init --cap-add=SYS_ADMIN --rm ghcr.io/puppeteer/puppeteer:latest node -e "$(cat ' . __DIR__ . '/puppeteer.js)" ' . escapeshellarg($site['url']) . ' 2>&1', $output, $exitCode);

    $outputLines = count($output);
    $output = implode(PHP_EOL, $output);

    if ($outputLines !== 1) {
        if (str_contains($output, 'net::ERR_NAME_NOT_RESOLVED')) {
            echo 'DEAD' . PHP_EOL;
            $sitesDead++;
            unset($sites[$i]);
            writeYaml($sites);

            continue;
        }

        if (str_contains($output, 'Navigation timeout') || str_contains($output, 'Timed out after') || str_contains($output, 'Runtime.callFunctionOn timed out.')) {
            echo 'TIMEOUT' . PHP_EOL;
            $sitesError++;
            continue;
        }

        if (str_contains($output, 'net::ERR_CERT_')) {
            echo 'CERT ERROR' . PHP_EOL;
            $sitesError++;
            continue;
        }

        echo 'INTERNAL ERROR' . PHP_EOL;
        $sitesError++;

//        echo str_repeat('-', 120) . PHP_EOL .  $output . PHP_EOL . str_repeat('-', 120) . PHP_EOL;

        continue;
    }

    $size = (int)$output;

    if ($exitCode !== 0 || $output == 'NaN' || $size <= 0) {
        echo 'ERROR' . PHP_EOL;
        $sitesError++;
        continue;
    }

    $sizeFormatted = number_format($size / 1000, 2);

    echo $site['size'] . ' => ' . $sizeFormatted . PHP_EOL;

    if ($site['size'] < $size / 1000) {
        $sitesLarger++;
    } else if ($site['size'] > $size / 1000) {
        $sitesSmaller++;
    } else {
        $sitesUnchanged++;
    }

    if ($size > 512 * 1000) {
        $sitesTooBig++;
        unset($sites[$i]);
        writeYaml($sites);

        continue;
    }

    $site['last_checked'] = new DateTimeImmutable()->format('Y-m-d');
    $site['size'] = $sizeFormatted;

    writeYaml($sites);
}

echo 'Done!' . PHP_EOL . PHP_EOL;

echo ' - Total sites: ' . $sitesTotal . PHP_EOL;
echo ' - Larger: ' . $sitesLarger . PHP_EOL;
echo ' - Smaller: ' . $sitesSmaller . PHP_EOL;
echo ' - Unchanged: ' . $sitesUnchanged . PHP_EOL;
echo ' - >512: ' . $sitesTooBig . PHP_EOL;
echo ' - Dead: ' . $sitesDead . PHP_EOL;
echo ' - Error: ' . $sitesError . PHP_EOL;



function writeYaml($sites): void {
    global $siteYamlPath;
    $sites = array_values($sites);

    $yamlText = Yaml::dump($sites, indent: 2);

    // match formatting
    $yamlText = str_replace(["-\n  ", "'", 'T00:00:00+00:00'], ["\n- ", '', ''], $yamlText);

    file_put_contents($siteYamlPath, ltrim($yamlText));
}



