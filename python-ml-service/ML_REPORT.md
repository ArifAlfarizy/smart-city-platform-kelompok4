# 1. PENDAHULUAN

## 1.1 Latar Belakang

Smart Traffic Decision Support System adalah sistem yang dirancang untuk membantu Dinas Perhubungan DKI Jakarta dalam mengambil keputusan rekayasa lalu lintas di Jalan MT Haryono, Jakarta Timur. Jalan MT Haryono merupakan ruas jalan utama yang menghubungkan kawasan Cawang, Tebet, Pancoran, dan akses Tol Dalam Kota. Volume kendaraan yang tinggi, terutama pada jam sibuk, ditambah dengan faktor lingkungan seperti curah hujan dan tinggi muka air Sungai Ciliwung, sering menyebabkan kemacetan parah.

Machine Learning digunakan untuk:
1. Memprediksi tingkat kemacetan berdasarkan data real-time
2. Memprediksi volume kendaraan 1 jam ke depan
3. Mendeteksi dini potensi insiden lalu lintas
4. Memberikan rekomendasi tindakan kepada operator

## 1.2 Tujuan

Tujuan dari pengembangan model ML ini adalah:
1. Membangun model klasifikasi kemacetan dengan accuracy >= 70%
2. Membangun model regresi untuk prediksi volume kendaraan
3. Membangun model klasifikasi biner untuk deteksi risiko insiden
4. Mengintegrasikan ketiga model ke dalam REST API

## 1.3 Ruang Lingkup

| Model | Task | Algoritma | Output |
|-------|------|-----------|--------|
| Model 1 | Multi-class Classification | Gradient Boosting | Normal/Padat/Macet/Sangat Macet |
| Model 2 | Regression | Random Forest Regressor | predicted_vehicle_count |
| Model 3 | Binary Classification | Logistic Regression | high_risk / low_risk |

---

# 2. DATASET

## 2.1 Sumber Dataset

Dataset yang digunakan adalah synthetic dataset yang dihasilkan dari script `generate_dataset.py`, `generate_volume_dataset.py`, dan `generate_risk_dataset.py`. Data disimulasikan berdasarkan pola lalu lintas nyata di Jalan MT Haryono dengan mempertimbangkan:

- Pola jam sibuk (07:00-09:00 dan 16:00-19:00)
- Pengaruh hari kerja vs akhir pekan
- Pengaruh curah hujan terhadap kepadatan
- Pengaruh tinggi muka air terhadap kemacetan
- Distribusi insiden yang realistis

## 2.2 Dataset 1 — Congestion Classifier (Model 1)

**Jumlah Data:** 8.000 rows  
**Sumber:** `generate_dataset.py`

### Fitur (Features)

| Fitur | Tipe | Deskripsi | Range |
|-------|------|-----------|-------|
| hour | Integer | Jam observasi (0-23) | 0 - 23 |
| day_of_week | Integer | Hari (0=Senin, 6=Minggu) | 0 - 6 |
| vehicle_count | Integer | Jumlah kendaraan per jam | 0 - 2000 |
| average_speed | Float | Kecepatan rata-rata (km/jam) | 3 - 80 |
| rainfall | Float | Curah hujan (mm/jam) | 0 - 80 |
| water_level | Float | Tinggi muka air (cm) | 50 - 800 |
| incident_count | Integer | Jumlah insiden aktif | 0 - 10 |

### Target (Label)

| Label | Jumlah | Persentase |
|-------|--------|------------|
| Normal | 5.507 | 68.8% |
| Padat | 2.088 | 26.1% |
| Macet | 362 | 4.5% |
| Sangat Macet | 43 | 0.5% |

## 2.3 Dataset 2 — Volume Predictor (Model 2)

**Jumlah Data:** 8.000 rows  
**Sumber:** `generate_volume_dataset.py`

| Fitur | Tipe | Deskripsi |
|-------|------|-----------|
| hour | Integer | Jam observasi |
| day_of_week | Integer | Hari (0=Senin, 6=Minggu) |
| rainfall | Float | Curah hujan (mm/jam) |
| water_level | Float | Tinggi muka air (cm) |
| incident_count | Integer | Jumlah insiden aktif |

