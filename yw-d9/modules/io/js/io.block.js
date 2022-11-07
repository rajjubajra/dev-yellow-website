/**
 * @file
 * Provides Intersection Observer for ajaxified blocks.
 */

(function ($, Drupal, drupalSettings, _doc) {

  'use strict';

  var _context = _doc;
  var _id = 'io-block';
  var _idOnce = _id;
  var _element = 'html';
  var _dataId = 'data-' + _id;
  var _dataTrigger = _dataId + '-trigger';
  var _base = Drupal.io.base || {};

  /**
   * Intersection Observer API public methods for Drupal blocks.
   *
   * @namespace
   */
  Drupal.io.block = $.extend({}, _base, {
    settings: drupalSettings.io.block || {},
    trigger: '[' + _dataTrigger + ']',
    dataLoaded: _dataId + '-loaded',
    dataTrigger: _dataTrigger,
    globals: function () {
      var me = this;
      var commons = {
        intersecting: me.intersecting.bind(me),
        success: me.success.bind(me),
        error: me.error.bind(me),
        visibleClass: 'is-iob-visible'
      };

      return $.extend({}, me.settings, commons);
    },

    ajaxSettings: function (el) {
      var uuid = $.hasAttr(el, this.dataTrigger);

      return !uuid ? false : {
        url: el.href,
        element: el,
        event: 'click touchstart',
        io: 'block'
      };
    },

    ajax: function (el, resolve, reject) {
      var me = this;
      var _settings = me.ajaxSettings(el);

      if (!_settings) {
        return false;
      }

      // @todo check out Native fetch(), if no issues with assets loading.
      var ajax = new Drupal.Ajax(null, null, _settings);
      var _ajaxOptions = ajax.options;
      var _complete = _ajaxOptions.complete;

      // Do not override Drupal.Ajax.prototype.error|success to avoid multiple
      // invocations on unrelated AJAX requests. Instead overrides its options.
      _ajaxOptions.complete = function complete(xmlhttprequest, status) {
        var sets = ajax.elementSettings;

        // Ensures our own AJAX, not anyone's else.
        if (sets && 'io' in sets && sets.io === 'block') {
          // Prevents dead errors without resolutions.
          if (status === 'error' || status === 'parsererror') {
            return reject();
          }
          else {
            // Drupal.Ajax.options.success is not always called, assumed so.
            resolve();
          }
        }

        // Makes the _super run last to catch validations.
        _complete.apply(this, arguments);
      };

      return ajax;
    },

    promise: function (el) {
      var me = this;
      return new Promise(function (resolve, reject) {

        // Bail out if AJAX is already hit, or unless an error is spit out.
        if (me.isError(el) || !el.iohit) {
          var ajax = me.ajax(el, resolve, reject);
          if (ajax) {
            // Executes the Drupal.Ajax instance.
            ajax.execute();
            el.iohit = true;
          }
        }
      });
    },

    intersecting: function (el) {
      var me = this;
      var parent = el.parentNode;

      // If hit, or success (1), and not error (-1), bail out.
      if (el.iohit && !me.isError(el)) {
        return;
      }

      return me.promise(el)
        .then(function () {
          me.success(el, 1, parent);
        })
        .catch(function () {
          me.error(el, -1, parent);
        });
    }
  });

  /**
   * IO block utility functions.
   *
   * @param {HTMLElement} elm
   *   The document HTML element.
   */
  function process(elm) {
    var me = Drupal.io.block;

    // Initializes Io instance.
    me.mount();

    // Ensures fallback is provied if anything fails.
    var doClick = function (e) {
      var el = $.hasClass(e.target, 'io__text') ? e.target.parentNode : e.target;
      e.preventDefault();

      me.reIntersecting(el);
    };

    $.on(elm, 'click', me.trigger, doClick, false);
  }

  /**
   * Auto load ajaxified blocks using the Intersection Observer.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ioBlock = {
    attach: function (context) {

      _context = $.context(context);

      $.once(process, _idOnce, _element, _context);
    }
  };

})(dBlazy, Drupal, drupalSettings, this.document);
