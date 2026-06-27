import os
import math
import random
import numpy as np
import pandas as pd
from datetime import datetime, timedelta

RANDOM_SEED = 42
N_ROWS = 8000
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "data")

random.seed(RANDOM_SEED)
np.random.seed(RANDOM_SEED)
os.makedirs(OUTPUT_DIR, exist_ok=True)

def generate_risk_dataset(n: int) -> pd.DataFrame:
    rows = []
    base_date = datetime(2024, 1, 1)
    
    for _ in range(n):
        ts = base_date + timedelta(minutes=random.randint(0, 365*24*60))
        hour = ts.hour
        dow = ts.weekday()
        
        # Rainfall
        rain_scenario = random.choices(
            ["none", "light", "moderate", "heavy"],
            weights=[60, 25, 10, 5]
        )[0]
        rainfall_map = {
            "none": random.uniform(0, 2),
            "light": random.uniform(2, 10),
            "moderate": random.uniform(10, 35),
            "heavy": random.uniform(35, 80),
        }
        rainfall = round(rainfall_map[rain_scenario] + np.random.normal(0, 0.5), 2)
        rainfall = max(0, rainfall)
        
        # Water Level
        water_level_base = 200 + (rainfall * 3) + np.random.normal(0, 20)
        if random.random() < 0.03:
            water_level_base += random.uniform(150, 350)
        water_level = round(max(50, water_level_base), 1)
        
        # Vehicle Count
        morning_peak = 800 * math.exp(-0.5 * ((hour - 8) / 1.2) ** 2)
        evening_peak = 900 * math.exp(-0.5 * ((hour - 17) / 1.5) ** 2)
        night_base = 80
        base_vehicle = morning_peak + evening_peak + night_base
        
        if dow >= 5:
            base_vehicle *= 0.70
        
        rain_factor = {"none": 1.0, "light": 1.10, "moderate": 1.25, "heavy": 1.40}[rain_scenario]
        base_vehicle *= rain_factor
        
        if water_level > 500:
            base_vehicle *= 0.5
        elif water_level > 400:
            base_vehicle *= 0.75
        
        vehicle_count = max(0, int(base_vehicle + np.random.normal(0, 50)))
        
        # Average Speed
        if vehicle_count > 1500:
            base_speed = 10 + np.random.normal(0, 5)
        elif vehicle_count > 900:
            base_speed = 25 + np.random.normal(0, 8)
        elif vehicle_count > 400:
            base_speed = 45 + np.random.normal(0, 10)
        else:
            base_speed = 60 + np.random.normal(0, 8)
        
        speed_rain_factor = {"none": 1.0, "light": 0.90, "moderate": 0.75, "heavy": 0.60}[rain_scenario]
        base_speed *= speed_rain_factor
        
        average_speed = round(max(3, min(80, base_speed)), 1)
        
        # Incident Risk (TARGET)
        risk_score = 0
        if vehicle_count > 1000: risk_score += 1
        if average_speed < 20: risk_score += 1
        if rainfall > 20: risk_score += 1
        if water_level > 300: risk_score += 1
        if hour in [7, 8, 17, 18]: risk_score += 1  # jam sibuk
        
        # Tambah noise biar gak perfect
        if random.random() < 0.05:
            risk_score = random.randint(0, 4)
        
        incident_risk = "high_risk" if risk_score >= 3 else "low_risk"
        
        rows.append({
            "vehicle_count": vehicle_count,
            "average_speed": average_speed,
            "rainfall": rainfall,
            "water_level": water_level,
            "hour": hour,
            "day_of_week": dow,
            "incident_risk": incident_risk,  # TARGET
        })
    
    return pd.DataFrame(rows)

def main():
    print("=" * 60)
    print("Model 3 — Incident Risk Dataset Generator")
    print("=" * 60)
    
    print(f"\nGenerating {N_ROWS} rows...")
    df = generate_risk_dataset(N_ROWS)
    
    out = os.path.join(OUTPUT_DIR, "risk_dataset.csv")
    df.to_csv(out, index=False)
    
    print(f"\nSaved → {out}")
    print(f" Shape: {df.shape}")
    print(f" Columns: {list(df.columns)}")
    print(f"\nTarget: incident_risk")
    vc = df['incident_risk'].value_counts()
    for label, count in vc.items():
        pct = count / len(df) * 100
        bar = '█' * int(pct / 2)
        print(f"  {label:<10}: {count:>5} ({pct:5.1f}%) {bar}")
    
    print("\nDataset siap untuk training Incident Risk Predictor!")
    print("  Jalankan: python train_risk_model.py")

if __name__ == "__main__":
    main()