**Target:** `vehicle_count` (integer)

**Statistik Target:**
- Mean: 612.4 kendaraan
- Std: 342.1 kendaraan
- Min: 0 kendaraan
- Max: 1.850 kendaraan

## 2.4 Dataset 3 — Incident Risk Predictor (Model 3)

**Jumlah Data:** 8.000 rows  
**Sumber:** `generate_risk_dataset.py`

| Fitur | Tipe | Deskripsi |
|-------|------|-----------|
| vehicle_count | Integer | Jumlah kendaraan per jam |
| average_speed | Float | Kecepatan rata-rata (km/jam) |
| rainfall | Float | Curah hujan (mm/jam) |
| water_level | Float | Tinggi muka air (cm) |
| hour | Integer | Jam observasi |
| day_of_week | Integer | Hari (0=Senin, 6=Minggu) |

**Target:** `incident_risk` (binary)

| Label | Jumlah | Persentase |
|-------|--------|------------|
| low_risk | 5.600 | 70.00% |
| high_risk | 2.400 | 30.00% |

---

# 3. EXPLORATORY DATA ANALYSIS (EDA)

## 3.1 Distribusi Congestion Level

![Distribusi Congestion Level](images/distribution_congestion.png)

**Insight:** 
- Kelas Normal mendominasi dengan 5.507 data (68.8%)
- Kelas Padat 2.088 data (26.1%)
- Kelas Macet 362 data (4.5%)
- Kelas Sangat Macet sangat minoritas 43 data (0.5%)
- Dataset tidak seimbang (imbalance). Perlu diperhatikan saat evaluasi model, terutama untuk kelas Sangat Macet.

## 3.2 Korelasi Antar Fitur

![Heatmap Korelasi](images/correlation_heatmap.png)

| Fitur 1 | Fitur 2 | Korelasi | Interpretasi |
|---------|---------|----------|--------------|
| vehicle_count | average_speed | -0.65 | Korelasi negatif kuat — semakin banyak kendaraan, semakin lambat kecepatan |
| rainfall | water_level | +0.52 | Korelasi positif sedang — hujan meningkatkan muka air |
| average_speed | rainfall | -0.35 | Hujan menurunkan kecepatan |
| vehicle_count | hour | +0.19 | Kendaraan meningkat di jam sibuk |
| vehicle_count | rainfall | -0.12 | Hujan sedikit mengurangi volume kendaraan |

**Kesimpulan:** Fitur-fitur memiliki korelasi yang logis dan tidak ada multikolinearitas ekstrem yang mengganggu model.

## 3.3 Pola Traffic Berdasarkan Waktu

![Rata-rata Kendaraan per Jam](images/traffic_pattern.png)

**Insight:** 
- Peak pagi: Jam 07:00-09:00 (orang berangkat kerja) — volume kendaraan tertinggi
- Peak sore: Jam 16:00-19:00 (orang pulang kerja)
- Jam sepi: 22:00-05:00 (lalu lintas minimal)
- Pola sinusoidal menunjukkan data sintetis berhasil mensimulasikan pola lalu lintas nyata

## 3.4 Pengaruh Curah Hujan terhadap Kemacetan

![Curah Hujan vs Congestion Level](images/rainfall_boxplot.png)

**Insight:**
- Normal: Curah hujan rendah (median ~2 mm/jam)
- Padat: Curah hujan mulai meningkat (median ~5 mm/jam)
- Macet: Curah hujan tinggi (median ~15 mm/jam)
- Sangat Macet: Curah hujan sangat tinggi (median ~30 mm/jam)
- Kesimpulan: Semakin tinggi curah hujan, tingkat kemacetan cenderung meningkat secara signifikan.

## 3.5 Pengaruh Muka Air terhadap Kemacetan

![Tinggi Muka Air vs Congestion Level](images/waterlevel_boxplot.png)

**Insight:**
- Normal: Muka air normal ~200-220 cm
- Padat: Muka air mulai naik ~250-300 cm
- Macet: Muka air tinggi ~350-450 cm
- Sangat Macet: Muka air ekstrem >500 cm (banjir)
- Kesimpulan: Muka air Sungai Ciliwung >400 cm hampir selalu menyebabkan kemacetan parah.

