"""
preprocess.py
-------------
Preprocessing pipeline untuk ketiga dataset ML:
  1. traffic_history.csv   → siap untuk Traffic Density Predictor
  2. air_quality.csv       → siap untuk Air Quality Classifier
  3. sensor_readings.csv   → siap untuk Anomaly Detector

Jalankan SATU KALI sebelum training:
  python preprocess.py

Input : data/raw/
Output: data/traffic_history.csv
        data/air_quality.csv
        data/sensor_readings.csv
"""

import os
import glob
import numpy as np
import pandas as pd
from sklearn.preprocessing import LabelEncoder

RAW_DIR    = os.path.join(os.path.dirname(__file__), "data", "raw")
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "data")
RANDOM_SEED = 42

np.random.seed(RANDOM_SEED)


# PREPROCESSING 1 — TRAFFIC DATASET
def preprocess_traffic() -> pd.DataFrame:
    """
    Input  : data/raw/traffic/*.csv (fedesoriano/traffic-prediction-dataset)
    Output : traffic_history.csv
    Kolom  : hour, day_of_week, weather_code, prev_density, location_enc,
             vehicle_density (target), congestion_level
    """
    print("\n[1/3] Preprocessing traffic dataset...")

    # Load semua file
    files = glob.glob(os.path.join(RAW_DIR, "traffic", "**", "*.csv"), recursive=True)
    if not files:
        raise FileNotFoundError(
            "Dataset traffic tidak ditemukan di data/raw/traffic/\n"
            "Jalankan: kaggle datasets download -d fedesoriano/traffic-prediction-dataset"
        )

    df = pd.concat([pd.read_csv(f) for f in files], ignore_index=True)
    print(f"  Raw shape: {df.shape}")

    # Parse datetime
    df["DateTime"] = pd.to_datetime(df["DateTime"])

    # Feature engineering
    df["hour"]        = df["DateTime"].dt.hour
    df["day_of_week"] = df["DateTime"].dt.dayofweek

    # Lag feature: prev_density (kendaraan 1 jam sebelumnya per junction)
    df = df.sort_values(["Junction", "DateTime"]).reset_index(drop=True)
    df["prev_density"] = df.groupby("Junction")["Vehicles"].shift(1)

    # Label encode junction → location_enc
    le = LabelEncoder()
    df["location_enc"] = le.fit_transform(df["Junction"].astype(str))

    # Weather code dummy (tidak ada di dataset)
    # Distribusi realistis: 60% cerah, 25% berawan, 15% hujan
    df["weather_code"] = np.random.choice(
        [0, 1, 2], size=len(df), p=[0.60, 0.25, 0.15]
    )

    # Rename target
    df["vehicle_density"] = df["Vehicles"]

    # Congestion level label
    df["congestion_level"] = pd.cut(
        df["vehicle_density"],
        bins=[-1, 40, 80, float("inf")],
        labels=["Lancar", "Sedang", "Padat"]
    )

    # Drop NaN dari lag dan pilih kolom final
    FEATURES = [
        "hour", "day_of_week", "weather_code",
        "prev_density", "location_enc",
        "vehicle_density", "congestion_level"
    ]
    df = df[FEATURES].dropna().reset_index(drop=True)

    print(f"  Processed shape: {df.shape}")
    print(f"  Congestion dist:\n{df['congestion_level'].value_counts().to_string()}")
    return df


# PREPROCESSING 2 — AIR QUALITY DATASET
def preprocess_air_quality() -> pd.DataFrame:
    """
    Input  : data/raw/air_quality/*.csv (hasibalmuzdadid/global-air-pollution-dataset)
    Output : air_quality.csv
    Kolom  : pm25, pm10, no2, co, o3, temperature, humidity, aqi_category (target)
    """
    print("\n[2/3] Preprocessing air quality dataset...")

    files = glob.glob(os.path.join(RAW_DIR, "air_quality", "**", "*.csv"), recursive=True)
    if not files:
        raise FileNotFoundError(
            "Dataset air quality tidak ditemukan di data/raw/air_quality/\n"
            "Jalankan: kaggle datasets download -d hasibalmuzdadid/global-air-pollution-dataset"
        )

    df = pd.concat([pd.read_csv(f) for f in files], ignore_index=True)
    print(f"  Raw shape: {df.shape}")

    # Rename kolom ke nama yang dipakai model
    rename_map = {
        "PM2.5 AQI Value": "pm25",
        "CO AQI Value":    "co",
        "Ozone AQI Value": "o3",
        "NO2 AQI Value":   "no2",
        "AQI Category":    "aqi_category",
    }
    df = df.rename(columns=rename_map)

    # Drop baris tanpa label
    df = df.dropna(subset=["aqi_category", "pm25", "co", "o3", "no2"])

    n = len(df)

    # Tambah kolom yang tidak ada di dataset (synthetic)
    df["pm10"]        = (df["pm25"] * np.random.uniform(1.3, 1.8, n)
                         + np.random.normal(0, 3, n)).clip(lower=0)
    df["temperature"] = np.random.uniform(24, 36, n)
    df["humidity"]    = np.random.uniform(50, 90, n)

    # Clamp nilai negatif
    for col in ["pm25", "co", "o3", "no2"]:
        df[col] = df[col].clip(lower=0)

    # Pilih kolom final
    FEATURES = ["pm25", "pm10", "no2", "co", "o3", "temperature", "humidity", "aqi_category"]
    df = df[FEATURES].reset_index(drop=True)

    print(f"  Processed shape: {df.shape}")
    print(f"  AQI Category dist:\n{df['aqi_category'].value_counts().to_string()}")
    return df


