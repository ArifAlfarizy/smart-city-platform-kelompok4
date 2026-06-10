"""
train_models.py
---------------
Training pipeline untuk 3 model ML Smart City:
  Model 1 — Traffic Density Predictor  (Random Forest Regressor)
  Model 2 — Air Quality Classifier      (Gradient Boosting Classifier)
  Model 3 — Anomaly Detector            (Isolation Forest)

Jalankan setelah preprocessing selesai:
  python train_models.py

Output: models/smartcity_models.pkl
"""

import os
import joblib
import numpy as np
import pandas as pd

from sklearn.ensemble import (
    RandomForestRegressor,
    GradientBoostingClassifier,
    IsolationForest,
)
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.model_selection import train_test_split, cross_val_score, StratifiedKFold, KFold
from sklearn.metrics import (
    r2_score,
    mean_absolute_error,
    accuracy_score,
    classification_report,
    confusion_matrix,
)

DATA_DIR   = os.path.join(os.path.dirname(__file__), "data")
MODELS_DIR = os.path.join(os.path.dirname(__file__), "models")
RANDOM_SEED = 42

np.random.seed(RANDOM_SEED)
os.makedirs(MODELS_DIR, exist_ok=True)


# MODEL 1 — TRAFFIC DENSITY PREDICTOR
def train_traffic_model() -> dict:
    """
    Task      : Regression
    Algoritma : Random Forest Regressor
    Target    : vehicle_density
    Fitur     : hour, day_of_week, weather_code, prev_density, location_enc
    """
    print("\n" + "=" * 55)
    print("MODEL 1 — Traffic Density Predictor")
    print("=" * 55)

    # Load dataset
    path = os.path.join(DATA_DIR, "traffic_history.csv")
    df = pd.read_csv(path)
    print(f"Dataset loaded: {df.shape}")

    TRAFFIC_FEATS = ["hour", "day_of_week", "weather_code", "prev_density", "location_enc"]
    TARGET        = "vehicle_density"

    df = df.dropna(subset=TRAFFIC_FEATS + [TARGET])
    X  = df[TRAFFIC_FEATS].values
    y  = df[TARGET].values

    # Train/test split
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=RANDOM_SEED
    )

    # Scaling
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s  = scaler.transform(X_test)

    # Train model
    print("Training Random Forest Regressor...")
    model = RandomForestRegressor(
        n_estimators=200,
        max_depth=12,
        min_samples_leaf=3,
        n_jobs=-1,
        random_state=RANDOM_SEED,
    )
    model.fit(X_train_s, y_train)

    # Evaluasi test set
    y_pred = model.predict(X_test_s)
    r2  = r2_score(y_test, y_pred)
    mae = mean_absolute_error(y_test, y_pred)
    print(f"\nTest Set Metrics:")
    print(f"  R²  : {r2:.4f}  {'✓' if r2 >= 0.70 else '✗ BELOW TARGET (< 0.70)'}")
    print(f"  MAE : {mae:.4f} kendaraan/menit")

    # Cross-validation 5-fold
    print("\nCross-Validation (5-fold)...")
    X_scaled = scaler.transform(X)
    cv       = KFold(n_splits=5, shuffle=True, random_state=RANDOM_SEED)
    cv_scores = cross_val_score(model, X_scaled, y, cv=cv, scoring="r2", n_jobs=-1)
    print(f"  CV R² per fold: {[round(s, 4) for s in cv_scores]}")
    print(f"  CV R² mean    : {cv_scores.mean():.4f} ± {cv_scores.std():.4f}")

    if cv_scores.mean() < 0.70:
        print("CV R² di bawah target 0.70 — lihat troubleshooting di bagian bawah")

    # Feature importance
    fi = dict(zip(TRAFFIC_FEATS, model.feature_importances_.round(4)))
    print(f"\nFeature Importance:")
    for feat, imp in sorted(fi.items(), key=lambda x: -x[1]):
        bar = "█" * int(imp * 40)
        print(f"  {feat:<20}: {imp:.4f}  {bar}")

    return {
        "model":    model,
        "scaler":   scaler,
        "features": TRAFFIC_FEATS,
        "metrics": {
            "test_r2":  round(r2, 4),
            "test_mae": round(mae, 4),
            "cv_r2_mean": round(cv_scores.mean(), 4),
            "cv_r2_std":  round(cv_scores.std(), 4),
        },
        "feature_importance": fi,
    }


