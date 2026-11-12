# SuperMiner.com MVP

## Setup Steps
1. **Environment**: Deploy in the provided container at `/var/www/html/superminer`.
2. **Dependencies**:
   - Install PHP 8.0 or newer with the `curl` extension, and Apache. See [Run locally](#run-locally).
   - Bootstrap 5 is loaded from a CDN by the pages in `public/`.
3. **Configuration**:
   - Copy `.env.example` to `.env` and adjust the values in [Configuration](#configuration).
   - Keep `.env` out of the web root; `public/.htaccess` already denies `.env` and `.json`.
4. **Data**:
   - Open the home page or `/api/coins.php` to refresh `config/coins.json`. See [Run locally](#run-locally).
   - Update `config/hardware.json` with additional GPU/ASIC specs as needed.
5. **Deployment**:
   - Place files in `/var/www/html/superminer`.
   - Test via container’s SSH access.
6. **Branding**: Logos and favicon in `/public/assets/logo/`.

## Run locally

PHP 8.0 or newer, with the `curl` extension. `public/index.php` passes a named argument to `number_format()`, and `fetchMiningData()` requests WhatToMine with curl. The workflow in `.github/workflows/ci.yml` lints every PHP file on PHP 8.3.

From the project root:

```bash
cp .env.example .env
php -S localhost:8080 -t public
```

`public/` is the document root, so the header links `/` and `/calculator.php` resolve to files in that directory.

| URL | What it does |
| --- | --- |
| http://localhost:8080/ | Top 10 coins. Reuses `config/coins.json` while the file is under 60 seconds old. After that, `fetchMiningData()` refreshes it from `API_WHATTOMINE_URL`. |
| http://localhost:8080/calculator.php | ROI form. Reads `config/coins.json` and `config/hardware.json`, then prints estimated daily profit after you submit. |
| http://localhost:8080/api/coins.php | Forces a refresh and returns JSON with `data` and `timestamp`. |

`includes/api.php` defines `fetchMiningData()`. Executing that file on its own loads the function and exits. Refresh the cache by opening the home page or `/api/coins.php`. When the WhatToMine request fails or returns a status other than 200, the function writes a small built-in coin list into `config/coins.json`.

To run the same syntax check as CI:

```bash
find . -path './.git' -prune -o -type f -name '*.php' -print0 | xargs -0 -n1 php -l
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

`config/coins.json` is the cache written by `includes/api.php` (60-second TTL). `config/hardware.json` lists sample GPU and ASIC hash rate and wattage; the calculator reads `config/coins.json` for profitability.
