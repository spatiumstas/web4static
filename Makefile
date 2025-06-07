SHELL := /bin/bash
VERSION := $(shell cat VERSION)
ROOT_DIR := /opt

include web.mk

clean:
	rm -rf out/openwrt
	rm -rf out/web
