const WHATTOMINE_URL = "https://whattomine.com/coins/";

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
                // Update coin summaries (index.php)
                const coinSummaries = document.getElementById('coin-summaries');
                if (coinSummaries) {
                    Object.keys(data.data).forEach(coin => {
                        const card = document.querySelector(`.coin-card[data-coin="${coin}"]`);
                        if (card) {
                            const profitabilityEl = card.querySelector('.coin-profitability');
                            const difficultyEl = card.querySelector('.coin-difficulty');
                            profitabilityEl.textContent = `$${parseFloat(data.data[coin].profitability).toFixed(5)}/day`;
                            difficultyEl.textContent = Number(data.data[coin].difficulty).toLocaleString();
                            profitabilityEl.classList.add('update-pulse');
                            difficultyEl.classList.add('update-pulse');
                            setTimeout(() => {
                                profitabilityEl.classList.remove('update-pulse');
                                difficultyEl.classList.remove('update-pulse');
                            }, 1000);
                        }
                    });
                }

                // Update coin select options (calculator.php)
                const coinSelect = document.getElementById('coin');
                if (coinSelect) {
                    Array.from(coinSelect.options).forEach(option => {
                        if (data.data[option.value]) {
                            option.dataset.profitability = data.data[option.value].profitability;
                        }
                    });
                }
            })
            .catch(error => console.error('Error fetching coin data:', error));
    }

    // Poll every 60 seconds
    updateCoinData(); // Initial update
    setInterval(updateCoinData, 60000);
    
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