<?php

function loadLLMC(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException('Missing llmc.json');
    }

    $raw = file_get_contents($path);
    $cfg = json_decode($raw, true);

    if (!is_array($cfg) || !isset($cfg['groups'], $cfg['rules'])) {
        throw new RuntimeException('Invalid llmc.json');
    }

    return $cfg;
}
