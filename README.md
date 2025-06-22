# SuperMiner.com MVP

## Setup Steps
1. **Environment**: Deploy in the provided container at `/var/www/html/superminer`.
2. **Dependencies**:
   - Install PHP 8.x and Apache.
   - Ensure Bootstrap 5 is loaded via CDN.
3. **Configuration**:
   - Add API keys and affiliate links to `/var/www/html/superminer/.env`.
   - Secure .env with .htaccess (already configured).
4. **Data**:
   - Run `includes/api.php` to fetch and cache mining data to `config/coins.json`.
   - Update `config/hardware.json` with additional GPU/ASIC specs as needed.
5. **Deployment**:
   - Place files in `/var/www/html/superminer`.
   - Test via container’s SSH access.
6. **Branding**: Logos and favicon in `/public/assets/logo/`.