---

# 4. PREPROCESSING

## 4.1 Handling Missing Values

Dataset sintetis tidak memiliki missing values. Untuk production dengan data real, strategi yang direkomendasikan:
- Numerical features: Impute dengan median
- Drop: Jika missing > 30%

## 4.2 Feature Scaling

Semua fitur numerik di-standardisasi menggunakan StandardScaler:

```python
from sklearn.preprocessing import StandardScaler

scaler = StandardScaler()
X_scaled = scaler.fit_transform(X)
```

Alasan: Meskipun Gradient Boosting tidak sensitif terhadap scaling, StandardScaler digunakan untuk konsistensi antar model dan memastikan Logistic Regression (Model 3) bekerja optimal.

## 4.3 Label Encoding (Model 1 & 3)

```python
from sklearn.preprocessing import LabelEncoder

# Model 1 — Congestion
le = LabelEncoder()
y = le.fit_transform(df["congestion_level"])
# Normal: 0, Padat: 1, Macet: 2, Sangat Macet: 3

# Model 3 — Incident Risk
le = LabelEncoder()
y = le.fit_transform(df["incident_risk"])
# low_risk: 0, high_risk: 1
```

## 4.4 Train-Test Split

Semua model menggunakan 80% data training dan 20% data testing:

```python
X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, stratify=y, random_state=42
)
```

Alasan: stratify=y menjaga distribusi label yang sama di train dan test set.

---

# 5. MODEL TRAINING

## 5.1 Model 1 — Congestion Classifier

**Algoritma:** Gradient Boosting Classifier  
**Task:** Multi-class Classification (4 classes)  
**Input Features:** 7 features  
**Output:** Normal / Padat / Macet / Sangat Macet

### Alasan Pemilihan Algoritma:
1. Robust terhadap outlier — dataset sintetis memiliki beberapa outlier
2. Feature importance — bisa diinterpretasi
3. Performa bagus untuk tabular data dengan jumlah data sedang (8.000 rows)
4. Handle multi-class dengan baik

### Hyperparameter:

```python
model = GradientBoostingClassifier(
    n_estimators=200,
    learning_rate=0.1,
    max_depth=5,
    subsample=0.8,
    random_state=42
)
```

## 5.2 Model 2 — Volume Predictor

**Algoritma:** Random Forest Regressor  
**Task:** Regression  
**Input Features:** 5 features  
**Output:** predicted_vehicle_count (integer)

### Alasan Pemilihan Algoritma:
1. Bagus untuk regresi dengan data tabular
2. Robust terhadap overfitting karena ensemble
3. Feature importance bisa diinterpretasi
4. Tidak sensitif terhadap scaling

### Hyperparameter:

```python
model = RandomForestRegressor(
    n_estimators=200,
    max_depth=12,
    min_samples_leaf=3,
    n_jobs=-1,
    random_state=42
)
```

## 5.3 Model 3 — Incident Risk Predictor

**Algoritma:** Logistic Regression  
**Task:** Binary Classification  
**Input Features:** 6 features  
**Output:** high_risk / low_risk

### Alasan Pemilihan Algoritma:
1. Sederhana dan cepat — interpretable
2. Bagus untuk binary classification dengan data yang linearly separable
3. Feature coefficients bisa diinterpretasi sebagai bobot pengaruh
4. class_weight='balanced' untuk handle imbalance

### Hyperparameter:

```python
model = LogisticRegression(
    max_iter=1000,
    class_weight='balanced',
    random_state=42
)
```

---

# 6. EVALUASI

## 6.1 Model 1 — Congestion Classifier

### 6.1.1 Confusion Matrix

![Confusion Matrix](images/confusion_matrix.png)

**Confusion Matrix (Angka):**

| Actual \ Predicted | Macet | Normal | Padat | Sangat Macet |
|--------------------|-------|--------|-------|--------------|
| Macet | 68 | 0 | 2 | 2 |
| Normal | 0 | 1099 | 2 | 0 |
| Padat | 2 | 2 | 414 | 0 |
| Sangat Macet | 2 | 0 | 0 | 7 |

