(function () {
  'use strict';

  var config = window.wpaAdmin || { i18n: {} };

  function ready(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function request(path, options) {
    options = options || {};
    options.headers = Object.assign(
      { 'X-WP-Nonce': config.nonce, 'Content-Type': 'application/json' },
      options.headers || {},
    );
    options.credentials = 'same-origin';
    return fetch(config.restRoot + path, options).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok) {
          var error = new Error((body && body.message) || config.i18n.failed);
          throw error;
        }
        return body;
      });
    });
  }

  function initTriggers() {
    document.querySelectorAll('[data-wpa-trigger]').forEach(function (box) {
      var card = box.closest('.wpa-trigger');
      box.addEventListener('change', function () {
        card.classList.toggle('is-on', box.checked);
      });
    });
  }

  function initFilter() {
    var input = document.querySelector('[data-wpa-filter]');
    if (!input) {
      return;
    }
    input.addEventListener('input', function () {
      var term = input.value.trim().toLowerCase();
      document
        .querySelectorAll('.wpa-abilities .wpa-ability')
        .forEach(function (row) {
          row.hidden =
            term !== '' &&
            (row.getAttribute('data-wpa-text') || '').indexOf(term) === -1;
        });
    });
  }

  function initCopy() {
    document.querySelectorAll('[data-wpa-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var field = document.querySelector(
          button.getAttribute('data-wpa-copy'),
        );
        if (!field) {
          return;
        }
        field.select();
        var done = function () {
          var label = button.textContent;
          button.textContent = config.i18n.copied;
          setTimeout(function () {
            button.textContent = label;
          }, 1500);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(field.value).then(done);
        } else {
          document.execCommand('copy');
          done();
        }
      });
    });
  }

  function showLink(status, runId, text) {
    status.textContent = text + ' ';
    var link = document.createElement('a');
    link.href = config.runUrl + runId;
    link.textContent = config.i18n.viewRun;
    status.appendChild(link);
  }

  function poll(status, runId, attempts) {
    request('runs/' + runId)
      .then(function (run) {
        var done =
          [
            'completed',
            'failed',
            'cancelled',
            'skipped',
            'awaiting_approval',
          ].indexOf(run.status) !== -1;
        if (done || attempts <= 0) {
          showLink(status, runId, run.status.replace('_', ' ') + '.');
          return;
        }
        setTimeout(function () {
          poll(status, runId, attempts - 1);
        }, 2000);
      })
      .catch(function (error) {
        status.textContent = error.message;
      });
  }

  function initRun() {
    var button = document.querySelector('[data-wpa-run]');
    var status = document.querySelector('[data-wpa-run-status]');
    if (!button || !status) {
      return;
    }
    button.addEventListener('click', function () {
      button.disabled = true;
      status.textContent = config.i18n.running;
      request('agents/' + button.getAttribute('data-wpa-run') + '/run', {
        method: 'POST',
        body: JSON.stringify({}),
      })
        .then(function (body) {
          showLink(status, body.run_id, config.i18n.queued);
          poll(status, body.run_id, 30);
        })
        .catch(function (error) {
          status.textContent = error.message || config.i18n.failed;
        })
        .then(function () {
          button.disabled = false;
        });
    });
  }

  ready(function () {
    initTriggers();
    initFilter();
    initCopy();
    initRun();
  });
})();
