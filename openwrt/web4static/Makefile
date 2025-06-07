include $(TOPDIR)/rules.mk

PKG_NAME:=web4static
PKG_VERSION:=$(shell cat ../../VERSION)
PKG_RELEASE:=1
PKG_LICENSE:=MIT

include $(INCLUDE_DIR)/package.mk

define Package/web4static
  TITLE:=web4static
  SECTION:=net
  CATEGORY:=Web-interface
  DEPENDS:=+php8-cgi +php8-mod-session +lighttpd +lighttpd-mod-cgi +lighttpd-mod-setenv +lighttpd-mod-rewrite +lighttpd-mod-redirect
  PKGARCH:=all
  URL:=https://github.com/spatiumstas/web4static
  VERSION:=$(PKG_VERSION)
endef

define Package/web4static/description
  Web4static web interface
endef

define Build/Prepare
	$(Build/Prepare/Default)
	cp -r ../../* $(PKG_BUILD_DIR)
	$(Build/Patch)
endef

define Build/Compile
	cd $(PKG_BUILD_DIR) && make web-openwrt
endef

define Package/web4static/install
	$(INSTALL_DIR) $(1)/{etc,www}
	$(CP) $(PKG_BUILD_DIR)/out/web-openwrt/data/{etc,www} $(1)/
endef

define Package/web4static/postinst
$(file <$(PKG_BUILD_DIR)/out/web-openwrt/control/postinst)
endef

$(eval $(call BuildPackage,web4static)) 