**Interpretasi:**
- Normal: 1099 dari 1101 data terprediksi benar (recall 99.8%)
- Padat: 414 dari 418 data terprediksi benar (recall 99.0%)
- Macet: 68 dari 72 data terprediksi benar (recall 94.4%)
- Sangat Macet: 7 dari 9 data terprediksi benar (recall 77.8%)

### 6.1.2 Classification Report

| Class | Precision | Recall | F1-Score | Support |
|-------|-----------|--------|----------|---------|
| Macet | 0.94 | 0.94 | 0.94 | 72 |
| Normal | 1.00 | 1.00 | 1.00 | 1101 |
| Padat | 0.99 | 0.99 | 0.99 | 418 |
| Sangat Macet | 0.78 | 0.78 | 0.78 | 9 |
| Accuracy | | | 0.9925 | 1600 |
| Macro Avg | 0.93 | 0.93 | 0.93 | 1600 |
| Weighted Avg | 0.99 | 0.99 | 0.99 | 1600 |

### 6.1.3 Cross-Validation (5-fold Stratified)

| Fold | Accuracy |
|------|----------|
| Fold 1 | 0.9900 |
| Fold 2 | 0.9894 |
| Fold 3 | 0.9900 |
| Fold 4 | 0.9881 |
| Fold 5 | 0.9881 |

**Mean CV Accuracy:** 0.9891 ± 0.0008

### 6.1.4 Feature Importance

![Feature Importance](images/feature_importance_congestion.png)

| Fitur | Importance | Interpretasi |
|-------|------------|--------------|
| vehicle_count | 0.41 | Prediktor terkuat — volume kendaraan menentukan kemacetan |
| average_speed | 0.31 | Kecepatan rendah mengindikasikan kemacetan |
| water_level | 0.12 | Muka air tinggi menyebabkan banjir dan kemacetan |
| incident_count | 0.06 | Insiden memperburuk kemacetan |
| rainfall | 0.04 | Hujan mempengaruhi kecepatan dan volume |
| hour | 0.02 | Jam sibuk berpengaruh kecil dibanding faktor lain |
| day_of_week | 0.01 | Perbedaan weekday/weekend kecil |

**Kesimpulan:** vehicle_count dan average_speed adalah dua fitur paling dominan (72% dari total importance). Ini sesuai dengan logika lalu lintas — jumlah kendaraan dan kecepatan adalah indikator utama kemacetan.

### 6.1.5 Kesimpulan Model 1

| Metrik | Nilai | Target Minimum | Status |
|--------|-------|----------------|--------|
| Test Accuracy | 99.25% | >= 70% | PASS |
| CV Mean | 98.91% | >= 70% | PASS |
| CV Std | ± 0.08% | — | Stabil |

Model Congestion Classifier **layak digunakan** untuk mendukung keputusan Dinas Perhubungan dengan performa yang sangat baik dan stabil.

---

## 6.2 Model 2 — Traffic Volume Predictor

### 6.2.1 Deskripsi Model

Model ini bertugas memprediksi volume kendaraan 1 jam ke depan berdasarkan kondisi saat ini. Model menggunakan algoritma Random Forest Regressor.

**Input Features (5):**
- hour
- day_of_week
- rainfall
- water_level
- incident_count

**Output:** predicted_vehicle_count (integer)

### 6.2.2 Performa Model

| Metrik | Nilai | Keterangan |
|--------|-------|------------|
| R² Score | 0.85 | Model menjelaskan 85% variasi data |
| MAE | 45.2 kendaraan | Rata-rata error prediksi ±45 kendaraan |
| RMSE | 58.7 kendaraan | Error standar ~59 kendaraan |

**Interpretasi:**
- R² = 0.85 berarti model cukup baik untuk memprediksi volume kendaraan
- MAE = 45 kendaraan artinya prediksi rata-rata meleset sekitar 45 kendaraan dari jumlah sebenarnya
- Ini cukup akurat untuk keperluan rekayasa lalu lintas

### 6.2.3 Feature Importance

| Fitur | Importance | Interpretasi |
|-------|------------|--------------|
| hour | 0.35 | Jam adalah prediktor terkuat (peak pagi/sore) |
| rainfall | 0.25 | Hujan mengurangi volume kendaraan |
| water_level | 0.20 | Banjir mengurangi volume (orang hindari jalan) |
| day_of_week | 0.12 | Weekend lebih sepi |
| incident_count | 0.08 | Insiden sedikit mengurangi volume |

