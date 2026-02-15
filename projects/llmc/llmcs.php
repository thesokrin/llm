<?php

// declare(strict_types=1);

// if (PHP_SAPI === 'cli') {
    // exit(cli_main($argv));
// }
// web_main();
// exit(0);

// const MAX_PASSES  = 5;
// const SCORE_STORE = __DIR__ . '/scores.json';
// const LLM_ID      = 'gpt';

//<?php
declare(strict_types=1);

const MAX_PASSES  = 5;
const SCORE_STORE = __DIR__ . '/scores.json';
const LLM_ID      = 'gpt';

if (PHP_SAPI === 'cli') {
    exit(cli_main($argv));
}
web_main();
exit(0);


function now_iso(): string { return gmdate('Y-m-d\TH:i:s\Z'); }

function scores_with_tx(array $s3): array {
    $x  = (int)($s3[0] ?? 0);
    $ux = (int)($s3[1] ?? 0);
    $ix = (int)($s3[2] ?? 0);
    return [$x,$ux,$ix,$x+$ux+$ix];
}

function fresh_store(): array {
    $meta_schema = [
        "v" => 1,
        "defs" => [
            "ScalarType" => ["int","str","bool","num"],
            "Node" => [
                "t"     => "ScalarType | array | obj | map | ref",
                "desc"  => "str (optional)",
                "order" => "array<str> (only if t=array)",
                "items" => "Node (only if t=array)",
                "props" => "map<str,Node> (only if t=obj)",
                "key"   => "Node (only if t=map)",
                "val"   => "Node (only if t=map)",
                "ref"   => "str (only if t=ref)"
            ]
        ]
    ];

    $schema = [
        "v" => 1,
        "attempt" => [
            "t" => "obj",
            "props" => [
                "s" => [
                    "t" => "array",
                    "desc" => "attempt score breakdown",
                    "order" => ["x","ux","ix"], // <-- 3-int
                    "items" => ["t" => "int"]
                ],
                "r" => [
                    "t" => "array",
                    "desc" => "violated rule ids (a1,a2,...)",
                    "items" => ["t" => "str"]
                ]
            ]
        ]
    ];

    return [
        "meta_schema" => $meta_schema,
        "schema" => $schema,
        "group_ids" => [],
        "group_names" => [],
        "rule_ids" => [],
        "rule_names" => [],
        "models" => []
    ];
}

function load_store(): array {
    if (!is_file(SCORE_STORE)) {
        $s = fresh_store();
        seed_defaults($s);
        save_store($s);
        return $s;
    }
    $raw = file_get_contents(SCORE_STORE);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $s = fresh_store();
        seed_defaults($s);
        save_store($s);
        return $s;
    }
    $data = ensure_store_shape($data);
    seed_defaults($data);
    return $data;
}

function save_store(array $s): void {
    $json = json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) throw new RuntimeException("json_encode failed");
    file_put_contents(SCORE_STORE, $json . "\n");
}

function ensure_store_shape(array $s): array {
    $fresh = fresh_store();
    foreach (array_keys($fresh) as $k) if (!array_key_exists($k, $s)) $s[$k] = $fresh[$k];
    foreach (['group_ids','group_names','rule_ids','rule_names','models'] as $k) if (!is_array($s[$k])) $s[$k] = [];
    if (!is_array($s['schema'] ?? null)) $s['schema'] = $fresh['schema'];
    if (!is_array($s['meta_schema'] ?? null)) $s['meta_schema'] = $fresh['meta_schema'];
    return $s;
}

function next_group_letter(array $group_ids): string {
    for ($c = ord('a'); $c <= ord('z'); $c++) {
        $id = chr($c);
        if (!isset($group_ids[$id])) return $id;
    }
    throw new RuntimeException("out of group letters");
}

function next_rule_id_for_group(array $rule_ids, string $g): string {
    $max = 0;
    foreach ($rule_ids as $rid => $_info) {
        if (!is_string($rid) || $rid === '') continue;
        if ($rid[0] !== $g) continue;
        $n = (int)substr($rid, 1);
        if ($n > $max) $max = $n;
    }
    return $g . (string)($max + 1);
}

