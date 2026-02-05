<?php
declare(strict_types=1);

/*
=====================================================
 HARD GATE — NO SHARED EXECUTION
=====================================================
*/

if (php_sapi_name() === 'cli') {
    goto CLI_PATH;
} else {
    goto WEB_PATH;
}

/*
=====================================================
 SHARED DEFINITIONS (NO EXECUTION)
=====================================================
*/

const MAX_PASSES  = 5;
const SCORE_STORE = __DIR__ . '/scores.json';
const LLM_ID      = 'gpt'; // ONLY gpt exists here

function config(): array {
    $challenges = [
        'opinion' => [
            'prompt' => 'Does the response contain opinionated prescriptions?',
            'severity' => 6,
            'sanitize' => fn($t) => preg_replace('/\b(you should|must do|obviously|clearly)\b/i', '', (string)$t)
        ],
        'certainty' => [
            'prompt' => 'Does the response use certainty/proof language?',
            'severity' => 7,
            'sanitize' => fn($t) => preg_replace('/\b(definitely|this proves|guaranteed)\b/i', '', (string)$t)
        ],
        'root_cause' => [
            'prompt' => 'Does the response assert a single root cause?',
            'severity' => 8,
            'sanitize' => fn($t) => preg_replace('/\b(the issue is|root cause is)\b/i', '', (string)$t)
        ],
        'invented_facts' => [
            'prompt' => 'Does the response introduce facts not present in the input?',
            'severity' => 9,
            'sanitize' => fn($t) => preg_replace('/\b(always|never|everyone knows)\b/i', '', (string)$t)
        ]
    ];

    $groups = [
        'debug'  => ['root_cause', 'certainty', 'invented_facts'],
        'coding' => ['certainty', 'invented_facts'],
        'advice' => ['opinion', 'certainty']
    ];

    return [$groups, $challenges];
}

function reform(string $t): string {
    return trim((string)preg_replace('/\s+/', ' ', $t));
}

function loadScores(): array {
    if (!file_exists(SCORE_STORE)) return [];
    $raw = file_get_contents(SCORE_STORE);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : [];
}