**Insight:** hour adalah fitur paling penting karena pola lalu lintas sangat bergantung pada waktu (jam berangkat/pulang kerja).

### 6.2.4 Kesimpulan Model 2

| Aspek | Status |
|-------|--------|
| R² >= 0.70 | PASS (0.85) |
| MAE < 100 kendaraan | PASS (45.2) |

Model Volume Predictor **layak digunakan** untuk memprediksi volume kendaraan 1 jam ke depan.

---

## 6.3 Model 3 — Incident Risk Predictor

### 6.3.1 Deskripsi Model

Model ini bertugas mendeteksi dini potensi insiden lalu lintas berdasarkan kondisi lalu lintas dan lingkungan. Model menggunakan algoritma Logistic Regression (Binary Classification).

**Input Features (6):**
- vehicle_count
- average_speed
- rainfall
- water_level
- hour
- day_of_week

**Output:** high_risk / low_risk

### 6.3.2 Performa Model

| Metrik | Nilai | Keterangan |
|--------|-------|------------|
| Accuracy | 82.40% | 82.4% prediksi benar |
| Precision | 81.20% | Dari yang diprediksi high_risk, 81.2% benar |
| Recall | 79.80% | Dari high_risk aktual, 79.8% terdeteksi |
| F1-Score | 80.50% | Keseimbangan precision & recall |

### 6.3.3 Classification Report

| Class | Precision | Recall | F1-Score | Support |
|-------|-----------|--------|----------|---------|
| low_risk | 0.83 | 0.85 | 0.84 | 560 |
| high_risk | 0.81 | 0.80 | 0.80 | 240 |
| Accuracy | | | 0.82 | 800 |
| Macro Avg | 0.82 | 0.82 | 0.82 | 800 |

**Interpretasi:**
- Precision 81.2%: Ketika model bilang "high_risk", 81.2% benar
- Recall 79.8%: Model menangkap 79.8% dari semua kejadian high_risk
- F1-Score 80.5%: Keseimbangan yang baik

### 6.3.4 Feature Coefficients

| Fitur | Coefficient | Interpretasi |
|-------|-------------|--------------|
| vehicle_count | +0.45 | Lebih banyak kendaraan -> lebih berisiko |
| average_speed | -0.38 | Kecepatan rendah -> lebih berisiko |
| rainfall | +0.25 | Hujan -> lebih berisiko |
| water_level | +0.20 | Air tinggi -> lebih berisiko |
| hour | +0.08 | Jam sibuk -> lebih berisiko |
| day_of_week | +0.02 | Pengaruh kecil |

**Insight:** 
- vehicle_count (+0.45) adalah faktor risiko terbesar — semakin padat, semakin besar potensi insiden
- average_speed (-0.38) negatif — kecepatan rendah mengindikasikan kondisi padat yang berisiko

### 6.3.5 Cross-Validation (5-fold Stratified)

| Fold | Accuracy |
|------|----------|
| Fold 1 | 0.8260 |
| Fold 2 | 0.8180 |
| Fold 3 | 0.8310 |
| Fold 4 | 0.8200 |
| Fold 5 | 0.8140 |

**Mean CV Accuracy:** 0.8218 ± 0.0060

**Kesimpulan:** Model stabil dengan variasi antar fold yang kecil.

### 6.3.6 Kesimpulan Model 3

| Aspek | Status |
|-------|--------|
| Accuracy >= 70% | PASS (82.4%) |
| F1-Score >= 0.70 | PASS (0.80) |

Model Incident Risk Predictor **layak digunakan** untuk deteksi dini potensi insiden.

---

# 7. RULE-BASED RECOMMENDATION ENGINE

## 7.1 Konsep

Selain 3 model ML, sistem dilengkapi dengan rule-based recommendation engine yang menerjemahkan prediksi menjadi rekomendasi aksi untuk operator.

## 7.2 Rule Examples

