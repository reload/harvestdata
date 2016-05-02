#!/bin/sh

sed -i -e "s/\$account/$HARVESTDATA_ACCOUNT/" \
    -e "s/\$username/$HARVESTDATA_USERNAME/" \
    -e "s/\$password/$HARVESTDATA_PASSWORD/" \
    /harvestdata/config.yml

if [ ! -e /harvestdata/data/today.xml ]
then
    echo "First run, running all cron entries"
    grep "^#" -v /harvestdata/docker/crontab | while read -r m h dom mon dow cmd; do sh -c "$cmd"; done
    echo "Done"
fi
