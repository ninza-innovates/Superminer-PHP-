# SuperMiner.com MVP

## Setup Steps
1. **Environment**: Deploy in the provided container at `/var/www/html/superminer`.
2. **Dependencies**:
   - Install PHP 8.x and Apache.
   - Ensure Bootstrap 5 is loaded via CDN.
3. **Configuration**:
   - Copy `.env.example` to `.env` and adjust the values in [Configuration](#configuration).
   - Keep `.env` out of the web root; `public/.htaccess` already denies `.env` and `.json`.
4. **Data**:
   - Run `includes/api.php` to fetch and cache mining data to `config/coins.json`.
   - Update `config/hardware.json` with additional GPU/ASIC specs as needed.
5. **Deployment**:
   - Place files in `/var/www/html/superminer`.
   - Test via container’s SSH access.
6. **Branding**: Logos and favicon in `/public/assets/logo/`.

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