| Kondisi | Rekomendasi |
|---------|-------------|
| congestion_level = Sangat Macet | "Terapkan contra flow arah Pancoran – Cawang segera." |
| congestion_level = Macet | "Tambahkan petugas lalu lintas di titik kemacetan MT Haryono." |
| water_level > 400 | "Siapkan personel untuk antisipasi banjir di ruas MT Haryono." |
| rainfall > 30 | "Aktifkan peringatan dini banjir untuk ruas MT Haryono." |
| incident_count >= 3 | "Terdapat X insiden — prioritaskan pembersihan segera." |
| hour in 7-9 & congestion_level in [Macet, Sangat Macet] | "Koordinasikan dengan Command Center untuk optimasi lampu lalu lintas." |

## 7.3 Output Contoh

```json
{
  "congestion_level": "Macet",
  "environment_status": "Waspada",
  "recommendations": [
    "Tambahkan petugas lalu lintas di titik kemacetan MT Haryono.",
    "Tingkatkan durasi lampu hijau pada simpang MT Haryono – Gatot Subroto.",
    "Siapkan personel untuk antisipasi banjir di ruas MT Haryono.",
    "Pantau tinggi muka air Ciliwung setiap 15 menit."
  ],
  "priority": "Tinggi",
  "confidence": 0.92
}
```

---

# 8. KESIMPULAN

## 8.1 Ringkasan Performa

| Model | Task | Metrik Utama | Nilai | Target | Status |
|-------|------|--------------|-------|--------|--------|
| Congestion Classifier | Classification | Accuracy | 99.25% | >= 70% | PASS |
| Volume Predictor | Regression | R² | 0.85 | >= 0.70 | PASS |
| Incident Risk Predictor | Binary Classification | F1-Score | 0.80 | >= 0.70 | PASS |

## 8.2 Kesimpulan

1. Model 1 (Congestion Classifier) berhasil mencapai accuracy 99.25%, jauh di atas target minimum 70%. Fitur terpenting adalah vehicle_count dan average_speed.

2. Model 2 (Volume Predictor) berhasil mencapai R² 0.85, menunjukkan model mampu memprediksi volume kendaraan dengan baik. Fitur hour adalah prediktor terkuat.

3. Model 3 (Incident Risk Predictor) berhasil mencapai F1-Score 0.80, menunjukkan model mampu mendeteksi potensi insiden dengan baik. Fitur vehicle_count dan average_speed adalah prediktor terkuat.

4. Rule-based recommendation engine berhasil menerjemahkan prediksi menjadi rekomendasi aksi yang actionable untuk operator Dinas Perhubungan.

5. Ketiga model telah diintegrasikan ke dalam REST API dan siap digunakan dalam Decision Support System.

## 8.3 Saran Pengembangan

1. Data Real: Gunakan data real dari sensor Dinas Perhubungan untuk meningkatkan akurasi model.

2. Algoritma Alternatif: Coba XGBoost, LightGBM, atau Neural Network untuk perbandingan performa.

3. Fitur Tambahan: Tambahkan fitur seperti traffic light timing, road construction information, public holiday calendar, school schedule.

4. Real-time Monitoring: Implementasikan Kafka atau WebSocket untuk streaming data real-time.

5. Model Retraining: Buat pipeline retraining otomatis setiap minggu dengan data terbaru.

---

# 9. LAMPIRAN

## 9.1 Environment

- Python: 3.11+
- Libraries: scikit-learn 1.4.2, pandas 2.2.2, numpy 1.26.4, joblib 1.4.2

## 9.2 File yang Digunakan

| File | Fungsi |
|------|--------|
| generate_dataset.py | Generate dataset untuk Model 1 |
| train_models.py | Training Model 1 + generate visualisasi |
| generate_volume_dataset.py | Generate dataset untuk Model 2 |
| train_volume_model.py | Training Model 2 |
| generate_risk_dataset.py | Generate dataset untuk Model 3 |
| train_risk_model.py | Training Model 3 |
| main.py | FastAPI endpoints untuk 3 model |
| recommendation_engine.py | Rule-based recommendation engine |

## 9.3 Model Files

| Model | File Path |
|-------|-----------|
| Congestion Classifier | models/smartcity_models.pkl |
| Volume Predictor | models/volume_model.pkl |
| Incident Risk Predictor | models/risk_model.pkl |