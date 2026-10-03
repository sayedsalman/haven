#!/usr/bin/env python3
"""
Haven - evaluate the trained emotion model and try your own text, in the command window.

    python evaluate_pc.py                      -> test-set scores, then interactive typing
    python evaluate_pc.py "I feel so alone"    -> score one sentence and exit
    python evaluate_pc.py --no-eval            -> skip the test-set scores, go straight to typing
    python evaluate_pc.py --model-dir D:\\x    -> use model files from another folder (default: model_out)

Needs only numpy (no TensorFlow). Run it from the same folder as train_pc.py.
Uses: model_out/model_config.json, emotion_weights.json, emotion_weights.bin, goemotions_data/test.tsv
"""
import json, os, sys, pathlib
import numpy as np
import train_pc as T            # same tokenizer + same int8 forward pass the website uses

GROUP = {'joy':'happy','amusement':'happy','excitement':'happy','love':'happy','pride':'happy','admiration':'happy','gratitude':'happy',
         'optimism':'hopeful','desire':'hopeful','approval':'hopeful','relief':'calm','caring':'calm',
         'sadness':'sad','grief':'sad','disappointment':'sad','remorse':'sad','embarrassment':'sad',
         'fear':'stressed','nervousness':'stressed','confusion':'stressed',
         'anger':'angry','annoyance':'angry','disapproval':'angry','disgust':'angry',
         'neutral':'neutral','realization':'neutral','surprise':'neutral','curiosity':'neutral'}

def load_model(mdir):
    mdir = pathlib.Path(mdir)
    need = ["model_config.json", "emotion_weights.json", "emotion_weights.bin"]
    miss = [f for f in need if not (mdir / f).exists()]
    if miss: sys.exit(f"Missing in '{mdir}': {', '.join(miss)}\nRun  python train_pc.py  first (or use --model-dir).")
    cfg = json.loads((mdir / "model_config.json").read_text(encoding="utf-8"))
    man = json.loads((mdir / "emotion_weights.json").read_text())
    return cfg, man, str(mdir / "emotion_weights.bin")

def predict(texts, cfg, man, binpath):
    ids = [T.encode(t, cfg["vocab"]) for t in texts]
    return T.numpy_forward(ids, man, binpath)

def decide(scores, thr):
    """Predicted label set: scores >= per-label threshold; if none pass, take the single best label."""
    pred = scores >= thr
    empty = ~pred.any(axis=1)
    pred[empty, scores[empty].argmax(axis=1)] = True
    return pred

def prf(y, p):
    tp = (y & p).sum(0).astype(float); fp = (~y & p).sum(0).astype(float); fn = (y & ~p).sum(0).astype(float)
    prec = np.divide(tp, tp + fp, out=np.zeros_like(tp), where=(tp + fp) > 0)
    rec = np.divide(tp, tp + fn, out=np.zeros_like(tp), where=(tp + fn) > 0)
    f1 = np.divide(2 * prec * rec, prec + rec, out=np.zeros_like(tp), where=(prec + rec) > 0)
    return prec, rec, f1, tp, fp, fn

