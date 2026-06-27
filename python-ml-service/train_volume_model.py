import os
import joblib
import numpy as np
import pandas as pd

from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import StandardScaler
from sklearn.model_selection import train_test_split, cross_val_score
from sklearn.metrics import r2_score, mean_absolute_error, mean_squared_error

DATA_DIR = os.path.join(os.path.dirname(__file__), "data")
MODELS_DIR = os.path.join(os.path.dirname(__file__), "models")
RANDOM_SEED = 42

os.makedirs(MODELS_DIR, exist_ok=True)

FEATURES = ["hour", "day_of_week", "rainfall", "water_level", "incident_count"]
TARGET = "vehicle_count"

def train_volume_model():
    print("=" * 55)
    print("MODEL 2 — Traffic Volume Predictor")
    print("=" * 55)
    
    # Load dataset
    path = os.path.join(DATA_DIR, "volume_dataset.csv")
    df = pd.read_csv(path)
    print(f"Dataset loaded: {df.shape}")
    
    df = df.dropna()
    X = df[FEATURES].values
    y = df[TARGET].values
    
    # Train-test split
    X_train, X_test, y_train, y_test = train_test_split(
        X, y, test_size=0.2, random_state=RANDOM_SEED
    )
    
    # Scaling
    scaler = StandardScaler()
    X_train_s = scaler.fit_transform(X_train)
    X_test_s = scaler.transform(X_test)
    
    # Train model
    print("\nTraining Random Forest Regressor...")
    model = RandomForestRegressor(
        n_estimators=200,
        max_depth=12,
        min_samples_leaf=3,
        n_jobs=-1,
        random_state=RANDOM_SEED
    )
    model.fit(X_train_s, y_train)
    
    # Evaluasi
    y_pred = model.predict(X_test_s)
    r2 = r2_score(y_test, y_pred)
    mae = mean_absolute_error(y_test, y_pred)
    rmse = np.sqrt(mean_squared_error(y_test, y_pred))
    
    print(f"\nTest Set Metrics:")
    print(f"  R²  : {r2:.4f}")
    print(f"  MAE : {mae:.2f} kendaraan")
    print(f"  RMSE: {rmse:.2f} kendaraan")
    
    # Cross-validation
    print("\nCross-Validation (5-fold)...")
    cv_scores = cross_val_score(
        model, scaler.transform(X), y, cv=5, scoring="r2", n_jobs=-1
    )
    print(f"  CV R² mean: {cv_scores.mean():.4f} ± {cv_scores.std():.4f}")
    
    # Feature importance
    fi = dict(zip(FEATURES, model.feature_importances_.round(4)))
    print(f"\nFeature Importance:")
    for feat, imp in sorted(fi.items(), key=lambda x: -x[1]):
        bar = "█" * int(imp * 40)
        print(f"  {feat:<15}: {imp:.4f} {bar}")
    
    return {
        "model": model,
        "scaler": scaler,
        "features": FEATURES,
        "metrics": {
            "r2": round(r2, 4),
            "mae": round(mae, 2),
            "rmse": round(rmse, 2),
            "cv_r2_mean": round(cv_scores.mean(), 4),
            "cv_r2_std": round(cv_scores.std(), 4),
        },
        "feature_importance": fi,
    }

def main():
    bundle = train_volume_model()
    
    # Simpan
    out = os.path.join(MODELS_DIR, "volume_model.pkl")
    joblib.dump(bundle, out)
    print(f"\nModel saved → {out}")
    
    print(f"\n── Ringkasan ──")
    print(f" R²  : {bundle['metrics']['r2']}")
    print(f" MAE : {bundle['metrics']['mae']} kendaraan")

if __name__ == "__main__":
    main()