<?php
// /var/www/html/superminer/public/calculator.php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/html.php';

$hardware = json_decode(file_get_contents(CONFIG_DIR . '/hardware.json'), true);
if (!is_array($hardware)) {
    $hardware = [];
}
$coins = json_decode(file_get_contents(CONFIG_DIR . '/coins.json'), true);

$result = '';
$breakEven = '';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
$fields = [
    'coin' => '',
    'hardware' => '',
    'hashRate' => '',
    'powerCost' => '',
    'powerUsage' => '',
    'hardwareCost' => '',
];
if ($isPost) {
    foreach ($fields as $name => $_) {
        $raw = $_POST[$name] ?? '';
        $fields[$name] = is_string($raw) ? $raw : '';
    }
    $hashRate = floatval($fields['hashRate']);
    $powerCost = floatval($fields['powerCost']);
    $powerUsage = floatval($fields['powerUsage']);
    $hardwareCost = floatval($fields['hardwareCost']);
    $profitPerDay = $coins[$fields['coin']]['profitability'] ?? 0;
    $result = calculateROI($hashRate, $powerCost, $powerUsage, $profitPerDay);
    if ($hardwareCost > 0 && $result > 0) {
        $breakEven = round($hardwareCost / $result, 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SuperMiner.com - ROI Calculator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="assets/logo/superminer.jpg">
</head>
<body>
    <header class="hero-section text-white" id="site-header">
        <a href="./" class="d-inline-flex align-items-center text-decoration-none text-white gap-3">
            <img src="assets/logo/superminer.jpg" alt="SuperMiner.com Logo" class="logo-glow" style="width: 56px; height: 56px; border-radius: 50%">
            <h2 class="fs-2 fw-bold d-sm-flex d-none">SuperMiner.com</h2>
        </a>
        <ul class="nav fs-6 header-nav d-lg-flex d-none">
            <li class="nav-item">
                <a class="nav-link" href="./">Top 10 Coins</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="calculator.php">ROI Calculator</a>
            </li>
        </ul>
        <div class="dropdown d-md-flex d-lg-none">
            <a class="btn btn-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                </svg>
            </a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="./">Top 10 Coins</a></li>
                <li><a class="dropdown-item active" href="calculator.php">ROI Calculator</a></li>
            </ul>
        </div>
    </header>

    <section class="row w-100" id="main-content">
        <div class="col-lg-2 border-end d-flex justify-content-center align-items-center">AD Section</div>
        <div class="col-lg-8 py-5">
            <h2 class="text-center mb-4 fw-bold text-primary">ROI Calculator</h2>
            <div class="card calc-card shadow-sm p-4">
                <form method="POST" class="row g-3">
                    <div class="col-md-6">
                        <label for="coin" class="form-label fw-bold text-secondary">Coin</label>
                        <select name="coin" id="coin" class="form-select" required>
                            <?php foreach ($coins as $coin => $data): ?>
                                <?php
                                $coinLink = httpUrlOrHash(is_array($data) ? ($data['link'] ?? null) : null);
                                $coinProfit = '';
                                if (is_array($data) && isset($data['profitability']) && is_scalar($data['profitability'])) {
                                    $coinProfit = (string) $data['profitability'];
                                }
                                ?>
                                <option data-href="<?php echo htmlspecialchars($coinLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($coin); ?>" data-profitability="<?php echo htmlspecialchars($coinProfit, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"<?php echo $fields['coin'] === $coin ? ' selected' : ''; ?>><?php echo htmlspecialchars($coin); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="hardware" class="form-label fw-bold text-secondary">Hardware preset</label>
                        <select name="hardware" id="hardware" class="form-select">
                            <option value="">Custom</option>
                            <?php foreach ($hardware as $name => $spec): ?>
                                <option value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>" data-hash-rate="<?php echo htmlspecialchars((string) ($spec['hashRate'] ?? ''), ENT_QUOTES); ?>" data-power-usage="<?php echo htmlspecialchars((string) ($spec['powerUsage'] ?? ''), ENT_QUOTES); ?>"<?php echo $fields['hardware'] === $name ? ' selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="hashRate" class="form-label fw-bold text-secondary">Hash Rate (MH/s)</label>
                        <input type="number" name="hashRate" id="hashRate" class="form-control" step="0.1" required value="<?php echo htmlspecialchars($fields['hashRate'], ENT_QUOTES); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="powerCost" class="form-label fw-bold text-secondary">Power Cost ($/kWh)</label>
                        <input type="number" name="powerCost" id="powerCost" class="form-control" step="0.01" required value="<?php echo htmlspecialchars($fields['powerCost'], ENT_QUOTES); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="powerUsage" class="form-label fw-bold text-secondary">Power Usage (W)</label>
                        <input type="number" name="powerUsage" id="powerUsage" class="form-control" required value="<?php echo htmlspecialchars($fields['powerUsage'], ENT_QUOTES); ?>">
                    </div>
                    <div class="col-md-6">
                        <label for="hardwareCost" class="form-label fw-bold text-secondary">Hardware Cost ($)</label>
                        <input type="number" name="hardwareCost" id="hardwareCost" class="form-control" step="0.01" required value="<?php echo htmlspecialchars($fields['hardwareCost'], ENT_QUOTES); ?>">
                    </div>
                    <div class="col-12 text-center">
                        <button type="submit" class="btn btn-primary btn-lg cta-btn">Calculate</button>
                        <a href="#" target="_blank" class="btn btn-secondary btn-lg explorer-btn" id="explorer_btn">Explorer</a>
                    </div>
                </form>
                <?php if ($result !== ''): ?>
                    <div class="alert alert-success mt-4 text-center">
                        <h5>Estimated Daily Profit: <span class="fw-bold text-success">$<?php echo $result; ?></span></h5>
                        <?php if ($breakEven !== ''): ?>
                            <p>Break-Even Time: <span class="fw-bold"><?php echo $breakEven; ?> days</span></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-2 border-start d-flex justify-content-center align-items-center">AD Section</div>
    </section>

    <footer class="text-center fixed-bottom bg-none" id="site-footer">
        <div class="ad bg-white border-start d-flex justify-content-center align-items-center" style="height: 100px;">Bottom-AD Section</div>
        <div class="bg-dark text-white py-3">
            <p class="mb-0">© 2025 SuperMiner.com | 
            <a href="<?php echo AFFILIATE_AMAZON; ?>" class="text-white">Buy on Amazon</a> | 
            <a href="<?php echo AFFILIATE_NEWEGG; ?>" class="text-white">Buy on Newegg</a>
        </p>
        </div>    
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>