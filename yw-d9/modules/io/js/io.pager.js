/**
 * @file
 * Provides AJAX append command for both automatic or manual pagers.
 */

(function ($, Drupal, drupalSettings, _win, _doc) {

  'use strict';

  drupalSettings.io = drupalSettings.io || {};

  var _context = _doc;
  var _id = 'io-pager';
  var _idOnce = _id;
  var _dataId = 'data-' + _id;
  var _dataTrigger = _dataId + '-trigger';
  var _dataView = 'data-io-view';
  var _isDone = _idOnce + '-done';
  var _autoloadId = 'pager--io--autoload';
  var _element = '.' + _autoloadId;
  var _idAjaxWraper = 'ajax-pager';
  var _idVef = 'exposed-form';
  var _base = Drupal.io.base || {};
  var elWrapper = null;

  // Provides the IO append command.
  Drupal.AjaxCommands.prototype.ioAppend = function (ajax, response) {
    var me = Drupal.io.pager;
    var opts = response.settings || {};
    var contentSelector = opts.contentSelector || (opts.style === 'table' ? '.views-table tbody' : '[' + _dataId + ']');
    var method = opts.method || 'append';
    var selPager = opts.pagerSelector || '.pager--io';
    var viewDomId = opts.view_dom_id;
    var view = Drupal.views.instances['views_dom_id:' + viewDomId];
    var settings = ajax.settings || drupalSettings;
    var elNewContent = _doc.createElement('div');
    var selView = '.js-view-dom-id-' + viewDomId;
    var $exposedForm = !$.isUnd(view) && view.$exposed_form.length ? view.$exposed_form : null;

    elNewContent.innerHTML = response.data.trim();
    elWrapper = typeof view === 'undefined' ? $.find(_doc, selView) : view.$view[0];

    if (!$.isElm(elWrapper)) {
      return;
    }

    // If removing content from the wrapper, detach behaviors first.
    Drupal.detachBehaviors(elWrapper, settings);

    // Update the pager.
    var oldPager = $.find(elWrapper, selPager);
    var newPager = $.find(elNewContent, selPager);
    if ($.isElm(oldPager)) {
      oldPager.innerHTML = $.isElm(newPager) ? newPager.innerHTML : '';
    }

    // Add the new content to the page.
    var oldCn = $.find(elWrapper, contentSelector);
    var newCn = $.find(elNewContent, contentSelector);
    if ($.isElm(oldCn) && $.isElm(newCn)) {
      $[method](oldCn, newCn.innerHTML);
    }

    // Remove once so that the exposed form and pager are processed on attach.
    $.once.removeSafely(_idAjaxWraper, selView, _doc);
    if ($exposedForm) {
      $.once.removeSafely(_idVef, $exposedForm, _doc);
    }

    // @todo remove when every uses core/once at min D9.2.
    // var grid = $.find(elWrapper, '.blazy--grid');
    // if ($.isElm(grid)) {
    // $.removeClass(grid, 'is-b-flex--on'); // is-b-masonry--on
    // }

    // Attach all JavaScript behaviors to the newly loaded content.
    Drupal.attachBehaviors(elWrapper, settings);

    // DOM ready fix.
    setTimeout(function () {
      checkAjaxin();

      var link = $.find(elWrapper, me.trigger);

      // The IO pager is all done if no link is found.
      if (!$.isElm(link)) {
        $.addClass(oldPager, _isDone);
      }
    }, 101);

  };

  /**
   * Intersection Observer API public methods for Views pagers.
   *
   * @namespace
   */
  Drupal.io.pager = $.extend({}, _base, {
    el: null,
    $trigger: null,
    settings: drupalSettings.io.pager || {},
    dataTrigger: _dataTrigger,
    trigger: '[' + _dataTrigger + ']',
    globals: function () {
      var me = this;
      var commons = {
        intersecting: me.intersecting.bind(me),
        success: me.success.bind(me),
        error: me.error.bind(me),
        visibleClass: 'is-iop-visible'
      };

      return $.extend({}, me.settings, commons);
    },

    // For static sites.
    loadStatic: function (el, path) {
      var $view = $.closest(el, '[' + _dataView + ']');
      var matches = /(js-view-dom-id-\w+)/.exec($.attr($view, 'class'));
      var domId = matches[1].replace('js-view-dom-id-', '');
      var style = $.attr($view, _dataView);

      // @todo update this, it is a blind guess without static sites for now.
      /* eslint-disable indent */
      fetch(path, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'text/html; charset=UTF-8'
        }
      }).then(checkStatus).then(function (response) {
        return response.text();
      }).then(function (body) {
        var elDiv = _doc.createElement('div');

        elDiv.innerHTML = body.trim();

        var elNewView = $.find(elDiv, '[' + _dataView + ']:first-child');
        var classes = $.attr($view, 'class');

        if (elNewView.length) {
          $.attr(elNewView, 'class', classes);

          var response = {
            command: 'ioAppend',
            data: elNewView.outerHTML,
            io: 'pager',
            settings: {
              view_dom_id: domId,
              style: style
            }
          };
          Drupal.AjaxCommands.prototype.ioAppend({}, response);
        }
      });
    },

    loadOrClick: function (el) {
      var me = this;
      var path = el.href;

      // Static sites ala Tome has `/page/` in url.
      if (path && path.indexOf('/page/') >= 0) {
        me.loadStatic(el, path);
      }
      else {
        el.click();
      }
    },

    intersecting: function (el) {
      var me = this;

      // Allows Blazy to change it into IOEntry for better usages.
      el = el.target || el;

      // Bail out if AJAX is already requested, or successful.
      if (el && (me.isError(el) || !el.iohit)) {
        me.loadOrClick(el);

        el.iohit = false;
      }
    }
  });

  function checkStatus(response) {
    if (response.status >= 200 && response.status < 300) {
      return response;
    }
    else {
      var error = new Error(response.statusText);
      error.response = response;
      throw error;
    }
  }

  function checkAjaxin() {
    var cn = $.find(_doc, '.ajaxin-wrapper--fs');
    $.remove(cn);
  }

  /**
   * IO automatic pager utility functions.
   *
   * @param {HTMLElement} elm
   *   The IO pager HTML element.
   */
  function process(elm) {
    var me = Drupal.io.pager;
    var link = $.find(elm, me.trigger);

    me.el = elm;
    me.$trigger = link;
    me.$trigger.iohit = false;

    // Initializes Io pager instance.
    me.mount(true);
  }

  /**
   * Auloload ajaxified views using the IO API, or the old scroll event, bLazy.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ioPager = {
    attach: function (context) {

      _context = $.context(context);

      $.once(process, _idOnce, _element, _context);
    },
    detach: function (context, settings, trigger) {
      var me = Drupal.io.pager;

      if (trigger === 'unload') {
        if (me.$trigger) {
          me.$trigger.iohit = false;
        }
        $.once.removeSafely(_idOnce, _element, _context);
      }
    }
  };

})(dBlazy, Drupal, drupalSettings, this, this.document);
