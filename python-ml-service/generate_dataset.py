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

def simulate_traffic_mt_haryono(n: int) -> pd.DataFrame:
    rows = []
    base_date = datetime(2024, 1, 1)
 
    for _ in range(n):
        # Random timestamp dalam 1 tahun
        ts = base_date + timedelta(minutes=random.randint(0, 365*24*60))
        hour = ts.hour
        dow = ts.weekday() # 0=Senin
        # ── Rainfall (curah hujan Jakarta) ──────────────────
        # 70% tidak hujan, 20% hujan ringan, 8% hujan sedang, 2% hujan lebat
        rain_scenario = random.choices(
            ["none", "light", "moderate", "heavy"],
            weights=[70, 20, 8, 2]
        )[0]
        rainfall_map = {
            "none": random.uniform(0, 2),
            "light": random.uniform(2, 10),
            "moderate": random.uniform(10, 30),
            "heavy": random.uniform(30, 80),
        }
        rainfall = round(rainfall_map[rain_scenario] + np.random.normal(0, 0.5), 2)
        rainfall = max(0, rainfall)
 
        # Water Level Sungai Ciliwung
        # Baseline ~200cm, naik saat hujan, kadang banjir kiriman
        water_level_base = 200 + (rainfall * 3) + np.random.normal(0, 20)
        # 3% kejadian banjir kiriman (air naik drastis tanpa hujan lokal)
        if random.random() < 0.03:
            water_level_base += random.uniform(150, 300)
        water_level = round(max(50, water_level_base), 1)
 
        # ── Vehicle Count (kendaraan/jam) ───────────────────
        # Pola sinusoidal dengan peak pagi dan sore
        morning_peak = 800 * math.exp(-0.5 * ((hour - 8) / 1.2) ** 2)
        evening_peak = 900 * math.exp(-0.5 * ((hour - 17) / 1.5) ** 2)
        night_base = 80
        base_vehicle = morning_peak + evening_peak + night_base
 
        # Weekend lebih sepi 30%
        if dow >= 5:
           base_vehicle *= 0.70
 
        # Faktor hujan — orang masih perlu pergi kerja tapi lebih padat
        rain_factor = {
            "none": 1.0, "light": 1.10, "moderate": 1.25, "heavy": 1.40
        }[rain_scenario]
        base_vehicle *= rain_factor
 
        # Faktor banjir — orang hindari jalan ini
        if water_level > 500:
            base_vehicle *= 0.5 # banyak yang putar balik
        elif water_level > 400:
            base_vehicle *= 0.75
 
        vehicle_count = max(0, int(base_vehicle + np.random.normal(0, 50)))
 
        # Incident Count
        # Lebih banyak saat hujan dan padat
        incident_prob = 0.05 + (rainfall / 100) * 0.15 + (vehicle_count / 5000) * 0.10
        incident_count = np.random.poisson(max(0, incident_prob * 3))
        incident_count = min(incident_count, 10)
 
        # Average Speed (km/jam)
        # Berbanding terbalik dengan kepadatan dan insiden
        if vehicle_count > 1500:
            base_speed = 10 + np.random.normal(0, 5)
        elif vehicle_count > 900:
            base_speed = 25 + np.random.normal(0, 8)
        elif vehicle_count > 400:
            base_speed = 45 + np.random.normal(0, 10)
        else:
            base_speed = 60 + np.random.normal(0, 8)
 
        # Hujan menurunkan kecepatan
        speed_rain_factor = {
            "none": 1.0, "light": 0.90, "moderate": 0.75, "heavy": 0.60
        }[rain_scenario]
        base_speed *= speed_rain_factor
 
        # Tiap insiden menurunkan kecepatan 5%
        base_speed *= (1 - incident_count * 0.05)
 
        average_speed = round(max(3, min(80, base_speed)), 1)
 
        # Congestion Level (TARGET)
        # Tentukan berdasarkan kombinasi semua faktor
        # Ini adalah "ground truth" yang kita latih modelnya
        score = 0
        
        # Skor kendaraan
        if vehicle_count > 1500: score += 4
        elif vehicle_count > 900: score += 3
        elif vehicle_count > 400: score += 2
        elif vehicle_count > 150: score += 1
        
        # Skor kecepatan
        if average_speed < 10: score += 4
        elif average_speed < 20: score += 3
        elif average_speed < 35: score += 2
        elif average_speed < 50: score += 1
        
        # Skor muka air
        if water_level > 500: score += 4
        elif water_level > 400: score += 3
        elif water_level > 300: score += 1
        
        # Skor curah hujan
        if rainfall > 30: score += 2
        elif rainfall > 10: score += 1
        
        # Skor insiden
        if incident_count >= 3: score += 3
        elif incident_count >= 1: score += 1

        # Mapping skor ke label
        if score >= 9:
            label = "Sangat Macet"
        elif score >= 6:
            label = "Macet"
        elif score >= 3:
            label = "Padat"
        else:
            label = "Normal"

        rows.append({
            "timestamp": ts.isoformat(),
            "hour": hour,
            "day_of_week": dow,
            "vehicle_count": vehicle_count,
            "average_speed": average_speed,
            "rainfall": rainfall,
            "water_level": water_level,
            "incident_count": incident_count,
            "congestion_level": label,
        })

    df = pd.DataFrame(rows)
    return df

def main():
    print("=" * 60)
    print("Smart Traffic MT Haryono — Dataset Generator")
    print("=" * 60)
    
    print(f"\nGenerating {N_ROWS} rows...")
    df = simulate_traffic_mt_haryono(N_ROWS)
    
    out = os.path.join(OUTPUT_DIR, "traffic_mt_haryono.csv")
    df.to_csv(out, index=False)
    
    print(f"\n✓ Saved → {out}")
    print(f" Shape : {df.shape}")
    print(f" Columns : {list(df.columns)}")
    print(f"\nDistribusi Congestion Level:")
    vc = df['congestion_level'].value_counts()
    for label, count in vc.items():
        pct = count / len(df) * 100
        bar = '█' * int(pct / 2)
        print(f" {label:<15}: {count:>5} ({pct:5.1f}%) {bar}")
    
    print("\n✓ Dataset siap untuk training!")
    print(" Jalankan: python train_models.py")
    print("=" * 60)

if __name__ == "__main__":
    main()