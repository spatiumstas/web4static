_web-clean:
	rm -rf out/$(BUILD_DIR)
	mkdir -p out/$(BUILD_DIR)/control
	mkdir -p out/$(BUILD_DIR)/data

_web-control:
	echo "Package: web4static" > out/$(BUILD_DIR)/control/control
	echo "Version: $(VERSION)" >> out/$(BUILD_DIR)/control/control
	echo "Depends: php8-cgi, php8-mod-session, lighttpd, lighttpd-mod-cgi, lighttpd-mod-setenv, lighttpd-mod-rewrite, lighttpd-mod-redirect" >> out/$(BUILD_DIR)/control/control
	echo "License: MIT" >> out/$(BUILD_DIR)/control/control
	echo "Section: net" >> out/$(BUILD_DIR)/control/control
	echo "URL: https://github.com/spatiumstas/web4static" >> out/$(BUILD_DIR)/control/control
	echo "Architecture: all" >> out/$(BUILD_DIR)/control/control
	echo "Description:  Web interface" >> out/$(BUILD_DIR)/control/control
	echo "" >> out/$(BUILD_DIR)/control/control

_web-scripts:
	@if [[ "$(BUILD_DIR)" == "web-openwrt" ]]; then \
	  cp web/ipk/postinst-openwrt out/$(BUILD_DIR)/control/postinst; \
	else \
		cp web/ipk/postinst out/$(BUILD_DIR)/control/postinst; \
	fi
	chmod +x out/$(BUILD_DIR)/control/postinst

_web-ipk:
	make _web-clean

	# control.tar.gz
	make _web-control
	make _web-scripts
	cd out/$(BUILD_DIR)/control; tar czvf ../control.tar.gz .; cd ../../..

	# data.tar.gz
	mkdir -p out/$(BUILD_DIR)/data$(ROOT_DIR)
	cp -r web/share out/$(BUILD_DIR)/data$(ROOT_DIR)/share
	sed -i -E "s#__VERSION__#$(VERSION)#g" out/$(BUILD_DIR)/data$(ROOT_DIR)/share/www/w4s/index.php
	sed -i -E "s#__PLATFORM__#$(PLATFORM)#g" out/$(BUILD_DIR)/data$(ROOT_DIR)/share/www/w4s/functions.php
	sed -i -E "s#__EXTENSION__#$(EXTENSION)#g" out/$(BUILD_DIR)/data$(ROOT_DIR)/share/www/w4s/functions.php

	mkdir -p out/$(BUILD_DIR)/data$(ROOT_DIR)/etc/lighttpd/conf.d
	cp web/etc/lighttpd/conf.d/entware.conf out/$(BUILD_DIR)/data$(ROOT_DIR)/etc/lighttpd/conf.d/80-w4s.conf
	cd out/$(BUILD_DIR)/data; tar czvf ../data.tar.gz .; cd ../../..

	# ipk
	echo 2.0 > out/$(BUILD_DIR)/debian-binary
	cd out/$(BUILD_DIR); \
	tar czvf ../web4static_$(VERSION)_$(PLATFORM).$(EXTENSION) control.tar.gz data.tar.gz debian-binary; \
	cd ../..

_web-apk:
	make _web-clean
	make _web-scripts

	mkdir -p out/$(BUILD_DIR)/data$(ROOT_DIR)
	cp -r web/share/www out/$(BUILD_DIR)/data$(ROOT_DIR)/www
	sed -i -E "s#__VERSION__#$(VERSION)#g" out/$(BUILD_DIR)/data$(ROOT_DIR)/www/w4s/index.php
	sed -i -E "s#__PLATFORM__#$(PLATFORM)#g" out/$(BUILD_DIR)/data$(ROOT_DIR)/www/w4s/functions.php
	sed -i -E "s#__EXTENSION__#$(EXTENSION)#g" out/$(BUILD_DIR)/data$(ROOT_DIR)/www/w4s/functions.php

	mkdir -p out/$(BUILD_DIR)/data$(ROOT_DIR)/etc/lighttpd/conf.d
	cp web/etc/lighttpd/conf.d/openwrt.conf out/$(BUILD_DIR)/data$(ROOT_DIR)/etc/lighttpd/conf.d/80-w4s.conf

	# apk
	cd out/$(BUILD_DIR)/data; \
	tar czvf ../web4static_$(VERSION)_$(PLATFORM).$(EXTENSION) .; \
	cd ../../..

web-keenetic:
	@make \
		BUILD_DIR=web \
		PLATFORM=keenetic \
		EXTENSION=ipk \
		_web-ipk

web-openwrt:
	@make \
		BUILD_DIR=web-openwrt \
		ROOT_DIR= \
		PLATFORM=openwrt \
		EXTENSION=apk \
		_web-apk

web-openwrt-ipk:
	@make \
		BUILD_DIR=web-openwrt \
		ROOT_DIR= \
		PLATFORM=openwrt \
		EXTENSION=ipk \
		_web-ipk
