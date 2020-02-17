find . -name '*.csv' -exec bash -c '
    /usr/local/bin/php /home/marke142/public_html/myjellysite/dasparts/administrator/components/com_csvi/helper/cron.php --key="12345" --template_name="J2Store Complete Import" --file="$1" >> /home/marke142/public_html/myjellysite/dasparts/imports/log.txt
    rm $1
' -- {} \;