function add_group(array &$s, string $name, ?string $gid=null): string {
    $name = trim($name);
    if ($name === '') throw new InvalidArgumentException("group name empty");
    if (isset($s['group_names'][$name])) return (string)$s['group_names'][$name];

    if ($gid === null || $gid === '') $gid = next_group_letter($s['group_ids']);
    $gid = strtolower(trim($gid));
    if (!preg_match('/^[a-z]$/', $gid)) throw new InvalidArgumentException("group id must be single letter a-z");
    if (isset($s['group_ids'][$gid])) throw new InvalidArgumentException("group id exists: $gid");

    $s['group_ids'][$gid] = ["name"=>$name,"rules"=>[]];
    $s['group_names'][$name] = $gid;
    return $gid;
}

function add_rule(array &$s, string $rule_name, string $group_letter): string {
    $rule_name = trim($rule_name);
    if ($rule_name === '') throw new InvalidArgumentException("rule name empty");
    if (isset($s['rule_names'][$rule_name])) return (string)$s['rule_names'][$rule_name];

    $g = strtolower(trim($group_letter));
    if (!preg_match('/^[a-z]$/', $g)) throw new InvalidArgumentException("group letter must be a-z");
    if (!isset($s['group_ids'][$g])) throw new InvalidArgumentException("unknown group letter: $g");

    $rid = next_rule_id_for_group($s['rule_ids'], $g);
    $s['rule_ids'][$rid] = ["name"=>$rule_name,"g"=>$g];
    $s['rule_names'][$rule_name] = $rid;

    $s['group_ids'][$g]['rules'][] = $rid;
    $s['group_ids'][$g]['rules'] = array_values(array_unique($s['group_ids'][$g]['rules']));
    natsort($s['group_ids'][$g]['rules']);
    $s['group_ids'][$g]['rules'] = array_values($s['group_ids'][$g]['rules']);

    return $rid;
}

function seed_defaults(array &$s): void {
    if (!isset($s['group_ids']['a'])) add_group($s, 'core', 'a');
    foreach (['opinion','certainty','root_cause','invented_facts'] as $rn) {
        if (!isset($s['rule_names'][$rn])) add_rule($s, $rn, 'a');
    }
}

