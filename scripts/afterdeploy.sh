#!/bin/bash
#!/bin/bash
/usr/bin/php7.4 /var/www/magento/public_html/bin/magento setup:upgrade
/usr/bin/php7.4 /var/www/magento/public_html/bin/magento cache:clean
rm -fr /var/www/magento/public_html/pub/static/frontend/
rm -fr /var/www/magento/public_html/pub/static/adminhtml/
rm -fr /var/www/magento/public_html/var/cache/
/usr/bin/php7.4 /var/www/magento/public_html/bin/magento setup:upgrade
/usr/bin/php7.4 /var/www/magento/public_html/bin/magento setup:di:compile
/usr/bin/php7.4 /var/www/magento/public_html/bin/magento setup:static-content:deploy -f
/usr/bin/php7.4 /var/www/magento/public_html/bin/magento cache:clean
chmod -R 777 /var/www/magento/public_html/
