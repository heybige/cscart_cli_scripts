#!/bin/bash

export ROOT=/path/to/cscart

ADDONS=()

ADDON_LIST=`cd $ROOT; git diff "HEAD@{1}" --name-only | egrep 'app/addons/.+?/addon.xml'`

if [ ! -z "$ADDON_LIST" ]; then
    for file in $ADDON_LIST; do
        OIFS=$IFS
        IFS='/'
        arrItems=($file)
        ADDONS=("${ADDONS[@]}" ${arrItems[2]})
        IFS=$OIFS
    done
fi

ADDON_LIST=`cd $ROOT; git diff "HEAD@{1}" --name-only | grep 'var/themes_repository/basic/templates/addons/'`

if [ ! -z "$ADDON_LIST" ]; then
    for file in $ADDON_LIST; do
        OIFS=$IFS
        IFS='/'
        arrItems=($file)
        ADDONS=("${ADDONS[@]}" ${arrItems[5]})
        IFS=$OIFS
    done
fi

function join { local IFS="$1"; shift; echo "$*"; }

if [ ! -z "$ADDONS" ]; then

	if [ ${#ADDONS[@]} -gt 1 ]; then
		CHANGED=($(printf "%s\n" "${ADDONS[@]}" | sort -u))
		LIST=`join : "${CHANGED[@]}"`
	else
		LIST=${ADDONS[0]}
	fi

    echo "php $ROOT/bin/php_flip_addon.php $LIST"
	php $ROOT/bin/php_flip_addon.php $LIST
fi
