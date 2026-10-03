#!/usr/bin/env python3
"""
Haven - train the GoEmotions emotion model on your own PC and export the files the website uses.

    pip install tensorflow numpy scikit-learn
    python train_pc.py

What it does
  1. Downloads GoEmotions (train/dev/test .tsv + emotions.txt) from GitHub into ./goemotions_data
     (or reads them from there if already present - you can also copy the files in by hand).
  2. Trains a small multi-label text model (28 emotions).
  3. Tunes one decision threshold per emotion on the validation set and reports test F1.
  4. Writes ./model_out/ :
        model_config.json      vocab, labels, thresholds, metrics        -> assets/ml/
        emotion_weights.json   int8 scales + biases                      -> assets/ml/
        emotion_weights.bin    int8 weights                              -> assets/ml/
        emotion.tflite         (optional, for the course/demo)           -> assets/ml/
  5. Checks the exported int8 weights reproduce the Keras model, then tells you what to upload.

Architecture is FIXED because assets/js/haven-emotion.js and includes/EmotionML.php implement it:
  Embedding(20000, 96) -> [Conv1D(128, k=3, same, relu) -> GlobalMaxPool] ++ [masked mean of embeddings]
  -> Dense(256, relu) -> Dense(28, sigmoid).        Do not change sizes unless you change JS + PHP too.

Options (environment variables):  EPOCHS=15  BATCH=128  GOEMOTIONS_DIR=<folder with the tsv files>  SKIP_TFLITE=1
"""
import json, os, re, sys, pathlib, urllib.request
from collections import Counter
import numpy as np

MAX_LEN, VOCAB_SIZE, EMB_DIM, CONV_FILTERS, KERNEL, HIDDEN = 64, 20000, 96, 128, 3, 256
EPOCHS = int(os.environ.get("EPOCHS", 15)); BATCH = int(os.environ.get("BATCH", 128))
MODEL_VERSION = os.environ.get("MODEL_VERSION", "goemotions-tflite-v1")   # keep in sync with EmotionML::MODEL_VERSION
LABELS = ["admiration","amusement","anger","annoyance","approval","caring","confusion","curiosity","desire",
          "disappointment","disapproval","disgust","embarrassment","excitement","fear","gratitude","grief","joy",
          "love","nervousness","optimism","pride","realization","relief","remorse","sadness","surprise","neutral"]
N = len(LABELS)
OUT = pathlib.Path("model_out")
DATA_URL = "https://raw.githubusercontent.com/google-research/google-research/master/goemotions/data/"


# ----------------------------------------------------------------- data
def get_data_dir():
    d = pathlib.Path(os.environ.get("GOEMOTIONS_DIR", "goemotions_data")); d.mkdir(exist_ok=True)
    for f in ("train.tsv", "dev.tsv", "test.tsv", "emotions.txt"):
        if not (d / f).exists():
            print("downloading", f, "...")
            try:
                urllib.request.urlretrieve(DATA_URL + f, d / f)
            except Exception as e:
                sys.exit(f"Could not download {f} ({e}).\nDownload train.tsv, dev.tsv, test.tsv and emotions.txt from\n"
                         f"  {DATA_URL}\nand put them in the folder '{d}' (open each link, Save As).")
    return d

def load_split(path):
    texts, labs = [], []
    for line in pathlib.Path(path).read_text(encoding="utf-8").splitlines():
        p = line.split("\t")
        if len(p) < 2 or not p[1].strip(): continue
        texts.append(p[0]); labs.append([int(x) for x in p[1].split(",")])
    return texts, labs

def load_goemotions():
    d = get_data_dir()
    names = (d / "emotions.txt").read_text(encoding="utf-8").split()
    if len(names) == 27: names.append("neutral")
    if names != LABELS:
        sys.exit("emotions.txt label order differs from LABELS - the website code would map labels wrongly.")
    return {s: load_split(d / f) for s, f in (("train", "train.tsv"), ("val", "dev.tsv"), ("test", "test.tsv"))}


# ----------------------------------------------------------------- text -> ids  (MUST match haven-emotion.js / EmotionML.php)
def tokenize(t): return re.findall(r"[a-z0-9']+", t.lower())

def build_vocab(texts):
    counter = Counter(w for t in texts for w in tokenize(t))
    return {w: i + 2 for i, (w, _) in enumerate(counter.most_common(VOCAB_SIZE - 2))}   # 0=pad 1=unknown

def encode(t, vocab):
    ids = [vocab.get(w, 1) for w in tokenize(t)[:MAX_LEN]]
    return ids + [0] * (MAX_LEN - len(ids))

def to_xy(split, vocab):
    texts, labs = split
    X = np.array([encode(t, vocab) for t in texts], dtype=np.int32)
    Y = np.zeros((len(X), N), np.float32)
    for i, ls in enumerate(labs): Y[i, ls] = 1
    return X, Y


