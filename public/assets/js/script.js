const WHATTOMINE_URL = "https://whattomine.com/coins/";

// Same contract as formatCoinNumber() in includes/functions.php: fixed
// decimals, "," grouping, and null when the stat is missing or not finite.
// The poll used to write $NaN, drop separators, and round difficulty by locale.
function formatCoinNumber(value, decimals) {
    if (typeof value === 'string') {
        value = value.trim();
    }
    if (typeof value === 'number') {
        if (!Number.isFinite(value)) {
            return null;
        }
    } else if (typeof value === 'string') {
        if (!/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/.test(value)) {
            return null;
        }
        value = Number(value);
        if (!Number.isFinite(value)) {
            return null;
        }
    } else {
        return null;
    }

    const places = decimals | 0;
    if (places < 0 || places > 100) {
        return null;
    }
    const negative = value < 0;
    const fixed = Math.abs(value).toFixed(places);
    if (fixed.indexOf('e') !== -1 || fixed.indexOf('E') !== -1) {
        return null;
    }
    const parts = fixed.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (negative ? '-' : '') + parts.join('.');
}

function coinProfitabilityText(value) {
    const formatted = formatCoinNumber(value, 5);
    return formatted === null ? 'n/a' : '$' + formatted + '/day';
}

function coinDifficultyText(value) {
    const formatted = formatCoinNumber(value, 10);
    return formatted === null ? 'n/a' : formatted;
}

// Compare the decoded attribute. A selector built from the coin name throws
// when that name contains a quote or a backslash, and then no card updates.
function findCoinCard(root, coin) {
    const wanted = String(coin);
    const cards = root.querySelectorAll('.coin-card[data-coin]');
    for (let i = 0; i < cards.length; i++) {
        if (cards[i].getAttribute('data-coin') === wanted) {
            return cards[i];
        }
    }
    return null;
}

function applyCoinPoll(data) {
    const coins = data && data.data;
    if (!coins || typeof coins !== 'object') {
        return;
    }

    const coinSummaries = document.getElementById('coin-summaries');
    if (coinSummaries) {
        Object.keys(coins).forEach(coin => {
            const info = coins[coin];
            if (!info || typeof info !== 'object') {
                return;
            }
            const card = findCoinCard(coinSummaries, coin);
            if (!card) {
                return;
            }
            const profitabilityEl = card.querySelector('.coin-profitability');
            const difficultyEl = card.querySelector('.coin-difficulty');
            if (!profitabilityEl || !difficultyEl) {
                return;
            }
            profitabilityEl.textContent = coinProfitabilityText(info.profitability);
            difficultyEl.textContent = coinDifficultyText(info.difficulty);
            profitabilityEl.classList.add('update-pulse');
            difficultyEl.classList.add('update-pulse');
            setTimeout(() => {
                profitabilityEl.classList.remove('update-pulse');
                difficultyEl.classList.remove('update-pulse');
            }, 1000);
        });
    }

    const coinSelect = document.getElementById('coin');
    if (coinSelect) {
        Array.from(coinSelect.options).forEach(option => {
            const info = coins[option.value];
            if (info && typeof info === 'object') {
                option.dataset.profitability = info.profitability;
            }
        });
    }
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', function () {
        // Form validation for calculator
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                const hashRate = document.getElementById('hashRate');
                const powerCost = document.getElementById('powerCost');
                const powerUsage = document.getElementById('powerUsage');
                const hardwareCost = document.getElementById('hardwareCost');
            
                if (hashRate.value <= 0 || powerCost.value <= 0 || powerUsage.value <= 0 || hardwareCost.value <= 0) {
                    alert('Please enter positive values for all fields.');
                    e.preventDefault();
                }
            });
        }

        // Card hover animation
        const cards = document.querySelectorAll('.coin-card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function () {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function () {
                this.style.transform = 'translateY(0)';
            });
        });

        // Adjust main content height based on footer height
        function adjustMainContentHeight() {
            const header = document.getElementById('site-header');
            const footer = document.getElementById('site-footer');
            const mainContent = document.getElementById('main-content');
            if (footer && mainContent) {
                const footerHeight = footer.offsetHeight;
                const headerHeight = header.offsetHeight;
                console.log(`Footer Height: ${footerHeight}px, Header Height: ${headerHeight}px`);
                mainContent.style.height = `calc(100vh - ${footerHeight}px - ${headerHeight}px)`;
            }
        }

        // Run height adjustment on page load and resize
        adjustMainContentHeight();
        window.addEventListener('resize', adjustMainContentHeight);

        // Real-time data polling
        function updateCoinData() {
            fetch('api/coins.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network error');
                    return response.json();
                })
                .then(data => {
                    applyCoinPoll(data);
                })
                .catch(error => console.error('Error fetching coin data:', error));
        }

        // Poll every 60 seconds
        updateCoinData(); // Initial update
        setInterval(updateCoinData, 60000);
    
        const hardwareSelect = document.getElementById('hardware');
        if (hardwareSelect) {
            hardwareSelect.addEventListener('change', function () {
                const option = this.options[this.selectedIndex];
                const hashRate = option.getAttribute('data-hash-rate');
                const powerUsage = option.getAttribute('data-power-usage');
                if (hashRate) {
                    document.getElementById('hashRate').value = hashRate;
                }
                if (powerUsage) {
                    document.getElementById('powerUsage').value = powerUsage;
                }
            });
        }

        const coinSelector = document.getElementById('coin');
        const selectedOption = coinSelector?.options[coinSelector.selectedIndex];
        if (selectedOption) {
            const dataHref = selectedOption.getAttribute('data-href');
            document.getElementById('explorer_btn').href = dataHref;
        }

        if (document.getElementById('coin')) {
            document.getElementById('coin').onchange = function () {
                const selectedOption = this.options[this.selectedIndex];
                const dataHref = selectedOption.getAttribute('data-href');
                document.getElementById('explorer_btn').href = dataHref;
            };
        }
    });
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        formatCoinNumber: formatCoinNumber,
        coinProfitabilityText: coinProfitabilityText,
        coinDifficultyText: coinDifficultyText
    };
}
