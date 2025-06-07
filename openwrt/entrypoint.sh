#!/bin/sh

./setup.sh

echo "src-link web4static /builder/src/openwrt" > feeds.conf

./scripts/feeds update web4static
./scripts/feeds install -a -p web4static

make defconfig
make CONFIG_USE_APK=y package/web4static/compile V=s
make CONFIG_USE_APK=y package/index V=s
make CONFIG_USE_APK= package/web4static/compile V=s
make CONFIG_USE_APK= package/index V=s