# PREPROCESSING 3 — SENSOR ANOMALY (NAB)
def preprocess_sensor() -> pd.DataFrame:
    """
    Input  : data/raw/nab/**/*.csv (boltzmannbrain/nab)
    Output : sensor_readings.csv
    Kolom  : sensor_value, timestamp_hour, rolling_mean_1h, z_score, is_anomaly (target)
    """
    print("\n[3/3] Preprocessing sensor/anomaly dataset (NAB)...")

    # File yang paling relevan
    candidates = [
        os.path.join(RAW_DIR, "nab", "realKnownCause", "machine_temperature_system_failure.csv"),
        os.path.join(RAW_DIR, "nab", "realKnownCause", "ambient_temperature_system_failure.csv"),
    ]

    loaded = []
    for path in candidates:
        if os.path.exists(path):
            df_temp = pd.read_csv(path)
            df_temp["source_file"] = os.path.basename(path)
            loaded.append(df_temp)
            print(f"  Loaded: {os.path.basename(path)} | {df_temp.shape}")

    # Fallback: ambil file pertama yang ditemukan
    if not loaded:
        all_files = glob.glob(os.path.join(RAW_DIR, "nab", "**", "*.csv"), recursive=True)
        if not all_files:
            raise FileNotFoundError(
                "Dataset NAB tidak ditemukan di data/raw/nab/\n"
                "Jalankan: kaggle datasets download -d boltzmannbrain/nab"
            )
        for f in all_files[:3]:
            df_temp = pd.read_csv(f)
            df_temp["source_file"] = os.path.basename(f)
            loaded.append(df_temp)
            print(f"  Loaded (fallback): {os.path.basename(f)} | {df_temp.shape}")

    df = pd.concat(loaded, ignore_index=True)
    print(f"  Raw shape (gabungan): {df.shape}")

    # Parse timestamp
    df["timestamp"] = pd.to_datetime(df["timestamp"])
    df = df.sort_values("timestamp").reset_index(drop=True)

    # Konversi nilai sensor
    df["value"] = pd.to_numeric(df["value"], errors="coerce")
    df = df.dropna(subset=["value"])

    # Feature engineering
    df["sensor_value"]    = df["value"]
    df["timestamp_hour"]  = df["timestamp"].dt.hour

    # Rolling features (window=12 asumsi 5 menit per baris = 1 jam)
    df["rolling_mean_1h"] = df["value"].rolling(window=12, min_periods=1).mean()
    df["rolling_std_1h"]  = df["value"].rolling(window=12, min_periods=1).std().fillna(1)

    # Z-score
    df["z_score"] = (
        (df["sensor_value"] - df["rolling_mean_1h"]) / df["rolling_std_1h"]
    )

    # Label anomali: |z_score| > 3.0 (3-sigma rule)
    Z_THRESHOLD = 3.0
    df["is_anomaly"] = (df["z_score"].abs() > Z_THRESHOLD).astype(int)

    # Pilih kolom final
    FEATURES = ["sensor_value", "timestamp_hour", "rolling_mean_1h", "z_score", "is_anomaly"]
    df = df[FEATURES].reset_index(drop=True)

    anomaly_count = df["is_anomaly"].sum()
    print(f"  Processed shape: {df.shape}")
    print(f"  Anomali: {anomaly_count} ({anomaly_count/len(df)*100:.1f}%) | "
          f"Normal: {len(df)-anomaly_count} ({(len(df)-anomaly_count)/len(df)*100:.1f}%)")
    return df


# MAIN
def main():
    print("=" * 60)
    print("Smart City — Dataset Preprocessing Pipeline")
    print("=" * 60)

    os.makedirs(OUTPUT_DIR, exist_ok=True)

    # Traffic
    df_traffic = preprocess_traffic()
    out = os.path.join(OUTPUT_DIR, "traffic_history.csv")
    df_traffic.to_csv(out, index=False)
    print(f"  Saved → {out}")

    # Air Quality
    df_air = preprocess_air_quality()
    out = os.path.join(OUTPUT_DIR, "air_quality.csv")
    df_air.to_csv(out, index=False)
    print(f"  Saved → {out}")

    # Sensor / Anomaly
    df_sensor = preprocess_sensor()
    out = os.path.join(OUTPUT_DIR, "sensor_readings.csv")
    df_sensor.to_csv(out, index=False)
    print(f"  Saved → {out}")

    print("\n" + "=" * 60)
    print("Semua dataset berhasil dipreprocess!")
    print("  Selanjutnya jalankan: python train_models.py")
    print("=" * 60)


if __name__ == "__main__":
    main()