# ----------------------------------------------------------------- export (numpy only; no TensorFlow needed)
def _q_rows(w):
    """float [out, ...] -> int8 + one scale per output row (symmetric)."""
    flat = w.reshape(w.shape[0], -1)
    scale = np.maximum(np.abs(flat).max(axis=1), 1e-8) / 127.0
    q = np.clip(np.round(flat / scale[:, None]), -127, 127).astype(np.int8)
    return q, scale.astype(np.float32)

def export_weights(emb, conv_k, conv_b, fc1_k, fc1_b, fc2_k, fc2_b, outdir):
    """
    Keras layouts in:  emb [V,96]; conv_k [3,96,128]; fc1_k [224,256]; fc2_k [256,28]
    Website layouts out: conv [128][3][96]; fc1 [256][224]; fc2 [28][256]  (int8, per-output-channel scale)
    """
    assert emb.shape == (VOCAB_SIZE, EMB_DIM) and conv_k.shape == (KERNEL, EMB_DIM, CONV_FILTERS)
    assert fc1_k.shape == (CONV_FILTERS + EMB_DIM, HIDDEN) and fc2_k.shape == (HIDDEN, N)
    es = max(float(np.abs(emb).max()), 1e-8) / 127.0                       # embedding: one scale for the whole table
    emb_q = np.clip(np.round(emb / es), -127, 127).astype(np.int8)
    conv_q, conv_s = _q_rows(np.transpose(conv_k, (2, 0, 1)))              # -> [128,3,96]
    fc1_q, fc1_s = _q_rows(fc1_k.T)                                        # -> [256,224]
    fc2_q, fc2_s = _q_rows(fc2_k.T)                                        # -> [28,256]
    blobs, off, man = [], 0, {"format": "haven-int8-v1"}
    for key, q, s, shape in (("emb", emb_q, [es], [VOCAB_SIZE, EMB_DIM]),
                             ("conv", conv_q, conv_s, [CONV_FILTERS, 1, KERNEL, EMB_DIM]),
                             ("fc1", fc1_q, fc1_s, [HIDDEN, CONV_FILTERS + EMB_DIM]),
                             ("fc2", fc2_q, fc2_s, [N, HIDDEN])):
        b = np.ascontiguousarray(q).tobytes()
        man[key] = {"offset": off, "length": len(b), "shape": shape, "scale": [float(x) for x in s]}
        blobs.append(b); off += len(b)
    man["bias"] = {"conv": [float(x) for x in conv_b], "fc1": [float(x) for x in fc1_b], "fc2": [float(x) for x in fc2_b]}
    outdir = pathlib.Path(outdir); outdir.mkdir(exist_ok=True)
    (outdir / "emotion_weights.bin").write_bytes(b"".join(blobs))
    (outdir / "emotion_weights.json").write_text(json.dumps(man, separators=(",", ":")))
    return man

def numpy_forward(ids, man, binpath):
    """Reference forward pass of the EXPORTED int8 model (same maths as the JS/PHP engines)."""
    raw = np.fromfile(binpath, dtype=np.int8)
    def blob(k, shape): m = man[k]; return raw[m["offset"]:m["offset"] + m["length"]].astype(np.float32).reshape(shape)
    emb = blob("emb", (VOCAB_SIZE, EMB_DIM)) * man["emb"]["scale"][0]
    conv = blob("conv", (CONV_FILTERS, KERNEL, EMB_DIM)) * np.array(man["conv"]["scale"], np.float32)[:, None, None]
    fc1 = blob("fc1", (HIDDEN, CONV_FILTERS + EMB_DIM)) * np.array(man["fc1"]["scale"], np.float32)[:, None]
    fc2 = blob("fc2", (N, HIDDEN)) * np.array(man["fc2"]["scale"], np.float32)[:, None]
    b = {k: np.array(v, np.float32) for k, v in man["bias"].items()}
    out = []
    for row in ids:
        row = np.asarray(row); E = emb[row]; m = (row != 0)[:, None]
        mean = (E * m).sum(0) / max(m.sum(), 1)
        P = np.pad(E, ((1, 1), (0, 0))); c = np.zeros((len(row), CONV_FILTERS), np.float32)
        for k in range(KERNEL): c += P[k:k + len(row)] @ conv[:, k, :].T
        c = np.maximum(c + b["conv"], 0).max(0)
        h = np.maximum(fc1 @ np.concatenate([c, mean]) + b["fc1"], 0)
        out.append(1 / (1 + np.exp(-(fc2 @ h + b["fc2"]))))
    return np.array(out)


