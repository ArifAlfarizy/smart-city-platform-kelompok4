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

def generate_volume_dataset(n: int) -> pd.DataFrame:
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
        
        # Vehicle Count (TARGET)
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
        
        # Incident Count
        incident_prob = 0.05 + (rainfall / 100) * 0.15 + (vehicle_count / 5000) * 0.10
        incident_count = np.random.poisson(max(0, incident_prob * 3))
        incident_count = min(incident_count, 10)
        
        rows.append({
            "hour": hour,
            "day_of_week": dow,
            "rainfall": rainfall,
            "water_level": water_level,
            "incident_count": incident_count,
            "vehicle_count": vehicle_count,  # TARGET
        })
    
    return pd.DataFrame(rows)

def main():
    print("=" * 60)
    print("Model 2 — Traffic Volume Dataset Generator")
    print("=" * 60)
    
    print(f"\nGenerating {N_ROWS} rows...")
    df = generate_volume_dataset(N_ROWS)
    
    out = os.path.join(OUTPUT_DIR, "volume_dataset.csv")
    df.to_csv(out, index=False)
    
    print(f"\nSaved → {out}")
    print(f" Shape: {df.shape}")
    print(f" Columns: {list(df.columns)}")
    print(f"\nTarget: vehicle_count")
    print(f"  Min : {df['vehicle_count'].min()}")
    print(f"  Max : {df['vehicle_count'].max()}")
    print(f"  Mean: {df['vehicle_count'].mean():.1f}")
    print(f"  Std : {df['vehicle_count'].std():.1f}")
    print("\nDataset siap untuk training Volume Predictor!")
    print("  Jalankan: python train_volume_model.py")

if __name__ == "__main__":
    main()