# MODEL 2 — AIR QUALITY CLASSIFIER
def train_air_quality_model() -> dict:
    """
    Task      : Multi-class Classification
    Algoritma : Gradient Boosting Classifier
    Target    : aqi_category (Good / Moderate / Unhealthy / Hazardous)
    Fitur     : pm25, pm10, no2, co, o3, temperature, humidity
    """
    print("\n" + "=" * 55)
    print("MODEL 2 — Air Quality Classifier")
    print("=" * 55)

    # Load dataset
    path = os.path.join(DATA_DIR, "air_quality.csv")
    df = pd.read_csv(path)
    print(f"Dataset loaded: {df.shape}")

    AIR_FEATS = ["pm25", "pm10", "no2", "co", "o3", "temperature", "humidity"]
    TARGET    = "aqi_category"

    df = df.dropna(subset=AIR_FEATS + [TARGET])

    # Encode label
    le = LabelEncoder()
    y  = le.fit_transform(df[TARGET])
    X  = df[AIR_FEATS].values

    print(f"Kelas AQI: {list(le.classes_)}")
    for cls, count in zip(le.classes_, np.bincount(y)):
        print(f"  {cls:<20}: {count} ({count/len(y)*100:.1f}%)")

    # Train/test split — stratified supaya distribusi kelas seimbang
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, stratify=y, random_state=RANDOM_SEED
    )

    # Scaling
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s  = scaler.transform(X_test)

    # Train model
    print("\nTraining Gradient Boosting Classifier...")
    model = GradientBoostingClassifier(
        n_estimators=150,
        learning_rate=0.1,
        max_depth=5,
        subsample=0.8,
        random_state=RANDOM_SEED,
    )
    model.fit(X_train_s, y_train)

    # Evaluasi test set
    y_pred  = model.predict(X_test_s)
    acc     = accuracy_score(y_test, y_pred)
    print(f"\nTest Set Metrics:")
    print(f"  Accuracy: {acc:.4f}  {'✓' if acc >= 0.70 else '✗ BELOW TARGET (< 0.70)'}")
    print(f"\nClassification Report:")
    print(classification_report(y_test, y_pred, target_names=le.classes_))

    # Cross-validation 5-fold stratified
    print("Cross-Validation (5-fold stratified)...")
    X_scaled  = scaler.transform(X)
    skf       = StratifiedKFold(n_splits=5, shuffle=True, random_state=RANDOM_SEED)
    cv_scores = cross_val_score(model, X_scaled, y, cv=skf, scoring="accuracy", n_jobs=-1)
    print(f"  CV Accuracy per fold: {[round(s, 4) for s in cv_scores]}")
    print(f"  CV Accuracy mean    : {cv_scores.mean():.4f} ± {cv_scores.std():.4f}")

    if cv_scores.mean() < 0.70:
        print("  CV Accuracy di bawah target 0.70 — lihat troubleshooting di bagian bawah")

    # Feature importance
    fi = dict(zip(AIR_FEATS, model.feature_importances_.round(4)))
    print(f"\nFeature Importance:")
    for feat, imp in sorted(fi.items(), key=lambda x: -x[1]):
        bar = "█" * int(imp * 40)
        print(f"  {feat:<15}: {imp:.4f}  {bar}")

    return {
        "model":    model,
        "scaler":   scaler,
        "le_aqi":   le,
        "features": AIR_FEATS,
        "metrics": {
            "test_accuracy":  round(acc, 4),
            "cv_acc_mean":    round(cv_scores.mean(), 4),
            "cv_acc_std":     round(cv_scores.std(), 4),
        },
        "feature_importance": fi,
        "classes": list(le.classes_),
    }


