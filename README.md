# SuperMiner.com MVP

PHP pages in `public/` list the top mining coins and estimate hardware ROI.

## Setup Steps
1. **Environment**: Deploy in the provided container at `/var/www/html/superminer`.
2. **Dependencies**:
   - Install PHP 8.0 or newer with the `curl` extension, and Apache. See [Run locally](#run-locally).
   - Bootstrap 5 is loaded from a CDN by the pages in `public/`.
3. **Configuration**:
   - Copy `.env.example` to `.env` and adjust the values in [Configuration](#configuration).
   - Keep `.env` out of the web root. On Apache, `public/.htaccess` denies files ending in `.env` or `.json`. The built-in server ignores `.htaccess`; `-t public` still cannot serve the project-root `.env`.
4. **Data**:
   - Open the home page or `/api/coins.php` to refresh `config/coins.json`. See [Run locally](#run-locally).
   - `config/hardware.json` lists sample GPU and ASIC hash rates and wattage. The calculator uses those rows as presets. Add a row to offer another machine.
5. **Web server**:
   - Serve the `public/` directory. Page links are relative (`./` and `calculator.php`), so they stay inside that directory when it is the document root and when the app is mounted at `/superminer/public/`.
   - Apache, project tree at `/var/www/html/superminer`: open `/superminer/public/`. `public/.htaccess` sets `RewriteBase /superminer/public/` for that prefix.
   - Apache with `DocumentRoot` pointed at `public/`: change that `RewriteBase` to `/`.
6. **Branding**: The logo and the favicon are both `public/assets/logo/superminer.jpg`.

## Run locally

PHP 8.0 or newer, with the `curl` extension. `public/index.php` passes a named argument to `number_format()`, and `fetchMiningData()` requests WhatToMine with curl. The workflow in `.github/workflows/ci.yml` lints every PHP file on PHP 8.3.

From the project root:

```bash
cp .env.example .env
php -S localhost:8080 -t public
```

`public/` is the document root. Header links are `./` and `calculator.php`, so they resolve to files in that directory.

| URL | What it does |
| --- | --- |
| http://localhost:8080/ | Top 10 coins, ranked by the WhatToMine profitability score. Reuses `config/coins.json` while the file is under 60 seconds old. After that, `fetchMiningData()` refreshes it from `API_WHATTOMINE_URL`. |
| http://localhost:8080/calculator.php | ROI form. Profitability comes from `config/coins.json`. A hardware preset fills hash rate and watts from `config/hardware.json`. Submit to print estimated daily profit. The server only calculates when the coin is listed and hash rate, power cost, wattage, and hardware cost are positive numbers. |
| http://localhost:8080/api/coins.php | Forces a refresh and returns JSON with `data` and `timestamp`. |

`includes/api.php` defines `fetchMiningData()`. Executing that file on its own loads the function and exits. Refresh the cache by opening the home page or `/api/coins.php`. When the WhatToMine request fails or returns a status other than 200, the function writes a small built-in coin list into `config/coins.json`.

To run the same syntax check as CI:

```bash
find . -path './.git' -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 php -l
```

CI also runs a form test that the ROI result keeps the submitted coin, inputs, and hardware preset:

```bash
php tests/calculator_form_test.php
```

CI also checks that coin links stay on http(s) and that calculator profitability attributes are escaped:

```bash
php tests/coin_link_test.php
```

CI also checks that a refresh keeps the ten highest WhatToMine profitability scores:

```bash
php tests/top_coins_test.php
```

## Configuration

`includes/config.php` reads a project-root `.env` (INI format, no sections). Copy `.env.example` and keep all five keys. Missing keys make those constants fail at startup.

| Key | Purpose |
| --- | --- |
| `WHATTOMINE_URL` | Base URL for coin links built in `includes/api.php`. |
| `API_WHATTOMINE_URL` | WhatToMine JSON endpoint requested by `fetchMiningData()`. |
| `API_NICEHASH_URL` | Defined for a future NiceHash client. Nothing calls it yet. |
| `AFFILIATE_AMAZON` | Footer "Buy on Amazon" href on the home page and calculator. |
| `AFFILIATE_NEWEGG` | Footer "Buy on Newegg" href on the home page and calculator. |

The example uses public WhatToMine and NiceHash URLs and plain store homepages. Replace the affiliate values with your own links. Do not commit `.env`.

`config/coins.json` is the cache written by `includes/api.php` (60-second TTL). `config/hardware.json` lists sample GPU and ASIC hash rate and wattage. Choosing a preset on the calculator copies those two numbers into the form; profitability still comes from `config/coins.json`.