function challenges_config(): array {
    return [
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
}

function sets_config(array $s): array {
    $rid = fn(string $name): string => (string)$s['rule_names'][$name];
    return [
        'debug'  => [$rid('root_cause'), $rid('certainty'), $rid('invented_facts')],
        'coding' => [$rid('certainty'),  $rid('invented_facts')],
        'advice' => [$rid('opinion'),    $rid('certainty')],
    ];
}

function reform(string $t): string {
    return trim((string)preg_replace('/\s+/', ' ', $t));
}

function build_active_rules(array $s, array $selected_sets): array {
    $sets = sets_config($s);
    $counts = [];
    foreach ($selected_sets as $set) {
        if (!isset($sets[$set])) continue;
        foreach ($sets[$set] as $rid) $counts[$rid] = ($counts[$rid] ?? 0) + 1;
    }
    return $counts;
}

function rule_prompt_and_sev(array $s, string $rid, array $challenges): array {
    $name = $s['rule_ids'][$rid]['name'] ?? null;
    if (!is_string($name) || !isset($challenges[$name])) throw new RuntimeException("unknown rule id: $rid");
    return [$challenges[$name]['prompt'], (int)$challenges[$name]['severity'], $challenges[$name]['sanitize']];
}

function run_attempt(array $s, string $text, array $active_counts, array $selected_sets, string $mode, array $answers, array $challenges): array {
    $x = $ux = $ix = 0;
    $y_rules = [];
    $selected_count = count($selected_sets);

    foreach ($active_counts as $rid => $owners_count) {
        $ans = strtoupper((string)($answers[$rid] ?? 'N'));
        if ($ans !== 'Y') continue;

        [, $sev, $sanitize] = rule_prompt_and_sev($s, (string)$rid, $challenges);

        $y_rules[] = (string)$rid;
        $x += $sev;
        if ($mode === 'union') $ux += $sev;
        if ($mode === 'intersection' && $owners_count === $selected_count) $ix += $sev;

        $text = (string)$sanitize($text);
    }

    $text = reform($text);

    $y_rules = array_values(array_unique($y_rules));
    natsort($y_rules);
    $y_rules = array_values($y_rules);

    // <-- return 3-int only
    return [$text, $y_rules, [$x,$ux,$ix]];
}

function ensure_model(array &$s, string $model): void {
    if (!isset($s['models'][$model])) {
        $s['models'][$model] = ["totals"=>[0,0,0],"trule"=>[],"sessions"=>[]];
    }
}
function ensure_session(array &$s, string $model, string $session_ts): void {
    ensure_model($s, $model);
    if (!isset($s['models'][$model]['sessions'][$session_ts])) {
        $s['models'][$model]['sessions'][$session_ts] = ["sc"=>[0,0,0],"turns"=>[]];
    }
}
function ensure_turn(array &$s, string $model, string $session_ts, string $turn_ts, array $sets, string $mode): void {
    ensure_session($s, $model, $session_ts);
    if (!isset($s['models'][$model]['sessions'][$session_ts]['turns'][$turn_ts])) {
        $s['models'][$model]['sessions'][$session_ts]['turns'][$turn_ts] = [
            "sets"=>array_values($sets),
            "mode"=>$mode,
            "attempts"=>[]
        ];
    }
}
function compute_trule(array $model_obj): array {
    $counts = [];
    foreach (($model_obj['sessions'] ?? []) as $sess) {
        foreach (($sess['turns'] ?? []) as $turn) {
            foreach (($turn['attempts'] ?? []) as $attempt) {
                foreach (($attempt['r'] ?? []) as $rid) {
                    if (!is_string($rid)) continue;
                    $counts[$rid] = ($counts[$rid] ?? 0) + 1;
                }
            }
        }
    }
    if (!$counts) return [];
    $max = max($counts);
    $w = [];
    foreach ($counts as $rid => $c) if ($c === $max) $w[] = $rid;
    natsort($w);
    return array_values($w);
}

function record_attempt(array &$s, string $model, string $session_ts, string $turn_ts, int $attempt_idx, array $scores3, array $y_rules): void {
    $attempt_idx = max(1, min(MAX_PASSES, $attempt_idx));
    $scores3 = array_slice(array_map('intval', $scores3), 0, 3);

    $s['models'][$model]['sessions'][$session_ts]['turns'][$turn_ts]['attempts'][(string)$attempt_idx] = [
        "s" => $scores3, // <-- [x,ux,ix]
        "r" => array_values($y_rules),
    ];

    $x  = (int)$scores3[0];
    $ux = (int)$scores3[1];
    $ix = (int)$scores3[2];

    $s['models'][$model]['totals'][0] += $x;
    $s['models'][$model]['totals'][1] += $ux;
    $s['models'][$model]['totals'][2] += $ix;

    $s['models'][$model]['sessions'][$session_ts]['sc'][0] += $x;
    $s['models'][$model]['sessions'][$session_ts]['sc'][1] += $ux;
    $s['models'][$model]['sessions'][$session_ts]['sc'][2] += $ix;

    $s['models'][$model]['trule'] = compute_trule($s['models'][$model]);
}

/* ---------------- CLI ---------------- */

// --- llmc.json helpers (CLI batch questions / answer-string scoring) ---

function llmc_json_path(): string {
    return __DIR__ . '/llmc.json';
}

function llmc_json_load(): array {
    $path = llmc_json_path();
    if (!is_file($path)) throw new RuntimeException("Missing llmc.json at $path");
    $raw = file_get_contents($path);
    $cfg = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($cfg) || !isset($cfg['groups'], $cfg['rules'])) throw new RuntimeException('Invalid llmc.json');
    if (!is_array($cfg['groups']) || !is_array($cfg['rules'])) throw new RuntimeException('Invalid llmc.json');
    return $cfg;
}

/**
 * Returns ordered unique rule ids for selected sets.
 * Order is preserved as they appear in each group array.
 */
function llmc_json_rule_order(array $cfg, array $sets): array {
    $out = [];
    $seen = [];
    foreach ($sets as $set) {
        $set = (string)$set;
        $list = $cfg['groups'][$set] ?? null;
        if (!is_array($list)) continue;
        foreach ($list as $rid) {
            if (!is_string($rid) || $rid === '') continue;
            if (isset($seen[$rid])) continue;
            $seen[$rid] = true;
            $out[] = $rid;
        }
    }
    return $out;
}

/** Returns [rid => prompt] in the order of llmc_json_rule_order(). */
function llmc_json_prompts_for_sets(array $cfg, array $sets): array {
    $rids = llmc_json_rule_order($cfg, $sets);
    $out = [];
    foreach ($rids as $rid) {
        $r = $cfg['rules'][$rid] ?? null;
        $prompt = (is_array($r) && isset($r['prompt']) && is_string($r['prompt'])) ? $r['prompt'] : ("Rule {$rid}");
        $out[$rid] = $prompt;
    }
    return $out;
}

/**
 * Parse answers like "YYN.." into [rid => 'Y'|'N'] aligned to provided rid order.
 * Non-YN characters are ignored. Missing answers default to 'N'.
 */