# ----------------------------------------------------------------- training
def main():
    import tensorflow as tf
    from sklearn.metrics import f1_score
    tf.keras.utils.set_random_seed(42)
    OUT.mkdir(exist_ok=True)

    ds = load_goemotions()
    vocab = build_vocab(ds["train"][0])
    Xtr, Ytr = to_xy(ds["train"], vocab); Xva, Yva = to_xy(ds["val"], vocab); Xte, Yte = to_xy(ds["test"], vocab)
    print(f"train/val/test: {len(Xtr)} / {len(Xva)} / {len(Xte)}   vocab={len(vocab)+2}")

    class MaskedMean(tf.keras.layers.Layer):
        """Average of word embeddings ignoring padding."""
        def call(self, inputs):
            emb, ids = inputs
            m = tf.cast(tf.not_equal(ids, 0), emb.dtype)[..., None]
            return tf.reduce_sum(emb * m, axis=1) / tf.maximum(tf.reduce_sum(m, axis=1), 1.0)
        def compute_output_shape(self, shapes): return (shapes[0][0], shapes[0][2])

    def build(batch_size=None):
        inp = tf.keras.Input(shape=(MAX_LEN,), batch_size=batch_size, dtype="int32", name="ids")
        x = tf.keras.layers.Embedding(VOCAB_SIZE, EMB_DIM, name="emb")(inp)
        conv = tf.keras.layers.Conv1D(CONV_FILTERS, KERNEL, padding="same", activation="relu", name="conv")(x)
        conv = tf.keras.layers.GlobalMaxPooling1D()(conv)
        mean = MaskedMean()([x, inp])
        h = tf.keras.layers.Concatenate()([conv, mean])
        h = tf.keras.layers.Dense(HIDDEN, activation="relu", name="fc1")(h)
        h = tf.keras.layers.Dropout(0.4)(h)
        out = tf.keras.layers.Dense(N, activation="sigmoid", name="fc2")(h)
        return tf.keras.Model(inp, out)

    model = build()
    model.compile(optimizer=tf.keras.optimizers.Adam(1e-3), loss="binary_crossentropy")
    model.fit(Xtr, Ytr, validation_data=(Xva, Yva), epochs=EPOCHS, batch_size=BATCH,
              callbacks=[tf.keras.callbacks.EarlyStopping(patience=2, restore_best_weights=True)])

    # per-label thresholds tuned on validation data
    pva = model.predict(Xva, batch_size=512, verbose=0)
    grid = np.arange(0.05, 0.9, 0.01); thr = []
    for c in range(N):
        if Yva[:, c].sum() == 0: thr.append(0.5); continue
        f1s = [f1_score(Yva[:, c], pva[:, c] >= t, zero_division=0) for t in grid]
        thr.append(round(float(grid[int(np.argmax(f1s))]), 2))
    pte = model.predict(Xte, batch_size=512, verbose=0)
    pred = (pte >= np.array(thr)).astype(int)
    macro = f1_score(Yte, pred, average="macro", zero_division=0)
    micro = f1_score(Yte, pred, average="micro", zero_division=0)
    print(f"\nTEST macro-F1={macro:.3f}  micro-F1={micro:.3f}")

    # export int8 weights for the website
    emb = model.get_layer("emb").get_weights()[0]
    ck, cb = model.get_layer("conv").get_weights()
    f1k, f1b = model.get_layer("fc1").get_weights()
    f2k, f2b = model.get_layer("fc2").get_weights()
    man = export_weights(emb, ck, cb, f1k, f1b, f2k, f2b, OUT)
    got = numpy_forward(Xte[:300], man, OUT / "emotion_weights.bin")
    diff = float(np.abs(got - pte[:300]).max())
    print(f"max |exported int8 model - Keras| on 300 test posts: {diff:.4f}  (expect < 0.05)")
    if diff > 0.1: print("WARNING: large difference - do not upload these files, something is wrong.")

    samples = ["I am so happy and grateful for my friends today!", "I feel so lonely and I can't stop crying.",
               "I'm terrified about my exam tomorrow", "this makes me really angry, it's so unfair"]
    for s in samples:
        p = numpy_forward([encode(s, vocab)], man, OUT / "emotion_weights.bin")[0]
        print(f"  {s!r} -> {LABELS[int(p.argmax())]} ({p.max():.2f})")

    (OUT / "model_config.json").write_text(json.dumps({
        "version": MODEL_VERSION, "max_len": MAX_LEN, "labels": LABELS, "thresholds": thr, "vocab": vocab,
        "selftest": [{"text": s, "ids": encode(s, vocab)} for s in samples],
        "metrics": {"test_macro_f1": round(macro, 4), "test_micro_f1": round(micro, 4)},
    }, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")

    # optional: a real .tflite file (not needed by the website, handy for the course report)
    if not os.environ.get("SKIP_TFLITE"):
        try:
            infer = build(batch_size=1); infer.set_weights(model.get_weights())
            conv = tf.lite.TFLiteConverter.from_keras_model(infer)
            conv.optimizations = [tf.lite.Optimize.DEFAULT]
            (OUT / "emotion.tflite").write_bytes(conv.convert())
            print("emotion.tflite written:", (OUT / "emotion.tflite").stat().st_size // 1024, "KB")
        except Exception as e:
            print("TFLite export skipped (website does not need it):", e)

    print("\nDONE. Upload these files from", OUT.resolve(), "to  <site>/assets/ml/ :")
    print("   model_config.json   emotion_weights.json   emotion_weights.bin")
    print("Then hard-refresh ml-demo.php. If labels/sizes changed, also update EmotionML.php + haven-emotion.js.")

if __name__ == "__main__":
    main()
