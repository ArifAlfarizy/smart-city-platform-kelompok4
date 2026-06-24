import os
import joblib
import numpy as np
import pandas as pd

from sklearn.ensemble import GradientBoostingClassifier
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.model_selection import train_test_split, StratifiedKFold, cross_val_score
from sklearn.metrics import accuracy_score, classification_report, confusion_matrix

DATA_DIR = os.path.join(os.path.dirname(__file__), "data")
MODELS_DIR = os.path.join(os.path.dirname(__file__), "models")
RANDOM_SEED = 42

os.makedirs(MODELS_DIR, exist_ok=True)

FEATURES = [
    "vehicle_count", "average_speed", "rainfall",
    "water_level", "incident_count", "hour", "day_of_week"
]
TARGET = "congestion_level"

def train_congestion_model() -> dict:
    print("=" * 55)
    print("Congestion Classifier — Gradient Boosting")
    print("=" * 55)
 
    # Load dataset
    path = os.path.join(DATA_DIR, "traffic_mt_haryono.csv")
    df = pd.read_csv(path)
    print(f"Dataset loaded: {df.shape}")
 
    df = df.dropna(subset=FEATURES + [TARGET])
 
    # Encode label
    le = LabelEncoder()
    y = le.fit_transform(df[TARGET])
    X = df[FEATURES].values
 
    print(f"\nKelas: {list(le.classes_)}")
    for cls, count in zip(le.classes_, np.bincount(y)):
        pct = count / len(y) * 100
        print(f" {cls:<15}: {count} ({pct:.1f}%)")
    # Train/test split — stratified
    X_train, X_test, y_train, y_test = train_test_split(
       X, y, test_size=0.2, stratify=y, random_state=RANDOM_SEED
    )
 
    # Scaling
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s = scaler.transform(X_test)
 
    # Train model
    print("\nTraining Gradient Boosting Classifier...")
    model = GradientBoostingClassifier(
        n_estimators=200,
        learning_rate=0.1,
        max_depth=5,
        subsample=0.8,
        random_state=RANDOM_SEED,
    )
    model.fit(X_train_s, y_train)
 
    # Evaluasi test set
    y_pred = model.predict(X_test_s)
    acc = accuracy_score(y_test, y_pred)
    print(f"\nTest Accuracy: {acc:.4f} {'V' if acc >= 0.70 else 'BELOW TARGET'}")
    print(f"\nClassification Report:")
    print(classification_report(y_test, y_pred, target_names=le.classes_))
 
    # Cross-validation 5-fold
    print("Cross-Validation (5-fold stratified)...")
    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=RANDOM_SEED)
    cv_scores = cross_val_score(
    model, scaler.transform(X), y, cv=skf, scoring="accuracy", n_jobs=-1)
    print(f" CV Accuracy per fold: {[round(s, 4) for s in cv_scores]}")
    print(f" CV Accuracy mean : {cv_scores.mean():.4f} ± {cv_scores.std():.4f}")
    
    # Feature importance
    fi = dict(zip(FEATURES, model.feature_importances_.round(4)))
    print(f"\nFeature Importance:")
    for feat, imp in sorted(fi.items(), key=lambda x: -x[1]):
        bar = "█" * int(imp * 40)
        print(f" {feat:<20}: {imp:.4f} {bar}")
 
    return {
        "model": model,
        "scaler": scaler,
        "le": le,
        "features": FEATURES,
        "classes": list(le.classes_),
        "metrics": {
        "test_accuracy": round(acc, 4),
        "cv_acc_mean": round(cv_scores.mean(), 4),
        "cv_acc_std": round(cv_scores.std(), 4),
    },
    "feature_importance": fi,
    }

def main():
    print("=" * 55)
    print("Smart Traffic MT Haryono — ML Training Pipeline")
    print("=" * 55)
    
    bundle = {}
    bundle["congestion"] = train_congestion_model()
    
    # Simpan
    out = os.path.join(MODELS_DIR, "smartcity_models.pkl")
    joblib.dump(bundle, out)
    print(f"\n✓ Model saved → {out}")
    
    m = bundle["congestion"]["metrics"]
    print(f"\n── Ringkasan ──")
    print(f" Accuracy : {m['test_accuracy']}")
    print(f" CV Acc : {m['cv_acc_mean']} ± {m['cv_acc_std']}")

if __name__ == "__main__":
    main()