/**
 * @file
 * Provides Intersection Observer for ajaxified blocks and Views pager.
 */

(function ($, Drupal) {

  'use strict';

  Drupal.blazy = Drupal.blazy || {};
  Drupal.io = Drupal.io || {};
  var _errorTimer;

  /**
   * Intersection Observer API public methods.
   *
   * @namespace
   */
  Drupal.io.base = {
    settings: {},
    globals: function () {},
    initAjaxin: function (settings) {
      return 'Ajaxin' in window ? new Ajaxin(settings) : false;
    },

    mount: function (el, reobserve) {
      var me = this;

      // Initializes Ajaxin instance, if available.
      me.initAjaxin(me.settings);

      var init = new Bio(me.globals());

      if (reobserve) {
        init.observe(true);
      }

      me.init = init;
    },

    isError: function (el) {
      return el !== null && (!el.iohit || $.hasClass(el, this.settings.errorClass));
    },

    error: function (el) {
      var me = this;

      // This basically means, re-using bLazy to watch the intersected elements.
      $.addClass(el, me.settings.errorClass);
      el.iohit = false;

      me.reIntersecting(el);
    },

    success: function (el, status, parent) {
      var me = this;
      parent = parent || el.parentNode;

      // Marks it done.
      $.addClass(el, me.settings.successClass);
      el.iohit = true;

      // To inform themers if anything to do with the loaded contents.
      if (parent) {
        $.attr(parent, me.dataLoaded || 'data-io-loaded', status);
      }
    },

    reIntersecting: function (el) {
      var me = this;

      // Attempts to re-ajax if its content is not loaded, prevents infinite.
      if (el.iohit) {
        return;
      }

      // Mark it an explicit error, so to re-request.
      el.iohit = false;

      // DOM ready fix. This is mostly needed by the fallback bLazy.
      // Or if IO disconnect option is enabled.
      clearTimeout(_errorTimer);
      _errorTimer = setTimeout(function () {
        me.intersecting(el);
      }, 300);
    }
  };

})(dBlazy, Drupal);
