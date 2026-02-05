<?php
// Force UTF-8 everywhere
header('Content-Type: text/html; charset=UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

require_once 'db.php';

// Force UTF-8 on the database connection
$dbconn->exec("SET NAMES utf8mb4");
$dbconn->exec("SET CHARACTER SET utf8mb4");

// Function to fix double-encoded UTF-8
function fix_utf8($text) {
    if (!$text) return $text;
    
    // Try to detect and fix double-encoding using iconv
    // This converts from UTF-8 to UTF-8, which fixes double-encoding
    $fixed = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
    if ($fixed === false) {
        $fixed = $text;
    }
    
    // Manual fixes for common double-encoding patterns
    $fixed = str_replace('ÃÂ®', 'Â®', $fixed);
    $fixed = str_replace('Ã¢Â¢', 'â¢', $fixed);
    $fixed = str_replace('Ã', '', $fixed);
    
    return $fixed;
}

try {
    // Load price data from database (Gemini's clean approach)
    $pricesStmt = $dbconn->query("SELECT * FROM prices");
    $prices = [];
    foreach ($pricesStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $prices[$p['upc']] = $p;
    }
    
    // Fetch data for both graph and table
    $stmt = $dbconn->query("SELECT * FROM food ORDER BY date ASC");
    $allRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Data structures for grouping
    $groups = [];
    $tableData = [];

    // Pre-defined color palette
    $palette = [
        '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', 
        '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'
    ];
    $colorIndex = 0;

    // Track first and latest weight for inventory
    $firstWeightByUPC = [];
    $latestByUPC = [];

    foreach ($allRows as $row) {
        $upc = $row['upc'];
        $weight = (float)$row['weight'];
        $json = json_decode($row['data'], true);
        $productName = fix_utf8($json['product']['product_name'] ?? ($json['product']['brands'] ?? "UPC: $upc"));
        
        // Initialize group if not exists
        if (!isset($groups[$upc])) {
            $groups[$upc] = [
                'label' => $productName,
                'upc' => $upc,
                'color' => $palette[$colorIndex % count($palette)],
                'points' => []
            ];
            $colorIndex++;
        }

        // Add to graph data points (ignore 0 weights)
        if ($weight > 0) {
            // $groups[$upc]['points'][] = [
                // 'x' => date('M j, H:i', strtotime($row['date'])),
                // 'y' => $weight
            // ];
            $groups[$upc]['points'][] = [
                'x' => strtotime($row['date']) * 1000, // milliseconds
                'y' => $weight
            ];
                        
            // Track first weight (baseline/original amount)
            if (!isset($firstWeightByUPC[$upc])) {
                $firstWeightByUPC[$upc] = $weight;
            }
            
            // Track latest weight
            $latestByUPC[$upc] = [
                'weight' => $weight,
                'first_weight' => $firstWeightByUPC[$upc],
                'name' => $productName
            ];
        }

        // Add to table data
        $priceData = $prices[$upc] ?? null;
        $pricePerUnit = $priceData ? $priceData['price'] : null;
        
        $tableData[] = [
            'id' => $row['id'],
            'upc' => $upc,
            'date' => $row['date'],
            'weight' => $weight,
            'name' => $productName,
            'brand' => fix_utf8($json['product']['brands'] ?? 'Generic'),
            'img' => $json['product']['image_front_small_url'] ?? '',
            'nutriments' => $json['product']['nutriments'] ?? null,
            'color' => $groups[$upc]['color'],
            'nutriscore' => $json['product']['nutriscore_grade'] ?? null,
            'nova_group' => $json['product']['nova_group'] ?? null,
            'ecoscore' => $json['product']['ecoscore_grade'] ?? null,
            'allergens' => $json['product']['allergens_tags'] ?? [],
            'additives_n' => $json['product']['additives_n'] ?? 0,
            'categories' => $json['product']['categories'] ?? '',
            'ingredients_text' => fix_utf8($json['product']['ingredients_text'] ?? ''),
            'vegan' => in_array('en:vegan', $json['product']['ingredients_analysis_tags'] ?? []),
            'vegetarian' => in_array('en:vegetarian', $json['product']['ingredients_analysis_tags'] ?? []) || 
                           in_array('en:maybe-vegetarian', $json['product']['ingredients_analysis_tags'] ?? []),
            // Price data
            'price_per_unit' => $pricePerUnit,
            'price_unit' => $priceData ? $priceData['unit'] : null,
            'price_store' => $priceData ? $priceData['store'] : null,
            'price_last_updated' => $priceData ? $priceData['last_updated'] : null,
            'price_on_sale' => $priceData && $priceData['promo_price'] ? true : false,
        ];
    }

    // Process deltas (Weight loss between scans)
    $lastWeightsByUPC = [];
    foreach ($tableData as $index => &$item) {
        $upc = $item['upc'];
        $item['delta'] = 0;
        $item['first_weight'] = $firstWeightByUPC[$upc] ?? $item['weight'];
        
        if ($item['weight'] > 0) {
            if (isset($lastWeightsByUPC[$upc])) {
                $item['delta'] = $lastWeightsByUPC[$upc] - $item['weight'];
            }
            $lastWeightsByUPC[$upc] = $item['weight'];
        }
    }

    // Prepare table display (Newest first)
    $displayRows = array_reverse($tableData);

} catch (Exception $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Digestive Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

    <style>
        .sortable { cursor: pointer; user-select: none; }
        .sortable:hover { background-color: #f1f5f9; }
        .sort-icon { display: inline-block; margin-left: 4px; opacity: 0.3; }
        .sortable.active .sort-icon { opacity: 1; }
        .row-highlighted { background-color: #fef3c7 !important; }
        tr.clickable { cursor: pointer; transition: all 0.2s; }
        tr.clickable:hover { background-color: #f8fafc; }
        .pulse-sale { animation: pulse-glow 2s ease-in-out infinite; }
        @keyframes pulse-glow {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-slate-50 p-4 md:p-8 text-slate-900">

    <div class="max-w-6xl mx-auto">
        <header class="mb-6">
            <h1 class="text-3xl font-bold"> Digestive Dashboard</h1>
            <p class="text-slate-500">Track consumption, costs, and nutritional trends.</p>
            
            <?php
            // Calculate pricing coverage
            $uniqueUPCs = array_unique(array_column($allRows, 'upc'));
            $withPrices = array_filter($uniqueUPCs, fn($upc) => isset($prices[$upc]));
            $priceCoverage = count($uniqueUPCs) > 0 ? (count($withPrices) / count($uniqueUPCs)) * 100 : 0;
            $lastPriceUpdate = !empty($prices) ? max(array_column($prices, 'last_updated')) : null;
            ?>
            
            <!-- Price Status Banner -->
            <div class="mt-4 p-4 rounded-xl border-2 <?= $priceCoverage >= 100 ? 'bg-green-50 border-green-200' : ($priceCoverage > 0 ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200') ?>">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="flex items-center gap-4">
                        <div class="text-3xl"><?= $priceCoverage >= 100 ? 'OK' : ($priceCoverage > 0 ? '!' : 'X') ?></div>
                        <div>
                            <div class="font-bold text-slate-800">
                                Price Coverage: <?= round($priceCoverage) ?>%
                            </div>
                            <div class="text-sm text-slate-600">
                                <?= count($withPrices) ?> of <?= count($uniqueUPCs) ?> products have pricing data
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($lastPriceUpdate): ?>
                    <div class="text-right">
                        <div class="text-xs text-slate-500">Last Updated</div>
                        <div class="font-mono text-sm font-bold text-slate-700"><?= $lastPriceUpdate ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($priceCoverage < 100 && count($uniqueUPCs) > count($withPrices)): ?>
                    <div class="text-xs text-yellow-800 bg-yellow-100 px-3 py-2 rounded-lg font-medium">
                        $ <?= count($uniqueUPCs) - count($withPrices) ?> product<?= count($uniqueUPCs) - count($withPrices) != 1 ? 's' : '' ?> missing prices
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Budget Summary -->
        <?php
        $totalSpent = 0;
        $totalCalories = 0;
        $totalWeight = 0;
        foreach ($displayRows as $row) {
            if ($row['price_per_unit'] && $row['delta'] > 0) {
                $firstWeight = $row['first_weight'];
                if ($firstWeight > 0) {
                    $totalSpent += ($row['price_per_unit'] / $firstWeight) * $row['delta'];
                }
            }
            if ($row['delta'] > 0 && isset($row['nutriments']['energy-kcal_100g'])) {
                $totalCalories += ($row['nutriments']['energy-kcal_100g'] / 100) * $row['delta'];
            }
            if ($row['delta'] > 0) {
                $totalWeight += $row['delta'];
            }
        }
        $avgCostPerCal = $totalCalories > 0 ? ($totalSpent / $totalCalories) : 0;
        $avgCostPerGram = $totalWeight > 0 ? ($totalSpent / $totalWeight) : 0;
        ?>
        
        <?php if ($totalSpent > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
                <div class="text-sm opacity-90 mb-1">$ Total Spent</div>
                <div class="text-4xl font-black">$<?= number_format($totalSpent, 2) ?></div>
                <div class="text-xs opacity-75 mt-2">Across <?= count(array_filter($displayRows, fn($r) => $r['delta'] > 0)) ?> consumption events</div>
            </div>
            
            <div class="bg-gradient-to-br from-blue-500 to-cyan-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
                <div class="text-sm opacity-90 mb-1">Lightning Cost Efficiency</div>
                <div class="text-3xl font-black">$<?= number_format($avgCostPerCal * 1000, 2) ?></div>
                <div class="text-xs opacity-75 mt-2">per 1,000 calories (lower = better!)</div>
            </div>
            
            <div class="bg-gradient-to-br from-purple-500 to-pink-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
                <div class="text-sm opacity-90 mb-1">Fire Total Calories</div>
                <div class="text-3xl font-black"><?= number_format($totalCalories) ?></div>
                <div class="text-xs opacity-75 mt-2">calories tracked & consumed</div>
            </div>
            
            <div class="bg-gradient-to-br from-orange-500 to-red-600 text-white p-6 rounded-2xl shadow-lg hover:shadow-xl transition-shadow">
                <div class="text-sm opacity-90 mb-1">Scale Avg Cost/Weight</div>
                <div class="text-3xl font-black">$<?= number_format($avgCostPerGram, 3) ?></div>
                <div class="text-xs opacity-75 mt-2">per gram consumed</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inventory Progress Bars -->
        <?php if (!empty($latestByUPC)): ?>
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4">Box Current Inventory</h2>
            <?php foreach ($latestByUPC as $upc => $info): 
                $remaining = $info['weight'];
                $original = $info['first_weight'];
                $consumed = $original - $remaining;
                $percentLeft = ($remaining / $original) * 100;
                
                $color = $percentLeft > 50 ? 'green' : ($percentLeft > 25 ? 'yellow' : 'red');
                $priceInfo = $prices[$upc] ?? null;
            ?>
                <div class="mb-4 p-4 bg-slate-50 rounded-xl">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-slate-700"><?= htmlspecialchars($info['name']) ?></span>
                            <?php if ($priceInfo): ?>
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-bold">
                                    $<?= number_format($priceInfo['price'], 2) ?>
                                </span>
                                <?php if ($priceInfo['promo_price']): ?>
                                    <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-bold pulse-sale">
                                        ON SALE!
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm font-bold text-<?= $color ?>-600"><?= round($percentLeft) ?>% left</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2.5 mb-2">
                        <div class="bg-<?= $color ?>-500 h-2.5 rounded-full transition-all" style="width: <?= min(100, max(0, $percentLeft)) ?>%"></div>
                    </div>
                    <div class="flex justify-between text-xs text-slate-600">
                        <span><?= number_format($remaining, 1) ?>g left</span>
                        <span><?= number_format($consumed, 1) ?>g consumed</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Graph -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-6">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-6">Chart Weight Trends Over Time</h2>
            <div class="h-[400px]">
                <canvas id="multiProductChart"></canvas>
            </div>
        </div>

        <button id="analyzeDietBtn" class="btn btn-primary">
          Analyze Diet
        </button>

        <div><pre id="dietAnalysisResult"></pre></div>

        <!-- Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <table class="w-full text-left border-collapse" id="foodTable">
                <thead>
                    <tr class="bg-slate-50 border-b text-xs font-bold text-slate-500 uppercase">
                        <th class="px-6 py-4 sortable" data-sort="name">Product <span class="sort-icon">â</span></th>
                        <th class="px-6 py-4 sortable" data-sort="weight">Weight <span class="sort-icon">â</span></th>
                        <th class="px-6 py-4 sortable" data-sort="delta">Consumption <span class="sort-icon">â</span></th>
                        <th class="px-6 py-4 sortable" data-sort="calories">Calories <span class="sort-icon">â</span></th>
                        <th class="px-6 py-4 sortable" data-sort="cost">$ Cost <span class="sort-icon">â</span></th>
                        <th class="px-6 py-4 sortable" data-sort="date">Date <span class="sort-icon">â</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="tableBody">
                    <?php foreach ($displayRows as $row): ?>
                    <tr class="hover:bg-slate-50 transition-colors clickable" data-upc="<?= htmlspecialchars($row['upc']) ?>">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-4">
                                <div class="w-3 h-10 rounded-full" style="background-color: <?= $row['color'] ?>;"></div>
                                
                                <img src="<?= $row['img'] ?: 'https://via.placeholder.com/40' ?>" class="w-10 h-10 object-contain bg-white rounded border">
                                
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="font-bold text-slate-800"><?= htmlspecialchars($row['name']) ?></div>
                                        
                                        <!-- Price Status Badge -->
                                        <?php if ($row['price_store']): ?>
                                            <span class="bg-emerald-100 text-emerald-700 text-[8px] font-bold px-2 py-0.5 rounded-full" title="<?= htmlspecialchars($row['price_store']) ?>">
                                                $ <?= number_format($row['price_per_unit'], 2) ?>
                                            </span>
                                            <?php if ($row['price_on_sale']): ?>
                                                <span class="bg-red-100 text-red-700 text-[8px] font-bold px-2 py-0.5 rounded-full pulse-sale">
                                                    SALE SALE
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="bg-slate-100 text-slate-500 text-[8px] font-bold px-2 py-0.5 rounded-full">
                                                X NO PRICE
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- NutriScore Badge -->
                                        <?php if ($row['nutriscore']): 
                                            $nutriColors = ['a' => 'bg-green-600', 'b' => 'bg-lime-500', 'c' => 'bg-yellow-500', 'd' => 'bg-orange-500', 'e' => 'bg-red-600'];
                                            $nutriColor = $nutriColors[strtolower($row['nutriscore'])] ?? 'bg-gray-400';
                                        ?>
                                            <span class="<?= $nutriColor ?> text-white text-[9px] font-bold px-1.5 py-0.5 rounded uppercase">
                                                <?= strtoupper($row['nutriscore']) ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <!-- Diet Badges -->
                                        <?php if ($row['vegan']): ?>
                                            <span class="bg-green-100 text-green-700 text-[9px] font-bold px-1.5 py-0.5 rounded">ð±</span>
                                        <?php elseif ($row['vegetarian']): ?>
                                            <span class="bg-lime-100 text-lime-700 text-[9px] font-bold px-1.5 py-0.5 rounded">ð¥¬</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-1">
                                        <?= htmlspecialchars($row['brand']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="px-6 py-4" data-value="<?= $row['weight'] ?>">
                            <?php if ($row['weight'] > 0): ?>
                                <div class="font-bold text-slate-700"><?= number_format($row['weight'], 2) ?>g</div>
                            <?php else: ?>
                                <span class="text-red-500 font-bold">0g</span>
                                <div class="text-[9px] uppercase font-bold text-red-400">Empty</div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="px-6 py-4" data-value="<?= $row['delta'] ?>">
                            <?php if ($row['delta'] > 0): ?>
                                <div class="text-emerald-600 font-black">-<?= number_format($row['delta'], 2) ?>g</div>
                                <div class="text-[9px] uppercase font-bold text-emerald-400">Consumed</div>
                            <?php elseif ($row['delta'] < 0): ?>
                                <div class="text-blue-500 font-bold">+<?= number_format(abs($row['delta']), 2) ?>g</div>
                                <div class="text-[9px] uppercase font-bold text-blue-300">Added/New</div>
                            <?php else: ?>
                                <span class="text-slate-300">--</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="px-6 py-4 text-xs" data-value="<?php 
                            if($row['delta'] > 0 && isset($row['nutriments']['energy-kcal_100g'])) {
                                echo ($row['nutriments']['energy-kcal_100g'] / 100) * $row['delta'];
                            } else {
                                echo 0;
                            }
                        ?>">
                            <?php 
                            if($row['delta'] > 0 && isset($row['nutriments']['energy-kcal_100g'])):
                                $kcal = ($row['nutriments']['energy-kcal_100g'] / 100) * $row['delta'];
                                echo "<div class='font-bold text-purple-600'>" . round($kcal) . "</div>";
                                echo "<div class='text-[9px] text-slate-400'>kcal</div>";
                            endif;
                            ?>
                        </td>
                        
                        <td class="px-6 py-4 text-xs" data-value="<?php
                            if ($row['price_per_unit'] && $row['delta'] > 0) {
                                $firstWeight = $row['first_weight'];
                                if ($firstWeight > 0) {
                                    echo ($row['price_per_unit'] / $firstWeight) * $row['delta'];
                                } else {
                                    echo 0;
                                }
                            } else {
                                echo 0;
                            }
                        ?>">
                            <?php if ($row['price_per_unit'] && $row['delta'] > 0): ?>
                                <?php
                                $firstWeight = $row['first_weight'];
                                if ($firstWeight > 0) {
                                    $costOfConsumption = ($row['price_per_unit'] / $firstWeight) * $row['delta'];
                                    $kcal = isset($row['nutriments']['energy-kcal_100g']) ? 
                                            ($row['nutriments']['energy-kcal_100g'] / 100) * $row['delta'] : 0;
                                    
                                    echo "<div class='font-black text-green-600 text-lg'>$" . number_format($costOfConsumption, 2) . "</div>";
                                    
                                    // $/calorie with color coding
                                    if ($kcal > 0) {
                                        $costPerCal = $costOfConsumption / $kcal;
                                        $costPer1000Cal = $costPerCal * 1000;
                                        $efficiencyColor = $costPer1000Cal < 5 ? 'text-green-600' : ($costPer1000Cal < 10 ? 'text-yellow-600' : 'text-red-600');
                                        $efficiencyLabel = $costPer1000Cal < 5 ? ' Great' : ($costPer1000Cal < 10 ? ' OK' : ' Expensive');
                                        echo "<div class='text-[9px] font-bold {$efficiencyColor}'>" . number_format($costPer1000Cal, 2) . "&cent;/1000cal {$efficiencyLabel}</div>";
                                    }
                                    
                                    // $/gram
                                    $costPerGram = $row['price_per_unit'] / $firstWeight;
                                    echo "<div class='text-[9px] text-slate-500 font-medium'>$" . number_format($costPerGram, 4) . "/g</div>";
                                }
                                ?>
                            <?php elseif ($row['delta'] > 0): ?>
                                <div class="text-slate-300 text-sm">--</div>
                                <div class="text-[8px] text-orange-600 font-bold bg-orange-50 px-1.5 py-0.5 rounded inline-block">
                                    ! ADD PRICE
                                </div>
                            <?php elseif ($row['price_per_unit']): ?>
                                <div class="text-slate-400 text-[10px] font-medium">
                                    <div class="font-bold">$<?= number_format($row['price_per_unit'], 2) ?></div>
                                    <div class="text-[8px] text-slate-400">per <?= $row['price_unit'] ?></div>
                                </div>
                            <?php else: ?>
                                <span class="text-slate-300">--</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="px-6 py-4 text-xs font-mono" data-value="<?= strtotime($row['date']) ?>">
                            <div class="font-bold text-slate-700">
                                <?= date('M j', strtotime($row['date'])) ?>
                            </div>
                            <div class="text-[10px] text-slate-400">
                                <?= date('g:i A', strtotime($row['date'])) ?>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Expandable Details Row -->
                    <tr class="details-row hidden bg-slate-50" data-upc="<?= $row['upc'] ?>">
                        <td colspan="6" class="px-6 py-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Nutrition Facts -->
                                <?php if ($row['nutriments']): ?>
                                <div>
                                    <h4 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                                        <span>NUTRITION (per 100g)</span>
                                    </h4>
                                    <div class="space-y-2 text-sm bg-white p-4 rounded-lg">
                                        <?php if (isset($row['nutriments']['energy-kcal_100g'])): ?>
                                            <div class="flex justify-between border-b pb-2">
                                                <span class="font-medium">Calories</span>
                                                <span class="font-bold text-purple-600"><?= round($row['nutriments']['energy-kcal_100g']) ?> kcal</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['proteins_100g'])): ?>
                                            <div class="flex justify-between">
                                                <span>Protein</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['proteins_100g'], 1) ?>g</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['carbohydrates_100g'])): ?>
                                            <div class="flex justify-between">
                                                <span>Carbs</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['carbohydrates_100g'], 1) ?>g</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['sugars_100g'])): ?>
                                            <div class="flex justify-between text-slate-600 text-xs pl-4">
                                                <span>â¢ Sugars</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['sugars_100g'], 1) ?>g</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['fat_100g'])): ?>
                                            <div class="flex justify-between">
                                                <span>Fat</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['fat_100g'], 1) ?>g</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['saturated-fat_100g'])): ?>
                                            <div class="flex justify-between text-slate-600 text-xs pl-4">
                                                <span>â¢ Saturated</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['saturated-fat_100g'], 1) ?>g</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['fiber_100g'])): ?>
                                            <div class="flex justify-between">
                                                <span>Fiber</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['fiber_100g'], 1) ?>g</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (isset($row['nutriments']['sodium_100g'])): ?>
                                            <div class="flex justify-between">
                                                <span>Sodium</span>
                                                <span class="font-mono"><?= number_format($row['nutriments']['sodium_100g'] * 1000, 0) ?>mg</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Ingredients & Allergens -->
                                <div class="md:col-span-2">
                                    <?php if ($row['ingredients_text']): ?>
                                        <h4 class="font-bold text-sm text-slate-700 mb-3 flex items-center gap-2">
                                            <span>INGREDIENTS</span>
                                        </h4>
                                        <div class="bg-white p-4 rounded-lg">
                                            <p class="text-xs text-slate-700 leading-relaxed"><?= htmlspecialchars($row['ingredients_text']) ?></p>
                                            
                                            <?php if (!empty($row['allergens'])): ?>
                                                <div class="mt-3 p-2 bg-red-50 border border-red-200 rounded">
                                                    <div class="font-bold text-xs text-red-700 mb-1">ALLERGENS</div>
                                                    <div class="text-xs text-red-600">
                                                        <?= implode(', ', array_map(fn($a) => ucfirst(str_replace('en:', '', $a)), $row['allergens'])) ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($row['additives_n'] > 0): ?>
                                                <div class="mt-2 text-xs text-orange-600">
                                                    ! Contains <?= $row['additives_n'] ?> additive<?= $row['additives_n'] > 1 ? 's' : '' ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($row['categories']): ?>
                                        <div class="mt-4">
                                            <h4 class="font-bold text-sm text-slate-700 mb-2">CATEGORIES</h4>
                                            <div class="flex flex-wrap gap-2">
                                                <?php foreach (array_slice(explode(',', $row['categories']), 0, 5) as $cat): ?>
                                                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                                                        <?= htmlspecialchars(trim(str_replace('en:', '', $cat))) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Footer Help -->
        <div class="mt-8 p-6 bg-blue-50 border-2 border-blue-200 rounded-2xl">
            <h3 class="font-bold text-blue-900 mb-2">TIP: Missing Prices?</h3>
            <p class="text-sm text-blue-700 mb-3">
                Fetch prices automatically using the Kroger API! Prices are stored in the database.
            </p>
            <code class="block bg-blue-100 text-blue-900 p-3 rounded-lg text-xs font-mono mb-2">
                python3 fetch_prices_db.py
            </code>
            <p class="text-xs text-blue-600 mt-2">
                Auto-updates all products from your food table! Run weekly to keep prices fresh.
            </p>
            <div class="mt-3 text-xs text-blue-600">
                * Prices sync automatically between Raspberry Pi and dashboard
            </div>
        </div>
    </div>

    <script>
        const datasets = [];
        const datasetsByUPC = {};

        <?php 
        $dsIndex = 0;
        foreach ($groups as $upc => $group): 
        ?>
            const dataset<?= $dsIndex ?> = {
                label: '<?= addslashes($group['label']) ?>',
                data: <?= json_encode($group['points']) ?>,
                borderColor: '<?= $group['color'] ?>',
                backgroundColor: '<?= $group['color'] ?>22',
                borderWidth: 3,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 8,
                fill: false,
                hidden: false
            };
            datasets.push(dataset<?= $dsIndex ?>);
            datasetsByUPC['<?= $upc ?>'] = <?= $dsIndex ?>;
        <?php $dsIndex++; endforeach; ?>

        const chart = new Chart(document.getElementById('multiProductChart'), {
            type: 'line',
            data: { datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                  x: {
                    type: 'time',
                    time: { unit: 'hour' }
                  },
                
                    y: {
                        beginAtZero: false,
                        title: { display: true, text: 'Grams' },
                        grid: { color: '#f1f5f9' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 20 },
                        onClick: (e, legendItem, legend) => {
                            const index = legendItem.datasetIndex;
                            const ci = legend.chart;
                            const meta = ci.getDatasetMeta(index);
                            meta.hidden = !meta.hidden;
                            ci.update();
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });

        // Table Sorting
        let currentSort = { column: 'date', direction: 'desc' };
        
        document.querySelectorAll('.sortable').forEach(th => {
            th.addEventListener('click', function() {
                const column = this.getAttribute('data-sort');
                
                if (currentSort.column === column) {
                    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.column = column;
                    currentSort.direction = 'asc';
                }
                
                document.querySelectorAll('.sortable').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                this.querySelector('.sort-icon').textContent = currentSort.direction === 'asc' ? '^' : 'v';
                
                sortTable(column, currentSort.direction);
            });
        });
        
        function sortTable(column, direction) {
            const tbody = document.getElementById('tableBody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const mainRows = rows.filter(r => !r.classList.contains('details-row'));
            
            mainRows.sort((a, b) => {
                let aVal, bVal;
                
                if (column === 'name') {
                    aVal = a.querySelector('td:nth-child(1) .font-bold').textContent.toLowerCase();
                    bVal = b.querySelector('td:nth-child(1) .font-bold').textContent.toLowerCase();
                } else if (column === 'weight') {
                    aVal = parseFloat(a.querySelector('td:nth-child(2)').getAttribute('data-value'));
                    bVal = parseFloat(b.querySelector('td:nth-child(2)').getAttribute('data-value'));
                } else if (column === 'delta') {
                    aVal = parseFloat(a.querySelector('td:nth-child(3)').getAttribute('data-value'));
                    bVal = parseFloat(b.querySelector('td:nth-child(3)').getAttribute('data-value'));
                } else if (column === 'calories') {
                    aVal = parseFloat(a.querySelector('td:nth-child(4)').getAttribute('data-value'));
                    bVal = parseFloat(b.querySelector('td:nth-child(4)').getAttribute('data-value'));
                } else if (column === 'cost') {
                    aVal = parseFloat(a.querySelector('td:nth-child(5)').getAttribute('data-value'));
                    bVal = parseFloat(b.querySelector('td:nth-child(5)').getAttribute('data-value'));
                } else if (column === 'date') {
                    aVal = parseInt(a.querySelector('td:nth-child(6)').getAttribute('data-value'));
                    bVal = parseInt(b.querySelector('td:nth-child(6)').getAttribute('data-value'));
                }
                
                if (direction === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
            
            tbody.innerHTML = '';
            mainRows.forEach(row => {
                tbody.appendChild(row);
                const detailRow = row.nextElementSibling;
                if (detailRow && detailRow.classList.contains('details-row')) {
                    tbody.appendChild(detailRow);
                }
            });
        }

        // Click handlers with event delegation
        document.querySelectorAll('#tableBody tr:not(.details-row)').forEach(row => {
            row.addEventListener('click', function(e) {
                const upc = this.getAttribute('data-upc');
                const detailRow = this.nextElementSibling;
                
                if (detailRow && detailRow.classList.contains('details-row')) {
                    // Toggle details
                    const wasHidden = detailRow.classList.contains('hidden');
                    
                    // Close all other details
                    document.querySelectorAll('.details-row').forEach(r => r.classList.add('hidden'));
                    document.querySelectorAll('tr:not(.details-row)').forEach(r => r.classList.remove('row-highlighted'));
                    
                    if (wasHidden) {
                        detailRow.classList.remove('hidden');
                        this.classList.add('row-highlighted');
                        
                        // Highlight on graph
                        const datasetIndex = datasetsByUPC[upc];
                        if (datasetIndex !== undefined) {
                            datasets.forEach((ds, idx) => {
                                const meta = chart.getDatasetMeta(idx);
                                if (idx === datasetIndex) {
                                    meta.hidden = false;
                                    ds.borderWidth = 5;
                                    ds.pointRadius = 6;
                                } else {
                                    meta.hidden = true;
                                }
                            });
                            chart.update();
                            
                            setTimeout(() => {
                                datasets.forEach((ds, idx) => {
                                    const meta = chart.getDatasetMeta(idx);
                                    meta.hidden = false;
                                    ds.borderWidth = 3;
                                    ds.pointRadius = 4;
                                });
                                chart.update();
                            }, 3000);
                        }
                    } else {
                        // Reset graph
                        datasets.forEach((ds, idx) => {
                            const meta = chart.getDatasetMeta(idx);
                            meta.hidden = false;
                            ds.borderWidth = 3;
                            ds.pointRadius = 4;
                        });
                        chart.update();
                    }
                }
            });
        });
      <?php
      $foodData = array_map(function ($f) {
    return [
        'name' => $f['name'],
        'date' => $f['date'],
        'foodweight' => round($f['weight'], 1),
        'delta' => round($f['delta'], 1),
        'nutriscore' => $f['nutriscore'],
        'nova_group' => $f['nova_group'],
        'calories' => $f['nutriments']['energy-kcal'] ?? null
    ]; }, $displayRows); ?>
      var  foodData = <?php
            echo json_encode(
      $foodData,
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
  ?>;

        document.getElementById('analyzeDietBtn').addEventListener('click', async () => {
          document.getElementById('dietAnalysisResult').innerText = "Thinking...";
						const res = await fetch('analyze_diet.php', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json' },
							body: JSON.stringify({
								foods: foodData
							})
						});

						if (!res.ok) {
							throw new Error(`HTTP ${res.status}`);
						}

						const reader = res.body.getReader();
						const decoder = new TextDecoder();
            var append = false;
						while (true) {
							const { value, done } = await reader.read();
							if (done) break;

							const chunk = decoder.decode(value, { stream: true });
							if (append !== true) { 
              document.getElementById('dietAnalysisResult').textContent = chunk;
              append = true;
              } else { document.getElementById('dietAnalysisResult').textContent += chunk;
						
            }
            }
				});
        
				
    </script>
</body>
</html>

