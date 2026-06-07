# Dataset Sources

## Dataset 1 — Traffic History

- **Sumber:** Traffic Prediction Dataset by fedesoriano
- **Link:** https://www.kaggle.com/datasets/fedesoriano/traffic-prediction-dataset
- **Download:** `kaggle datasets download -d fedesoriano/traffic-prediction-dataset`
- **Dipakai untuk:** Traffic Density Predictor (Random Forest Regressor)

## Dataset 2 — Air Quality

- **Sumber:** Global Air Pollution Dataset by hasibalmuzdadid
- **Link:** https://www.kaggle.com/datasets/hasibalmuzdadid/global-air-pollution-dataset
- **Download:** `kaggle datasets download -d hasibalmuzdadid/global-air-pollution-dataset`
- **Dipakai untuk:** Air Quality Classifier (Gradient Boosting)

## Dataset 3 — Sensor Anomaly

- **Sumber:** Numenta Anomaly Benchmark (NAB) by boltzmannbrain
- **Link:** https://www.kaggle.com/datasets/boltzmannbrain/nab
- **Download:** `kaggle datasets download -d boltzmannbrain/nab`
- **Dipakai untuk:** Anomaly Detector (Isolation Forest)
- **File yang dipakai:** `realKnownCause/machine_temperature_system_failure.csv`

## Catatan

File raw dataset ada di `data/raw/` dan di-gitignore (ukuran terlalu besar).
Setelah clone repo, jalankan perintah download di atas untuk mendapatkan dataset.
Dataset hasil preprocessing (setelah Sprint 2) disimpan langsung di `data/`.