function llmc_json_answers(array $rid_order, string $answers): array {
    $answers = strtoupper(trim($answers));
    $answers = preg_replace('/[^YN]/', '', $answers) ?? '';
    $out = [];
    $n = count($rid_order);
    for ($i = 0; $i < $n; $i++) {
        $rid = (string)$rid_order[$i];
        $ch  = $answers[$i] ?? 'N';
        $out[$rid] = ($ch === 'Y') ? 'Y' : 'N';
    }
    return $out;
}

/**
 * Compute [x,ux,ix] from llmc.json given sets + answers.
 * - x: sum severity for Y rules
 * - ux: same as x (union mode bookkeeping; kept for shape)
 * - ix: sum severity for Y rules that belong to ALL selected sets
 */
function llmc_json_score(array $cfg, array $sets, array $answers): array {
    $sets = array_values(array_filter(array_map('strval', $sets), 'strlen'));
    $setCount = count($sets);

    // owner counts per rule (in how many selected sets does this rule appear?)
    $owners = [];
    foreach ($sets as $set) {
        $list = $cfg['groups'][(string)$set] ?? null;
        if (!is_array($list)) continue;
        foreach ($list as $rid) {
            if (!is_string($rid) || $rid === '') continue;
            $owners[$rid] = ($owners[$rid] ?? 0) + 1;
        }
    }

    $x = 0; $ux = 0; $ix = 0;
    foreach ($answers as $rid => $yn) {
        if ($yn !== 'Y') continue;
        $r = $cfg['rules'][$rid] ?? null;
        $sev = (is_array($r) && isset($r['severity'])) ? (int)$r['severity'] : 0;
        $x  += $sev;
        $ux += $sev;
        if ($setCount > 0 && (($owners[$rid] ?? 0) == $setCount)) $ix += $sev;
    }

    return [$x,$ux,$ix];
}




/**
 * Parse CLI args into key/value options.
 * Supports: --key=value and --key value
 * Skips argv[0] (script) and argv[1] (cmd).
 */
