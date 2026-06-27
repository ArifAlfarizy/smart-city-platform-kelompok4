import os
import joblib
import numpy as np
import pandas as pd

from sklearn.linear_model import LogisticRegression
from sklearn.preprocessing import StandardScaler, LabelEncoder
from sklearn.model_selection import train_test_split, StratifiedKFold, cross_val_score
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, classification_report

DATA_DIR = os.path.join(os.path.dirname(__file__), "data")
MODELS_DIR = os.path.join(os.path.dirname(__file__), "models")
RANDOM_SEED = 42

os.makedirs(MODELS_DIR, exist_ok=True)

FEATURES = ["vehicle_count", "average_speed", "rainfall", "water_level", "hour", "day_of_week"]
TARGET = "incident_risk"

def train_risk_model():
    print("=" * 55)
    print("MODEL 3 — Incident Risk Predictor")
    print("=" * 55)
    
    # Load dataset
    path = os.path.join(DATA_DIR, "risk_dataset.csv")
    df = pd.read_csv(path)
    print(f"Dataset loaded: {df.shape}")
    
    df = df.dropna()
    
    # Encode target
    le = LabelEncoder()
    y = le.fit_transform(df[TARGET])
    X = df[FEATURES].values
    
    print(f"\nClasses: {list(le.classes_)}")
    for cls, count in zip(le.classes_, np.bincount(y)):
        pct = count / len(y) * 100
        print(f"  {cls:<10}: {count:>5} ({pct:5.1f}%)")
    
    # Train-test split
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, stratify=y, random_state=RANDOM_SEED
    )
    
    # Scaling
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s = scaler.transform(X_test)
    
    # Train model
    print("\nTraining Logistic Regression...")
    model = LogisticRegression(
        max_iter=1000,
        class_weight='balanced',
        random_state=RANDOM_SEED
    )
    model.fit(X_train_s, y_train)
    
    # Evaluasi
    y_pred = model.predict(X_test_s)
    acc = accuracy_score(y_test, y_pred)
    precision = precision_score(y_test, y_pred)
    recall = recall_score(y_test, y_pred)
    f1 = f1_score(y_test, y_pred)
    
    print(f"\nTest Set Metrics:")
    print(f"  Accuracy : {acc:.4f}")
    print(f"  Precision: {precision:.4f}")
    print(f"  Recall   : {recall:.4f}")
    print(f"  F1-Score : {f1:.4f}")
    print(f"\nClassification Report:")
    print(classification_report(y_test, y_pred, target_names=le.classes_))
    
    # Cross-validation
    print("\nCross-Validation (5-fold stratified)...")
    skf = StratifiedKFold(n_splits=5, shuffle=True, random_state=RANDOM_SEED)
    cv_scores = cross_val_score(model, scaler.transform(X), y, cv=skf, scoring="accuracy")
    print(f"  CV Accuracy: {cv_scores.mean():.4f} ± {cv_scores.std():.4f}")
    
    # Feature coefficients
    coef = dict(zip(FEATURES, model.coef_[0].round(4)))
    print(f"\nFeature Coefficients:")
    for feat, val in sorted(coef.items(), key=lambda x: abs(x[1]), reverse=True):
        bar = "█" * int(abs(val) * 15)
        print(f"  {feat:<15}: {val:+.4f} {bar}")
    
    return {
        "model": model,
        "scaler": scaler,
        "label_encoder": le,
        "features": FEATURES,
        "classes": list(le.classes_),
        "metrics": {
            "accuracy": round(acc, 4),
            "precision": round(precision, 4),
            "recall": round(recall, 4),
            "f1": round(f1, 4),
            "cv_mean": round(cv_scores.mean(), 4),
            "cv_std": round(cv_scores.std(), 4),
        },
        "feature_coefficients": coef,
    }

def main():
    bundle = train_risk_model()
    
    out = os.path.join(MODELS_DIR, "risk_model.pkl")
    joblib.dump(bundle, out)
    print(f"\nModel saved → {out}")
    
    print(f"\n── Ringkasan ──")
    print(f" Accuracy : {bundle['metrics']['accuracy']}")
    print(f" F1-Score : {bundle['metrics']['f1']}")

if __name__ == "__main__":
    main()