# main.py — FastAPI Smart City ML Service
# Skeleton placeholder — akan diimplementasikan penuh di Sprint 3 (Hari 8-10)
# Jangan hapus file ini, dibutuhkan untuk struktur folder yang benar

from fastapi import FastAPI

app = FastAPI(
    title="Smart City ML Service",
    version="1.0.0",
    description="Traffic, Air Quality & Anomaly prediction — WIP"
)

@app.get("/health")
def health():
    return {"status": "ok", "service": "python-ml", "note": "WIP — full implementation Sprint 3"}