function cli_kv_opts(array $argv): array {
    $out = [];
    $n = count($argv);
    for ($i = 2; $i < $n; $i++) {
        $a = (string)$argv[$i];
        if (!str_starts_with($a, '--')) continue;
        $a = substr($a, 2);
        if ($a === '') continue;
        if (strpos($a, '=') !== false) {
            [$k, $v] = explode('=', $a, 2);
            $out[(string)$k] = (string)$v;
            continue;
        }
        $k = $a;
        $v = '';
        if ($i + 1 < $n && !str_starts_with((string)$argv[$i+1], '--')) {
            $v = (string)$argv[$i+1];
            $i++;
        }
        $out[(string)$k] = (string)$v;
    }
    return $out;
}
function cli_main(array $argv): int {
    $cmd = $argv[1] ?? 'run';

    try {
        $s = load_store();
        $challenges = challenges_config();

        if ($cmd === 'help') {
            fwrite(STDOUT, "Usage: echo text | php llmcs.php run --sets=debug,coding [--mode=union|intersection]\n");
            return 0;
        }

        if ($cmd === 'schema') {
            fwrite(STDOUT, json_encode(["meta_schema"=>$s['meta_schema'], "schema"=>$s['schema']], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
            return 0;
        }

        if ($cmd === 'store') {
            fwrite(STDOUT, json_encode($s, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
            return 0;
        }

        if ($cmd === 'questions') {
            $kv = cli_kv_opts($argv);
            if (!isset($kv['sets']) || trim((string)$kv['sets']) === '') {
                fwrite(STDERR, "Usage: php llmcs.php questions --sets=debug,coding\n");
                return 2;
            }
            $sets = array_values(array_filter(array_map('trim', explode(',', (string)$kv['sets'])), 'strlen'));
            $cfg = llmc_json_load();
            $prompts = llmc_json_prompts_for_sets($cfg, $sets);
            $i = 1;
            fwrite(STDOUT, "NOTE: Penalty score (golf). Answer Y only for violations. Lower is better (0=perfect).\n\n");
            foreach ($prompts as $rid => $prompt) {
                fwrite(STDOUT, sprintf("%02d %s\t%s\n", $i++, $rid, $prompt));
            }
            fwrite(STDOUT, "COUNT=".(string)count($prompts)."\n");
            return 0;
        }

        if ($cmd === 'score') {
            $kv = cli_kv_opts($argv);
            if (!isset($kv['sets']) || trim((string)$kv['sets']) === '' || !isset($kv['answers'])) {
                fwrite(STDERR, "Usage: echo text | php llmcs.php score --sets=debug,coding [--mode=union|intersection] --answers=YYN...\n");
                return 2;
            }

            $mode = (string)($kv['mode'] ?? 'union');
            if ($mode !== 'union' && $mode !== 'intersection') $mode = 'union';

            $sets = array_values(array_filter(array_map('trim', explode(',', (string)$kv['sets'])), 'strlen'));
            $text = trim(stream_get_contents(STDIN) ?: '');

            $cfg = llmc_json_load();
            $rid_order = llmc_json_rule_order($cfg, $sets);
            $answers = llmc_json_answers($rid_order, (string)$kv['answers']);

            [$x,$ux,$ix] = llmc_json_score($cfg, $sets, $answers);

            $y_rules = [];
            foreach ($rid_order as $rid) {
                if (($answers[(string)$rid] ?? 'N') === 'Y') $y_rules[] = (string)$rid;
            }

            $total = $x + $ux + $ix;

            $report = (string)($kv['report'] ?? 'llm');
            if ($report !== 'llm' && $report !== 'audit') $report = 'llm';
            $include_prompts = isset($kv['include-prompts']) || isset($kv['include_prompts']);

            if ($report === 'llm') {
                $ok = ($x === 0);
                echo ($ok ? "PASS" : "FAIL") . "\n";
                echo "y_rules:";
                if ($y_rules) echo " " . implode(',', $y_rules);
                echo "\n";

                if ($include_prompts && $y_rules) {
                    // Print prompts for triggered rules to guide self-correction without showing numeric weights.
                    foreach ($y_rules as $rid) {
                        $r = $cfg['rules'][(string)$rid] ?? null;
                        $prompt = (is_array($r) && isset($r['prompt']) && is_string($r['prompt'])) ? $r['prompt'] : ("Rule {$rid}");
                        echo "- {$rid}: {$prompt}\n";
                    }
                }

                return 0;
            }

            // audit report (numbers)
            echo $text . "\n\n[i]" . $x . ',' . $ux . ',' . $ix . ',' . $total . "\n";
            echo "[y_rules]" . ($y_rules ? (' ' . implode(',', $y_rules)) : '') . "\n";

            return 0;
        }
$opts = getopt('', ['sets:', 'mode::']);
        if (!isset($opts['sets'])) {
            fwrite(STDERR, "Usage: echo text | php llmcs.php run --sets=debug,coding [--mode=union|intersection]\n");
            return 2;
        }

        $mode = (string)($opts['mode'] ?? 'union');
        if ($mode !== 'union' && $mode !== 'intersection') $mode = 'union';

        $selected_sets = array_values(array_filter(array_map('trim', explode(',', (string)$opts['sets'])), 'strlen'));
        $text = trim(stream_get_contents(STDIN) ?: '');

        $session_ts = now_iso();
        $turn_ts = $session_ts;

        $active_counts = build_active_rules($s, $selected_sets);
        ensure_turn($s, LLM_ID, $session_ts, $turn_ts, $selected_sets, $mode);

        $last3 = [0,0,0];

        for ($attempt = 1; $attempt <= MAX_PASSES; $attempt++) {
            $answers = [];
            foreach ($active_counts as $rid => $_owners) {
                [$prompt] = rule_prompt_and_sev($s, (string)$rid, $challenges);
                fwrite(STDOUT, $prompt . " (Y/N): ");
                $ans = strtoupper(trim((string)fgets(STDIN)));
                $answers[(string)$rid] = ($ans === 'Y') ? 'Y' : 'N';
            }

            [$text, $y_rules, $scores3] = run_attempt($s, $text, $active_counts, $selected_sets, $mode, $answers, $challenges);
            record_attempt($s, LLM_ID, $session_ts, $turn_ts, $attempt, $scores3, $y_rules);

            $last3 = $scores3;
            if ($scores3[0] === 0) break;
        }

        save_store($s);

        $last4 = scores_with_tx($last3);
        echo $text . "\n\n[i]" . implode(',', $last4) . "\n";

        return 0;

    } catch (Throwable $e) {
        fwrite(STDERR, "ERROR: ".$e->getMessage()."\n");
        return 1;
    }
}

/* ---------------- WEB ---------------- */

function web_main(): void {
    try {
        $s = load_store();
        $challenges = challenges_config();
        $sets = sets_config($s);

        // normalize state (fixes 500)
        $state = $_POST['state'] ?? [];
        if (!is_array($state)) $state = [];

        $text = (string)($state['text'] ?? ($_POST['text'] ?? ''));
        $attempt = (int)($state['attempt'] ?? 1);

        $mode = (string)($_POST['mode'] ?? ($state['mode'] ?? 'union'));
        if ($mode !== 'union' && $mode !== 'intersection') $mode = 'union';

        $selected_sets = $_POST['sets'] ?? ($state['sets'] ?? []);
        if (!is_array($selected_sets)) $selected_sets = [];

        $session_ts = (string)($state['session_ts'] ?? '');
        $turn_ts    = (string)($state['turn_ts'] ?? '');
        if ($session_ts === '' || $turn_ts === '') {
            $session_ts = now_iso();
            $turn_ts = $session_ts;
        }

        $active_counts = build_active_rules($s, $selected_sets);

        $done = false;
        $last3 = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge'])) {
            $challenge = $_POST['challenge'];
            if (!is_array($challenge)) $challenge = [];

            $answers = [];
            foreach ($challenge as $rid => $ans) {
                $answers[(string)$rid] = (strtoupper((string)$ans) === 'Y') ? 'Y' : 'N';
            }

            ensure_turn($s, LLM_ID, $session_ts, $turn_ts, $selected_sets, $mode);

            [$text, $y_rules, $scores3] = run_attempt($s, $text, $active_counts, $selected_sets, $mode, $answers, $challenges);
            record_attempt($s, LLM_ID, $session_ts, $turn_ts, $attempt, $scores3, $y_rules);
            save_store($s);

            $last3 = $scores3;

            if ($attempt >= MAX_PASSES || $scores3[0] === 0) $done = true;
            else $attempt++;
        }

        if ($done) {
            $safe = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $last4 = scores_with_tx($last3 ?? [0,0,0]);
            echo "<pre>{$safe}\n\n[i]".implode(',', $last4)."</pre>";
            return;
        }

        $safe_text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ?>
<!doctype html>
<html>
<body>
<form method="post">
<textarea name="text" rows="10" cols="80"><?= $safe_text ?></textarea><br><br>

<strong>Sets:</strong><br>
<?php foreach ($sets as $set_name => $_): ?>
  <label>
    <input type="checkbox" name="sets[]" value="<?=htmlspecialchars($set_name)?>"
      <?=in_array($set_name, $selected_sets, true) ? 'checked' : ''?>>
    <?=htmlspecialchars($set_name)?>
  </label><br>
<?php endforeach; ?>
<br>

<strong>Mode:</strong>
<label><input type="radio" name="mode" value="union" <?=$mode==='union'?'checked':''?>>Union</label>
<label><input type="radio" name="mode" value="intersection" <?=$mode==='intersection'?'checked':''?>>Intersection</label>
<br><br>

<strong>Challenges:</strong><br>
<?php foreach ($active_counts as $rid => $_owners): ?>
  <?php
    $rname = $s['rule_ids'][(string)$rid]['name'] ?? '';
    $prompt = isset($challenges[$rname]) ? $challenges[$rname]['prompt'] : ("Rule ".$rid);
  ?>
  <label>
    <?=htmlspecialchars($prompt)?> <small>(<?=htmlspecialchars((string)$rid)?>)</small>
    <input type="radio" name="challenge[<?=htmlspecialchars((string)$rid)?>]" value="Y">Y
    <input type="radio" name="challenge[<?=htmlspecialchars((string)$rid)?>]" value="N" checked>N
  </label><br>
<?php endforeach; ?>

<input type="hidden" name="state[text]" value="<?=htmlspecialchars($text, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>">
<input type="hidden" name="state[attempt]" value="<?= (int)$attempt ?>">
<input type="hidden" name="state[mode]" value="<?=htmlspecialchars($mode, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>">
<?php foreach ($selected_sets as $sv): ?>
  <input type="hidden" name="state[sets][]" value="<?=htmlspecialchars((string)$sv, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>">
<?php endforeach; ?>
<input type="hidden" name="state[session_ts]" value="<?=htmlspecialchars($session_ts, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>">
<input type="hidden" name="state[turn_ts]" value="<?=htmlspecialchars($turn_ts, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>">

<br><br>
<button type="submit">Attempt <?= (int)$attempt ?></button>
</form>
</body>
</html>
<?php
    } catch (Throwable $e) {
        http_response_code(500);
        $msg = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo "<pre>ERROR: {$msg}</pre>";
    }
}