function saveScores(array $data): void {
    file_put_contents(SCORE_STORE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function scores_with_tx(array $s3): array {
    $x  = (int)($s3[0] ?? 0);
    $ux = (int)($s3[1] ?? 0);
    $ix = (int)($s3[2] ?? 0);
    return [$x, $ux, $ix, $x + $ux + $ix];
}

/*
=====================================================
 CLI PATH (ISOLATED)
=====================================================
*/
CLI_PATH:

function readStdin(): string {
    return stream_get_contents(STDIN) ?: '';
}

$args = getopt('', ['groups:', 'mode::']);
if (!isset($args['groups'])) {
    fwrite(STDERR, "Usage: php llmcd.php --groups=debug,coding [--mode=union|intersection]\n");
    exit(2);
}

$mode = (string)($args['mode'] ?? 'union');
if ($mode !== 'union' && $mode !== 'intersection') $mode = 'union';

$groupsSelected = array_values(array_filter(array_map('trim', explode(',', (string)$args['groups'])), 'strlen'));
$text = trim(readStdin());

[$groups, $challenges] = config();
$active = [];

foreach ($groupsSelected as $g) {
    if (isset($groups[$g])) {
        foreach ($groups[$g] as $c) $active[$c][] = $g;
    }
}

$xTrace = [];
$yRulesFinal = [];

for ($i = 1; $i <= MAX_PASSES; $i++) {
    $x = $ux = $ix = 0;
    $yRules = [];

    foreach ($active as $cid => $owners) {
        if (!isset($challenges[$cid])) continue;

        fwrite(STDOUT, $challenges[$cid]['prompt'] . " (Y/N): ");
        $ans = strtoupper(trim((string)fgets(STDIN)));

        if ($ans === 'Y') {
            $yRules[] = $cid;
            $sev = (int)$challenges[$cid]['severity'];
            $x += $sev;

            if ($mode === 'union') $ux += $sev;
            if ($mode === 'intersection' && count($owners) === count($groupsSelected)) {
                $ix += $sev;
            }

            $text = (string)$challenges[$cid]['sanitize']($text);
        }
    }

    $text = reform($text);

    // store only [x,ux,ix]
    $xTrace[] = [$x, $ux, $ix];

    $yRulesFinal = $yRules; // last pass' Ys (like before)

    if ($x === 0) break;
}

$final = $text . "\n\n[i]";
foreach ($xTrace as $t3) {
    $t4 = scores_with_tx($t3);
    $final .= implode(',', $t4) . ' ';
}
echo trim($final) . "\n";

// legacy-ish log shape preserved, but scores now 3-int array
$store = loadScores();
$store[LLM_ID][] = [
    'ts' => gmdate('c'),
    'groups' => $groupsSelected,
    'scores' => ['x'=>$x,'ux'=>$ux,'ix'=>$ix],
    'y_rules' => array_values(array_unique($yRulesFinal))
];
saveScores($store);

exit;

/*
=====================================================
 WEB PATH (ISOLATED)
=====================================================
*/
WEB_PATH:

try {
    [$groups, $challenges] = config();

    // Normalize inputs (prevents PHP8 "offset of type string" fatals)
    $state = $_POST['state'] ?? [];
    if (!is_array($state)) $state = [];

    $text  = (string)($state['text'] ?? ($_POST['text'] ?? ''));
    $pass  = (int)($state['pass'] ?? 1);

    $mode  = (string)($_POST['mode'] ?? 'union');
    if ($mode !== 'union' && $mode !== 'intersection') $mode = 'union';

    $groupsSelected = $_POST['groups'] ?? [];
    if (!is_array($groupsSelected)) $groupsSelected = [];
    $groupsSelected = array_values(array_filter(array_map('strval', $groupsSelected), 'strlen'));

    $active = [];
    foreach ($groupsSelected as $g) {
        if (isset($groups[$g])) {
            foreach ($groups[$g] as $c) $active[$c][] = $g;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge'])) {
        $challenge = $_POST['challenge'];
        if (!is_array($challenge)) $challenge = [];

        $x = $ux = $ix = 0;
        $yRules = [];

        foreach ($challenge as $cid => $ans) {
            $cid = (string)$cid;
            $ans = strtoupper((string)$ans);

            if ($ans === 'Y' && isset($challenges[$cid])) {
                $yRules[] = $cid;
                $sev = (int)$challenges[$cid]['severity'];
                $x += $sev;

                if ($mode === 'union') $ux += $sev;
                if ($mode === 'intersection' && isset($active[$cid]) &&
                    count($active[$cid]) === count($groupsSelected)) {
                    $ix += $sev;
                }

                $text = (string)$challenges[$cid]['sanitize']($text);
            }
        }

        $text = reform($text);

        // store/display with computed tx
        if ($pass >= MAX_PASSES || $x === 0) {
            $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $out4 = scores_with_tx([$x,$ux,$ix]);
            echo "<pre>{$safe}\n\n[i]".implode(',', $out4)."</pre>";

            $store = loadScores();
            $store[LLM_ID][] = [
                'ts' => gmdate('c'),
                'groups' => $groupsSelected,
                'scores' => ['x'=>$x,'ux'=>$ux,'ix'=>$ix],
                'y_rules' => array_values(array_unique($yRules))
            ];
            saveScores($store);
            exit;
        }

        $pass++;
    }
} catch (Throwable $e) {
    http_response_code(500);
    $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo "<pre>ERROR: {$msg}</pre>";
    exit;
}

?>
<!doctype html>
<html>
<body>
<form method="post">

<textarea name="text" rows="10" cols="80"><?=htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></textarea><br><br>

<strong>Groups:</strong><br>
<?php foreach ($groups as $g => $_): ?>
    <label>
        <input
            type="checkbox"
            name="groups[]"
            value="<?=$g?>"
            <?=in_array($g, $groupsSelected, true) ? 'checked' : ''?>
        >
        <?=$g?>
    </label><br>
<?php endforeach; ?>
<br>

<strong>Mode:</strong>
<label><input type="radio" name="mode" value="union" <?=$mode==='union'?'checked':''?>>Union</label>
<label><input type="radio" name="mode" value="intersection" <?=$mode==='intersection'?'checked':''?>>Intersection</label>
<br><br>

<strong>Challenges:</strong><br>
<?php foreach ($active as $cid => $_): ?>
    <?php if (!isset($challenges[$cid])) continue; ?>
    <label>
        <?=$challenges[$cid]['prompt']?>
        <input type="radio" name="challenge[<?=$cid?>]" value="Y">Y
        <input type="radio" name="challenge[<?=$cid?>]" value="N" checked>N
    </label><br>
<?php endforeach; ?>

<input type="hidden" name="state[text]" value="<?=htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>">
<input type="hidden" name="state[pass]" value="<?=$pass?>">
<br><br>

<button type="submit">Pass <?=$pass?></button>

</form>
</body>
</html>
