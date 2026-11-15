/* Haven – on-device emotion detection (GoEmotions, 28 labels).
 *
 * The model is the one trained by ml/train_goemotions_tflite.py and exported to emotion.tflite.
 * Its int8 weights are extracted into assets/ml/emotion_weights.{json,bin} (ml/export_weights.py)
 * and executed here in plain JavaScript, so there is NO dependency on tfjs / tfjs-tflite / WebAssembly
 * (the old tfjs-tflite runtime cannot load models converted by current TensorFlow versions).
 *
 * The draft text never leaves the browser; only the 28 scores are sent with the post.
 *
 * Usage: <textarea data-emotion-ml data-emotion-chip="someElementId"></textarea>
 *        (chip optional; a hidden <input name="ml_scores"> is added to the form automatically)
 */
(function () {
  'use strict';
  var SCRIPT = document.currentScript && document.currentScript.src;
  var BASE = new URL('../ml/', SCRIPT || location.href).href;
  var VER = '?v=3';

  // Keep in sync with includes/EmotionML.php
  var GROUP = {
    joy:'happy', amusement:'happy', excitement:'happy', love:'happy', pride:'happy', admiration:'happy', gratitude:'happy',
    optimism:'hopeful', desire:'hopeful', approval:'hopeful',
    relief:'calm', caring:'calm',
    sadness:'sad', grief:'sad', disappointment:'sad', remorse:'sad', embarrassment:'sad',
    fear:'stressed', nervousness:'stressed', confusion:'stressed',
    anger:'angry', annoyance:'angry', disapproval:'angry', disgust:'angry',
    neutral:'neutral', realization:'neutral', surprise:'neutral', curiosity:'neutral'
  };
  var EMOJI = { happy:'😊', sad:'😔', stressed:'😰', angry:'😡', calm:'😌', hopeful:'🌟', neutral:'😐' };

  var cfg = null, vocab = null, W = null, readyPromise = null;
  var EMB = 96, CONV = 128, KW = 3, H1 = 256, NOUT = 28;

  function tokenize(text) { return String(text).toLowerCase().match(/[a-z0-9']+/g) || []; }
  function encode(text) {
    var ids = tokenize(text).slice(0, cfg.max_len).map(function (t) { return vocab.has(t) ? vocab.get(t) : 1; });
    while (ids.length < cfg.max_len) ids.push(0);
    return ids;
  }

  function fetchJSON(url) {
    return fetch(url).then(function (r) { if (!r.ok) throw new Error(url + ' -> HTTP ' + r.status); return r.json(); });
  }
  function fetchBin(url) {
    return fetch(url).then(function (r) { if (!r.ok) throw new Error(url + ' -> HTTP ' + r.status); return r.arrayBuffer(); });
  }

  // int8 [out][in] weights * per-output-channel scale -> Float32Array
  function dequant(i8, scales, outDim) {
    var per = i8.length / outDim, out = new Float32Array(i8.length), o, j;
    for (o = 0; o < outDim; o++) {
      var s = scales.length === 1 ? scales[0] : scales[o];
      for (j = 0; j < per; j++) out[o * per + j] = i8[o * per + j] * s;
    }
    return out;
  }

  function init() {
    if (readyPromise) return readyPromise;
    readyPromise = (async function () {
      var res = await Promise.all([
        fetchJSON(BASE + 'model_config.json' + VER),
        fetchJSON(BASE + 'emotion_weights.json' + VER),
        fetchBin(BASE + 'emotion_weights.bin' + VER)
      ]);
      cfg = res[0]; var man = res[1], buf = res[2];
      vocab = new Map(Object.entries(cfg.vocab));
      function blob(k) { return new Int8Array(buf, man[k].offset, man[k].length); }
      W = {
        emb: blob('emb'), embScale: man.emb.scale[0],
        conv: dequant(blob('conv'), man.conv.scale, CONV),   // [128][3][96]
        fc1: dequant(blob('fc1'), man.fc1.scale, H1),        // [256][224]
        fc2: dequant(blob('fc2'), man.fc2.scale, NOUT),      // [28][256]
        bConv: Float32Array.from(man.bias.conv),
        bFc1: Float32Array.from(man.bias.fc1),
        bFc2: Float32Array.from(man.bias.fc2)
      };
      // Dev check: JS tokenizer must reproduce the Python token ids.
      (cfg.selftest || []).forEach(function (t) {
        if (JSON.stringify(encode(t.text)) !== JSON.stringify(t.ids)) console.warn('[Haven ML] tokenizer mismatch for:', t.text);
      });
      return true;
    })();
    readyPromise.catch(function (e) {
      window.HavenEmotionFailed = true;
      console.warn('[Haven ML] unavailable:', e.message);
    });
    return readyPromise;
  }

  // Forward pass: embedding -> [Conv1D(3,same)+ReLU -> global max] ++ [masked mean] -> Dense(256,ReLU) -> Dense(28) -> sigmoid
  function run(ids) {
    var L = ids.length, t, c, k, o, i;
    var emb = new Float32Array(L * EMB), n = 0;
    var mean = new Float32Array(EMB);
    for (t = 0; t < L; t++) {
      var row = ids[t] * EMB, m = ids[t] !== 0;
      if (m) n++;
      for (c = 0; c < EMB; c++) {
        var v = W.emb[row + c] * W.embScale;
        emb[t * EMB + c] = v;
        if (m) mean[c] += v;
      }
    }
    var denom = Math.max(n, 1);
    for (c = 0; c < EMB; c++) mean[c] /= denom;

    var x = new Float32Array(CONV + EMB);
    for (o = 0; o < CONV; o++) {
      var best = -Infinity, wbase = o * KW * EMB;
      for (t = 0; t < L; t++) {
        var s = W.bConv[o];
        for (k = 0; k < KW; k++) {
          var tt = t + k - 1;
          if (tt < 0 || tt >= L) continue;                    // 'same' zero padding
          var eb = tt * EMB, wb = wbase + k * EMB;
          for (c = 0; c < EMB; c++) s += W.conv[wb + c] * emb[eb + c];
        }
        if (s < 0) s = 0;                                       // ReLU
        if (s > best) best = s;
      }
      x[o] = best;
    }
    for (c = 0; c < EMB; c++) x[CONV + c] = mean[c];

    var h = new Float32Array(H1);
    for (o = 0; o < H1; o++) {
      var a = W.bFc1[o], wo = o * (CONV + EMB);
      for (i = 0; i < CONV + EMB; i++) a += W.fc1[wo + i] * x[i];
      h[o] = a > 0 ? a : 0;
    }
    var out = new Array(NOUT);
    for (o = 0; o < NOUT; o++) {
      var z = W.bFc2[o], w2 = o * H1;
      for (i = 0; i < H1; i++) z += W.fc2[w2 + i] * h[i];
      out[o] = 1 / (1 + Math.exp(-z));
    }
    return out;
  }

  function predictSync(text) { return run(encode(text)); }
  async function predict(text) { await init(); return predictSync(text); }

  // Same rule as EmotionML::summarize() on the server.
  function summarize(scores) {
    var labels = cfg.labels, top = 0, i;
    for (i = 1; i < scores.length; i++) if (scores[i] > scores[top]) top = i;
    if (labels[top] === 'neutral') {
      var alt = -1;
      for (i = 0; i < scores.length; i++) if (labels[i] !== 'neutral' && (alt < 0 || scores[i] > scores[alt])) alt = i;
      if (alt >= 0 && scores[alt] >= 0.35) top = alt;
    }
    return { label: labels[top], score: scores[top], emotion: GROUP[labels[top]] || 'neutral' };
  }

  function attach(textarea) {
    var form = textarea.form;
    var chip = document.getElementById(textarea.dataset.emotionChip || '');
    if (!chip) { chip = document.createElement('div'); chip.className = 'small text-muted mt-1'; textarea.insertAdjacentElement('afterend', chip); }
    var hidden = null;
    if (form) {
      hidden = form.querySelector('input[name="ml_scores"]');
      if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = 'ml_scores'; form.appendChild(hidden); }
    }
    function readyNow() { return !!(cfg && W); }
    function scoreNow() {                                   // synchronous: the model is tiny
      var text = textarea.value.trim();
      if (text.length < 4 || !readyNow()) return null;
      try { return predictSync(text); } catch (e) { return null; }
    }
    function show(scores) {
      var s = summarize(scores);
      if (hidden) hidden.value = JSON.stringify(scores.map(function (x) { return +x.toFixed(4); }));
      chip.textContent = (EMOJI[s.emotion] || '😐') + ' ' + s.label + ' (' + Math.round(s.score * 100) + '%)';
      chip.style.opacity = '1';
    }
    var timer = null;
    function update() {
      var text = textarea.value.trim();
      if (text.length < 4) { if (hidden) hidden.value = ''; chip.textContent = '😐 Detecting emotion...'; return; }
      init().then(function () {
        if (textarea.value.trim() !== text) return;           // a newer keystroke superseded this one
        var sc = scoreNow(); if (sc) show(sc);
      }).catch(function () { if (hidden) hidden.value = ''; /* server runs the same model as a fallback */ });
    }
    textarea.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(update, 200); });
    if (form) {
      // Guarantee the submitted scores always match the final text (even if the user posts instantly).
      form.addEventListener('formdata', function (e) {
        var sc = scoreNow();
        if (sc) { try { e.formData.set('ml_scores', JSON.stringify(sc.map(function (x) { return +x.toFixed(4); }))); } catch (_) {} }
      });
      form.addEventListener('submit', function () { var sc = scoreNow(); if (sc && hidden) hidden.value = JSON.stringify(sc.map(function (x) { return +x.toFixed(4); })); }, true);
      form.addEventListener('reset', function () { if (hidden) hidden.value = ''; chip.textContent = '😐 Detecting emotion...'; });
    }
    textarea.addEventListener('focus', init, { once: true });   // warm up the model when the user starts writing
    init();
  }

  window.HavenEmotion = { init: init, predict: predict, predictSync: predictSync, summarize: summarize, attach: attach,
    config: function () { return cfg; }, emoji: EMOJI };

  function boot() { document.querySelectorAll('textarea[data-emotion-ml]').forEach(attach); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