def evaluate(cfg, man, binpath, data_dir):
    path = pathlib.Path(data_dir) / "test.tsv"
    if not path.exists(): sys.exit(f"Cannot find {path}. Run train_pc.py once (it downloads the dataset) or set GOEMOTIONS_DIR.")
    texts, labs = T.load_split(path)
    Y = np.zeros((len(texts), T.N), bool)
    for i, ls in enumerate(labs): Y[i, ls] = True
    print(f"\nScoring {len(texts)} test posts ...", flush=True)
    S = np.vstack([predict(texts[i:i + 500], cfg, man, binpath) for i in range(0, len(texts), 500)])
    thr = np.array(cfg["thresholds"]); P = decide(S, thr)

    prec, rec, f1, tp, fp, fn = prf(Y, P)
    support = Y.sum(0)
    micro_tp, micro_fp, micro_fn = tp.sum(), fp.sum(), fn.sum()
    mp = micro_tp / max(micro_tp + micro_fp, 1); mr = micro_tp / max(micro_tp + micro_fn, 1)
    micro_f1 = 2 * mp * mr / max(mp + mr, 1e-9)
    macro_f1 = f1.mean(); weighted_f1 = (f1 * support).sum() / support.sum()
    top1 = Y[np.arange(len(S)), S.argmax(1)].mean()                       # best-scored label is one of the true labels
    exact = (P == Y).all(1).mean()                                         # whole label set exactly right
    hamming = (P == Y).mean()                                              # per-label yes/no accuracy

    print("\n" + "=" * 62 + "\n MODEL SCORES (GoEmotions test set)\n" + "=" * 62)
    print(f" model version              : {cfg.get('version')}")
    print(f" macro F1                   : {macro_f1:.3f}   (every emotion counts equally - main GoEmotions metric)")
    print(f" micro F1                   : {micro_f1:.3f}   (every prediction counts equally)")
    print(f" weighted F1                : {weighted_f1:.3f}")
    print(f" micro precision / recall   : {mp:.3f} / {mr:.3f}")
    print(f" top-1 accuracy             : {top1:.3f}   (model's best label is one of the true labels)")
    print(f" exact-match accuracy       : {exact:.3f}   (all labels of a post exactly right; strict)")
    print(f" per-label accuracy         : {hamming:.3f}   (inflated: most labels are 'no' for most posts)")
    print("\n per-emotion results" + " " * 5 + "precision  recall   F1   posts")
    for i in np.argsort(-f1):
        print(f"  {T.LABELS[i]:<15} {prec[i]:>9.2f} {rec[i]:>7.2f} {f1[i]:>6.2f} {int(support[i]):>6}")
    print("=" * 62)
    (pathlib.Path(".") / "test_scores.json").write_text(json.dumps(
        {"macro_f1": round(float(macro_f1), 4), "micro_f1": round(float(micro_f1), 4), "weighted_f1": round(float(weighted_f1), 4),
         "top1_accuracy": round(float(top1), 4), "exact_match_accuracy": round(float(exact), 4),
         "per_label": {T.LABELS[i]: {"precision": round(float(prec[i]), 3), "recall": round(float(rec[i]), 3), "f1": round(float(f1[i]), 3), "support": int(support[i])} for i in range(T.N)}},
        indent=2))
    print(" saved test_scores.json")

def show(text, cfg, man, binpath):
    s = predict([text], cfg, man, binpath)[0]; thr = np.array(cfg["thresholds"])
    order = np.argsort(-s)
    pos = sum(s[i] for i in range(T.N) if cfg["labels"][i] in ('admiration','amusement','approval','caring','desire','excitement','gratitude','joy','love','optimism','pride','relief'))
    neg = sum(s[i] for i in range(T.N) if cfg["labels"][i] in ('anger','annoyance','disappointment','disapproval','disgust','embarrassment','fear','grief','nervousness','remorse','sadness'))
    sent = 0.5 + 0.5 * (pos - neg) / (pos + neg) if pos + neg > 0 else 0.5
    top = int(order[0])
    if cfg["labels"][top] == 'neutral':                                    # same rule as the website
        alt = int(order[1]); top = alt if s[alt] >= 0.35 else top
    passed = [cfg["labels"][i] for i in order if s[i] >= thr[i]]
    print(f"\n  Detected : {cfg['labels'][top]}  ({s[top]*100:.0f}%)   ->  app emotion: {GROUP[cfg['labels'][top]]}")
    print(f"  Sentiment: {sent:.2f}  (0 = negative, 1 = positive)")
    print(f"  Labels over their threshold: {', '.join(passed) if passed else 'none'}")
    print("  Top 5:")
    for i in order[:5]:
        print(f"    {cfg['labels'][i]:<14} {'#' * int(round(s[i] * 30)):<30} {s[i]*100:5.1f}%")

def main():
    args = sys.argv[1:]
    mdir = "model_out"
    if "--model-dir" in args:
        k = args.index("--model-dir"); mdir = args[k + 1]; del args[k:k + 2]
    skip = "--no-eval" in args; args = [a for a in args if a != "--no-eval"]
    cfg, man, binpath = load_model(mdir)
    if args:                                                               # one sentence from the command line
        show(" ".join(args), cfg, man, binpath); return
    if not skip: evaluate(cfg, man, binpath, os.environ.get("GOEMOTIONS_DIR", "goemotions_data"))
    print("\nType a sentence and press Enter to test it. Empty line or 'q' to quit.")
    while True:
        try: text = input("\ntext> ").strip()
        except (EOFError, KeyboardInterrupt): break
        if text.lower() in ("", "q", "quit", "exit"): break
        show(text, cfg, man, binpath)
    print("bye")

if __name__ == "__main__":
    main()
