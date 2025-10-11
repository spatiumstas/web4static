SHELL := /bin/bash
VERSION := $(shell cat VERSION)
ROOT_DIR := /opt
.PHONY: clean _web-clean _web-control _web-scripts _web-ipk web-kn

clean:
	rm -rf out/web

_web-clean:
	rm -rf out/$(BUILD_DIR)
	mkdir -p out/$(BUILD_DIR)/control
	mkdir -p out/$(BUILD_DIR)/data

_web-control:
	echo "Package: web4static" > out/$(BUILD_DIR)/control/control
	echo "Version: $(VERSION)" >> out/$(BUILD_DIR)/control/control
	echo "Depends: curl, ip, php8-cgi, php8-mod-session, lighttpd, lighttpd-mod-cgi, lighttpd-mod-setenv, lighttpd-mod-rewrite" >> out/$(BUILD_DIR)/control/control
	echo "License: MIT" >> out/$(BUILD_DIR)/control/control
	echo "Section: net" >> out/$(BUILD_DIR)/control/control
	echo "URL: https://github.com/spatiumstas/web4static" >> out/$(BUILD_DIR)/control/control
	echo "Architecture: all" >> out/$(BUILD_DIR)/control/control
	echo "Description: Web interface" >> out/$(BUILD_DIR)/control/control
	echo "" >> out/$(BUILD_DIR)/control/control

_web-scripts:
	cp web/ipk/postinst out/$(BUILD_DIR)/control/postinst;
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

	mkdir -p out/$(BUILD_DIR)/data$(ROOT_DIR)/etc/lighttpd/conf.d
	cp web/etc/lighttpd/conf.d/80-w4s.conf out/$(BUILD_DIR)/data$(ROOT_DIR)/etc/lighttpd/conf.d/80-w4s.conf

	W4S_DIR=out/$(BUILD_DIR)/data$(ROOT_DIR)/share/www/w4s; \
	bash scripts/fingerprint.sh "$$W4S_DIR"
	cd out/$(BUILD_DIR)/data; tar czvf ../data.tar.gz .; cd ../../..

	# ipk
	echo 2.0 > out/$(BUILD_DIR)/debian-binary
	cd out/$(BUILD_DIR); \
	tar czvf ../web4static_$(VERSION)_kn.ipk control.tar.gz data.tar.gz debian-binary; \
	cd ../..

web-kn:
	@make \
		BUILD_DIR=web \
		_web-ipk