# MODEL 3 — ANOMALY DETECTOR
def train_anomaly_model() -> dict:
    """
    Task      : Anomaly Detection (Unsupervised)
    Algoritma : Isolation Forest
    Target    : is_anomaly (derive dari z_score, bukan supervised label)
    Fitur     : sensor_value, timestamp_hour, rolling_mean_1h, z_score

    Catatan: Isolation Forest adalah algoritma unsupervised — tidak butuh label
    saat training. Label is_anomaly di dataset kita digunakan HANYA untuk evaluasi
    (menghitung precision/recall), bukan untuk training.
    """
    print("\n" + "=" * 55)
    print("MODEL 3 — Anomaly Detector (Isolation Forest)")
    print("=" * 55)

    # Load dataset
    path = os.path.join(DATA_DIR, "sensor_readings.csv")
    df = pd.read_csv(path)
    print(f"Dataset loaded: {df.shape}")

    ANOMALY_FEATS = ["sensor_value", "timestamp_hour", "rolling_mean_1h", "z_score"]
    TARGET        = "is_anomaly"

    df = df.dropna(subset=ANOMALY_FEATS)
    X  = df[ANOMALY_FEATS].values
    y  = df[TARGET].values  # hanya untuk evaluasi

    # Scaling
    scaler = StandardScaler()
    X_s    = scaler.fit_transform(X)

    # Hitung contamination dari data aktual
    contamination = float(y.mean())
    contamination = max(0.01, min(contamination, 0.5))  # clamp 1%–50%
    print(f"Contamination (proporsi anomali aktual): {contamination:.4f} ({contamination*100:.2f}%)")

    # Train Isolation Forest
    print("Training Isolation Forest...")
    model = IsolationForest(
        n_estimators=200,
        contamination=contamination,
        max_samples="auto",
        random_state=RANDOM_SEED,
        n_jobs=-1,
    )
    model.fit(X_s)

    # Evaluasi: Isolation Forest predict() mengembalikan 1 (normal) atau -1 (anomali)
    # Kita konversi ke 0/1 supaya konsisten dengan label kita
    y_pred_raw = model.predict(X_s)
    y_pred     = (y_pred_raw == -1).astype(int)  # -1 → anomali → 1

    # Hitung metrik
    from sklearn.metrics import precision_score, recall_score, f1_score
    precision = precision_score(y, y_pred, zero_division=0)
    recall    = recall_score(y, y_pred, zero_division=0)
    f1        = f1_score(y, y_pred, zero_division=0)

    detected  = y_pred.sum()
    actual    = y.sum()

    print(f"\nEvaluasi vs label z-score:")
    print(f"  Anomali aktual    : {actual} ({actual/len(y)*100:.2f}%)")
    print(f"  Anomali terdeteksi: {detected} ({detected/len(y)*100:.2f}%)")
    print(f"  Precision         : {precision:.4f}")
    print(f"  Recall            : {recall:.4f}")
    print(f"  F1-Score          : {f1:.4f}")

    # Anomaly scores (lebih negatif = lebih anomali)
    scores = model.score_samples(X_s)
    print(f"\nAnomaly score stats:")
    print(f"  Min  : {scores.min():.4f}")
    print(f"  Max  : {scores.max():.4f}")
    print(f"  Mean : {scores.mean():.4f}")

    # Feature importance tidak tersedia di IsolationForest secara langsung
    # Gunakan mean absolute feature contribution sebagai proxy
    fi = {}
    for i, feat in enumerate(ANOMALY_FEATS):
        fi[feat] = round(float(np.abs(X_s[:, i]).mean()), 4)
    print(f"\nFeature mean absolute value (proxy importance):")
    for feat, val in sorted(fi.items(), key=lambda x: -x[1]):
        bar = "█" * int(val * 10)
        print(f"  {feat:<25}: {val:.4f}  {bar}")

    return {
        "model":    model,
        "scaler":   scaler,
        "features": ANOMALY_FEATS,
        "metrics": {
            "precision":     round(precision, 4),
            "recall":        round(recall, 4),
            "f1":            round(f1, 4),
            "contamination": round(contamination, 4),
        },
        "feature_importance": fi,
    }


# MAIN
def main():
    print("=" * 55)
    print("Smart City — ML Training Pipeline")
    print("=" * 55)

    bundle = {}

    # Train semua model
    bundle["traffic"] = train_traffic_model()
    bundle["air"]     = train_air_quality_model()
    bundle["anomaly"] = train_anomaly_model()   # ← sekarang implementasi penuh

    # Simpan semua model dalam satu file
    out_path = os.path.join(MODELS_DIR, "smartcity_models.pkl")
    joblib.dump(bundle, out_path)
    print(f"\n✓ All models saved → {out_path}")

    # Ringkasan performa semua model
    print("\n" + "=" * 55)
    print("RINGKASAN PERFORMA SEMUA MODEL")
    print("=" * 55)

    if bundle.get("traffic"):
        m = bundle["traffic"]["metrics"]
        status = "✓" if m["cv_r2_mean"] >= 0.70 else "✗"
        print(f"  {status} Traffic Predictor")
        print(f"      Test R²    : {m['test_r2']}")
        print(f"      CV R² mean : {m['cv_r2_mean']} ± {m['cv_r2_std']}")

    if bundle.get("air"):
        m = bundle["air"]["metrics"]
        status = "✓" if m["cv_acc_mean"] >= 0.70 else "✗"
        print(f"  {status} AQI Classifier")
        print(f"      Test Acc   : {m['test_accuracy']}")
        print(f"      CV Acc mean: {m['cv_acc_mean']} ± {m['cv_acc_std']}")

    if bundle.get("anomaly"):
        m = bundle["anomaly"]["metrics"]
        print(f"  ✓ Anomaly Detector")
        print(f"      Precision  : {m['precision']}")
        print(f"      Recall     : {m['recall']}")
        print(f"      F1-Score   : {m['f1']}")

    print("\nSelesai! File model: models/smartcity_models.pkl")



if __name__ == "__main__":
    main()