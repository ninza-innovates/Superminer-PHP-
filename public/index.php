<?php
// /var/www/html/superminer/public/index.php
require_once '../includes/config.php';
require_once '../includes/api.php';

$coins = fetchMiningData();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SuperMiner.com - Mining Profitability</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
        <link rel="stylesheet" href="assets/css/style.css">
        <link rel="icon" href="assets/logo/superminer.jpg">
    </head>
    <body>
        <header class="hero-section text-white" id="site-header">
            <a href="/" class="d-inline-flex align-items-center text-decoration-none text-white gap-3">
                <img src="assets/logo/superminer.jpg" alt="SuperMiner.com Logo" class="logo-glow" style="width: 56px; height: 56px; border-radius: 50%">
                <h2 class="fs-2 fw-bold d-sm-flex d-none">SuperMiner.com</h2>
            </a>
            <ul class="nav fs-6 header-nav d-lg-flex d-none">
                <li class="nav-item">
                    <a class="nav-link active" href="/">Top 10 Coins</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/calculator.php">ROI Calculator</a>
                </li>
            </ul>
            <div class="dropdown d-md-flex d-lg-none">
                <a class="btn btn-secondary dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
                    </svg>
                </a>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item active" href="/">Top 10 Coins</a></li>
                    <li><a class="dropdown-item" href="/calculator.php">ROI Calculator</a></li>
                </ul>
            </div>
        </header>

        <section class="row w-100" id="main-content">
            <div class="col-lg-2 border-end d-none d-lg-flex justify-content-center align-items-center">Left-AD Section</div>
            <div class="col-lg-8 pt-3 pb-3">
                <h2 class="text-center mb-4 fw-bold text-primary">Top 10 Coins</h2>
                <div class="row g-4" id="coin-summaries">
                    <?php $i = 0; ?>
                    <?php foreach ($coins as $coin => $data): ?>
                        <?php $i ++; ?>
                        <div class="col-lg-4 col-md-12">
                            <a class="card coin-card shadow-sm h-100 text-decoration-none" href="<?php echo !empty($data['link']) ? htmlspecialchars($data['link']) : '#'; ?>" target="_blank" data-coin="<?php echo htmlspecialchars($coin); ?>">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-secondary fw-bold"><?php echo htmlspecialchars($coin); ?></h5>
                                    <p class="card-text text-muted">Profitability: <span class="text-success fw-bold coin-profitability">$<?php echo number_format($data['profitability'], decimals: 5); ?>/day</span></p>
                                    <p class="card-text text-muted">Difficulty: <span class="fw-bold coin-difficulty"><?php echo number_format($data['difficulty'], 10); ?></span></p>
                                </div>
                            </a>
                        </div>
                        <?php if ($i === 5): ?>
                            <div class="col-md-12 d-md-block d-sm-block d-lg-none">
                                <div class="card coin-card shadow-sm min-h-100 text-decoration-none d-flex justify-content-center align-items-center">
                                    AD Section
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-2 border-start d-none d-lg-flex justify-content-center align-items-center">Right-AD Section</div>
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

        <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
        <script src="assets/js/script.js"></script>
    </body>
</html>