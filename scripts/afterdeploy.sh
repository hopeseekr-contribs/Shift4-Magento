#!/bin/bash
php /var/www/magento/public_html/bin/magento setup:upgrade
php /var/www/magento/public_html/bin/magento cache:clean
rm -fr /var/www/magento/public_html/pub/static/frontend/
rm -fr /var/www/magento/public_html/pub/static/adminhtml/
rm -fr /var/www/magento/public_html/var/cache/
php /var/www/magento/public_html/bin/magento setup:static-content:deploy -f
php /var/www/magento/public_html/bin/magento setup:di:compile
chmod -R 777 /var/www/magento/public_html/