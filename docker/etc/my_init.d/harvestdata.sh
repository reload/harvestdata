#!/bin/sh

sed -i -e "s/\$account/$HARVESTDATA_ACCOUNT/" \
    -e "s/\$username/$HARVESTDATA_USERNAME/" \
    -e "s/\$password/$HARVESTDATA_PASSWORD/" \
    /harvestdata/config.yml
