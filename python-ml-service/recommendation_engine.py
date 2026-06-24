from typing import List, Dict, Any

def generate_recommendations(
    congestion_level: str,
    vehicle_count: int,
    average_speed: float,
    rainfall: float,
    water_level: float,
    incident_count: int,
    hour: int,
    confidence: float,
) -> Dict[str, Any]:
    recommendations: List[str] = []
    priority = "Rendah"
    
    # ── Status Lingkungan ───────────────────────────────────
    if water_level > 500:
        env_status = "Bahaya"
    elif water_level > 400 or rainfall > 30:
        env_status = "Waspada"
    elif water_level > 300 or rainfall > 10:
        env_status = "Siaga"
    else:
        env_status = "Aman"
    
    # ── Rule: Banjir / Muka Air Tinggi ──────────────────────
    if water_level > 500:
        recommendations.append("Tutup sementara akses ruas MT Haryono segmen terdampak banjir.")
        recommendations.append("Alihkan kendaraan menuju Jalan Dewi Sartika.")
        recommendations.append("Alihkan kendaraan menuju Tol Dalam Kota.")
        recommendations.append("Siapkan personel dan peralatan antisipasi banjir di titik rawan.")
        priority = "Sangat Tinggi"
    
    elif water_level > 400:
        recommendations.append("Siapkan personel untuk antisipasi banjir di ruas MT Haryono.")
        recommendations.append("Pantau tinggi muka air Ciliwung setiap 15 menit.")
        if rainfall > 10:
            recommendations.append("Koordinasikan dengan Pintu Air Manggarai untuk update debit air.")
        priority = "Tinggi"
    
    # ── Rule: Kemacetan ──────────────────────────────────────
    if congestion_level == "Sangat Macet":
        recommendations.append("Terapkan contra flow arah Pancoran – Cawang segera.")
        recommendations.append("Tempatkan petugas lalu lintas di Simpang Cawang dan Simpang Tebet.")
        recommendations.append("Koordinasikan pengalihan arus dengan petugas simpang terdekat.")
        if vehicle_count > 1500:
            recommendations.append("Terapkan sistem satu arah sementara pada ruas terpadat.")
        if priority == "Rendah":
            priority = "Sangat Tinggi"
        elif priority == "Sedang":
            priority = "Sangat Tinggi"
    
    elif congestion_level == "Macet":
        recommendations.append("Tambahkan petugas lalu lintas di titik kemacetan MT Haryono.")
        recommendations.append("Tingkatkan durasi lampu hijau pada simpang MT Haryono – Gatot Subroto.")
        if average_speed < 15:
            recommendations.append("Pertimbangkan penerapan contra flow parsial.")
        if priority == "Rendah":
            priority = "Tinggi"
        elif priority == "Sedang":
            priority = "Tinggi"
    
    elif congestion_level == "Padat":
        recommendations.append("Pantau kondisi lalu lintas MT Haryono secara intensif.")
        if hour in range(7, 10) or hour in range(16, 20):
            recommendations.append("Siagakan petugas di Simpang Cawang untuk antisipasi puncak arus.")
        if priority == "Rendah":
            priority = "Sedang"
    
    else:  # Normal
        recommendations.append("Kondisi lalu lintas MT Haryono normal. Pantau berkala.")
        if priority == "Rendah":
            priority = "Rendah"
    
    # ── Rule: Curah Hujan Tinggi ─────────────────────────────
    if rainfall > 30 and env_status not in ["Bahaya"]:
        recommendations.append("Aktifkan peringatan dini banjir untuk ruas MT Haryono.")
        recommendations.append("Kurangi kecepatan batas maksimum sementara menjadi 40 km/jam.")
        if priority == "Rendah":
            priority = "Sedang"
        elif priority == "Sedang":
            priority = "Tinggi"
    
    # ── Rule: Banyak Insiden ─────────────────────────────────
    if incident_count >= 3:
        recommendations.append(
            f"Terdapat {incident_count} insiden aktif — prioritaskan pembersihan segera."
        )
        recommendations.append("Alihkan kendaraan dari ruas berinsiden.")
        if priority in ["Rendah", "Sedang"]:
            priority = "Tinggi"
    elif incident_count >= 1:
        recommendations.append(
            f"Terdapat {incident_count} insiden — kirim petugas ke lokasi kejadian."
        )
        if priority == "Rendah":
            priority = "Sedang"
    
    # ── Rule: Jam Sibuk ─────────────────────────────────────
    if hour in range(7, 10) and congestion_level in ["Padat", "Macet", "Sangat Macet"]:
        recommendations.append(
            "Koordinasikan dengan Command Center untuk optimasi lampu lalu lintas koridor MT Haryono."
        )
    
    # ── Deduplicate sambil pertahankan urutan ──────────────
    seen = set()
    unique_recs = []
    for r in recommendations:
        if r not in seen:
            seen.add(r)
            unique_recs.append(r)
    
    # ── Minimal 1 rekomendasi ──────────────────────────────
    if not unique_recs:
        unique_recs.append("Kondisi dalam batas normal. Tidak ada tindakan mendesak.")
    
    return {
        "congestion_level": congestion_level,
        "environment_status": env_status,
        "recommendations": unique_recs,
        "priority": priority,
        "confidence": round(confidence, 4),
    }